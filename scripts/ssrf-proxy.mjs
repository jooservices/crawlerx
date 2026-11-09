import { isIP } from 'node:net';
import { Transform } from 'node:stream';
import { createServer, request as httpRequest } from 'node:http';
import { request as httpsRequest } from 'node:https';
import { createServer as createTlsServer } from 'node:https';
import { createPublicKey, createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { createBrotliDecompress, createGunzip, createInflate } from 'node:zlib';
import { assertSafeBrowserUrl, SsrfBlockedError } from './ssrf-guard.mjs';
import { configuredMaxResponseBytes } from './fetch-limits.mjs';
import { withoutConfiguredCookies } from './request-security.mjs';

const hopByHopHeaderNames = new Set([
    'connection',
    'keep-alive',
    'proxy-authenticate',
    'proxy-authorization',
    'proxy-connection',
    'te',
    'trailer',
    'transfer-encoding',
    'upgrade',
]);

let mitmCertificate = null;

export function ssrfProxyCertificateSpkiHash() {
    return certificate().spkiHash;
}

function certificate() {
    if (mitmCertificate !== null) {
        return mitmCertificate;
    }

    const directory = mkdtempSync(join(tmpdir(), 'crawlerx-ssrf-proxy-'));
    const keyPath = join(directory, 'key.pem');
    const certificatePath = join(directory, 'certificate.pem');
    execFileSync('openssl', [
        'req', '-x509', '-newkey', 'rsa:2048', '-nodes',
        '-keyout', keyPath,
        '-out', certificatePath,
        '-days', '1',
        '-subj', '/CN=crawlerx-ssrf-proxy.invalid',
    ], { stdio: 'ignore' });

    const cert = readFileSync(certificatePath);
    const publicKey = createPublicKey(cert).export({ type: 'spki', format: 'der' });
    const spkiHash = createHash('sha256').update(publicKey).digest('base64');
    mitmCertificate = { key: readFileSync(keyPath), cert, spkiHash, directory };
    process.once('exit', () => rmSync(directory, { recursive: true, force: true }));

    return mitmCertificate;
}

function stripHopByHopHeaders(headers) {
    const filtered = { ...headers };
    const connectionValues = Array.isArray(filtered.connection)
        ? filtered.connection
        : [filtered.connection];

    for (const value of connectionValues) {
        if (typeof value !== 'string') continue;
        for (const token of value.split(',')) {
            const name = token.trim().toLowerCase();
            if (name !== '') delete filtered[name];
        }
    }

    for (const name of hopByHopHeaderNames) {
        delete filtered[name];
    }

    return filtered;
}

/** Start a loopback-only egress proxy for one browser context/process. */
export async function createSsrfProxyServer({
    assertSafeUrl = assertSafeBrowserUrl,
    maxResponseBytes = configuredMaxResponseBytes(),
    requestOrigin = null,
    sensitiveHeaders = [],
    configuredCookieNames = [],
} = {}) {
    const normalizedSensitiveHeaders = new Set(sensitiveHeaders.map((name) => String(name).toLowerCase()));
    const normalizedConfiguredCookieNames = configuredCookieNames.map((name) => String(name));
    const sockets = new Set();
    let blocked = false;
    let blockedError = null;
    let credentialRedirectBlocked = false;
    let responseTooLarge = false;
    let closed = false;
    const server = createServer((request, response) => {
        void forwardHttpRequest(request, response);
    });

    server.on('connection', trackSocket);
    server.on('connect', (request, clientSocket, head) => {
        void tunnelConnect(request, clientSocket, head);
    });
    server.on('upgrade', (request, clientSocket, head) => {
        void forwardUpgrade(request, clientSocket, head);
    });

    await new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(0, '127.0.0.1', () => {
            server.removeListener('error', reject);
            resolve();
        });
    });

    const address = server.address();
    if (address === null || typeof address === 'string') {
        throw new Error('Unable to start SSRF proxy');
    }

    async function resolveTarget(url) {
        try {
            return await assertSafeUrl.resolve(url);
        } catch (error) {
            if (error instanceof SsrfBlockedError) {
                blocked = true;
                blockedError ??= error;
            }
            throw error;
        }
    }

    function trackSocket(socket) {
        sockets.add(socket);
        socket.on('error', (error) => {
            if (!closed && !['EPIPE', 'ECONNRESET'].includes(error.code)) {
                process.stderr.write(`SSRF proxy socket error: ${error.code ?? 'unknown'}\n`);
            }
        });
        socket.once('close', () => sockets.delete(socket));
    }

    function rejectRequest(response) {
        response.writeHead(403, { Connection: 'close', 'Content-Type': 'text/plain' });
        response.end('ssrf_blocked');
    }

    function rejectSocket(socket) {
        if (!socket.destroyed) {
            socket.end('HTTP/1.1 403 Forbidden\r\nConnection: close\r\nContent-Length: 12\r\n\r\nssrf_blocked');
        }
    }

    async function forwardHttpRequest(request, response, baseUrl = null) {
        let target;
        let resolved;
        try {
            target = new URL(request.url ?? '', baseUrl ?? undefined);
            if (!['http:', 'https:'].includes(target.protocol)) {
                throw new SsrfBlockedError();
            }
            resolved = await resolveTarget(target.href);
        } catch (error) {
            if (error instanceof SsrfBlockedError) {
                rejectRequest(response);
            } else {
                response.writeHead(400, { Connection: 'close' });
                response.end();
            }
            return;
        }

        let headers = stripHopByHopHeaders(request.headers);
        if (requestOrigin !== null && target.origin !== requestOrigin) {
            const hasSensitiveHeader = [...normalizedSensitiveHeaders].some((name) => headers[name] !== undefined);
            if (hasSensitiveHeader) {
                credentialRedirectBlocked = true;
                rejectRequest(response);
                return;
            }
            for (const name of normalizedSensitiveHeaders) {
                delete headers[name];
            }
            headers = withoutConfiguredCookies(headers, normalizedConfiguredCookieNames);
        }
        headers.host = target.host;
        headers.connection = 'close';
        headers['accept-encoding'] = 'gzip, deflate, br';
        const transport = target.protocol === 'https:' ? httpsRequest : httpRequest;
        const outbound = transport({
            protocol: target.protocol,
            hostname: resolved.hostname,
            port: Number(target.port) || (target.protocol === 'https:' ? 443 : 80),
            path: `${target.pathname}${target.search}`,
            method: request.method,
            headers,
            lookup: pinnedLookup(resolved.addresses),
        }, (upstream) => {
            const encoding = String(upstream.headers['content-encoding'] ?? '').toLowerCase();
            const decoder = encoding === ''
                ? null
                : encoding === 'gzip' || encoding === 'x-gzip'
                    ? createGunzip()
                    : encoding === 'deflate'
                        ? createInflate()
                        : encoding === 'br'
                            ? createBrotliDecompress()
                            : null;
            if (encoding !== '' && decoder === null) {
                response.writeHead(502, { Connection: 'close', 'Content-Length': '0' });
                response.end();
                upstream.destroy();
                return;
            }

            const contentLength = upstream.headers['content-length'];
            if (encoding === '' && typeof contentLength === 'string' && /^\d+$/.test(contentLength) && Number(contentLength) > maxResponseBytes) {
                responseTooLarge = true;
                upstream.resume();
                response.writeHead(413, { Connection: 'close', 'Content-Length': '0' });
                response.end();
                return;
            }

            const responseHeaders = stripHopByHopHeaders(upstream.headers);
            delete responseHeaders['content-encoding'];
            delete responseHeaders['content-length'];
            responseHeaders.connection = 'close';
            response.writeHead(upstream.statusCode ?? 502, upstream.statusMessage, responseHeaders);
            upstream.on('error', () => response.destroy());
            let bytes = 0;
            const limiter = new Transform({
                transform(chunk, encoding, callback) {
                    bytes += chunk.length;
                    if (bytes > maxResponseBytes) {
                        responseTooLarge = true;
                        callback(new Error('response_too_large'));
                        return;
                    }
                    callback(null, chunk);
                },
            });
            limiter.on('error', () => {
                decoder?.destroy();
                upstream.destroy();
                response.destroy();
            });
            if (decoder !== null) {
                decoder.on('error', () => {
                    upstream.destroy();
                    response.destroy();
                });
                upstream.pipe(decoder).pipe(limiter).pipe(response);
            } else {
                upstream.pipe(limiter).pipe(response);
            }
        });
        outbound.on('socket', trackSocket);
        response.on('error', () => outbound.destroy());
        outbound.on('error', () => {
            if (!response.headersSent) {
                response.writeHead(502, { Connection: 'close' });
            }
            response.end();
        });
        request.on('aborted', () => outbound.destroy());
        request.pipe(outbound);
    }

    async function tunnelConnect(request, clientSocket, head) {
        const match = (request.url ?? '').match(/^(?:\[([^\]]+)\]|([^:]+)):(\d+)$/);
        if (!match) {
            clientSocket.end('HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n');
            return;
        }

        const hostname = match[1] ?? match[2];
        const port = Number.parseInt(match[3], 10);
        if (!Number.isInteger(port) || port < 1 || port > 65535) {
            clientSocket.end('HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n');
            return;
        }

        try {
            await resolveTarget(`https://${hostname.includes(':') ? `[${hostname}]` : hostname}:${port}/`);
        } catch (error) {
            if (error instanceof SsrfBlockedError) {
                rejectSocket(clientSocket);
            } else {
                clientSocket.end('HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n');
            }
            return;
        }

        const targetOrigin = `https://${hostname.includes(':') ? `[${hostname}]` : hostname}:${port}`;
        const { key, cert } = certificate();
        const secureServer = createTlsServer({ key, cert, ALPNProtocols: ['http/1.1'] }, (secureRequest, response) => {
            void forwardHttpRequest(secureRequest, response, targetOrigin);
        });
        secureServer.on('secureConnection', trackSocket);
        secureServer.on('tlsClientError', () => clientSocket.destroy());
        secureServer.on('clientError', () => clientSocket.destroy());
        secureServer.on('upgrade', (_upgradeRequest, socket) => socket.destroy());

        clientSocket.write('HTTP/1.1 200 Connection Established\r\n\r\n');
        if (head.length > 0) {
            clientSocket.unshift(head);
        }
        secureServer.emit('connection', clientSocket);
    }

    async function forwardUpgrade(request, clientSocket, head) {
        let target;
        let resolved;
        try {
            target = new URL(request.url ?? '');
            if (target.protocol !== 'ws:') {
                throw new SsrfBlockedError();
            }
            resolved = await resolveTarget(target.href);
        } catch (error) {
            if (error instanceof SsrfBlockedError) {
                rejectSocket(clientSocket);
            } else {
                clientSocket.end('HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n');
            }
            return;
        }

        let headers = { ...request.headers, host: target.host, connection: 'Upgrade' };
        const targetOrigin = `http://${target.host}`;
        if (requestOrigin !== null && targetOrigin !== requestOrigin) {
            for (const name of normalizedSensitiveHeaders) {
                delete headers[name];
            }
            headers = withoutConfiguredCookies(headers, normalizedConfiguredCookieNames);
        }
        delete headers['proxy-authorization'];
        delete headers['proxy-connection'];
        const outbound = httpRequest({
            hostname: resolved.hostname,
            port: Number(target.port) || 80,
            path: `${target.pathname}${target.search}`,
            method: request.method,
            headers,
            lookup: pinnedLookup(resolved.addresses),
        });
        outbound.on('socket', trackSocket);
        outbound.on('upgrade', (upstreamResponse, upstreamSocket, upstreamHead) => {
            trackSocket(upstreamSocket);
            clientSocket.once('close', () => upstreamSocket.destroy());
            const status = upstreamResponse.statusCode ?? 101;
            const reason = upstreamResponse.statusMessage ?? 'Switching Protocols';
            clientSocket.write(`HTTP/1.1 ${status} ${reason}\r\n`);
            for (const [name, value] of Object.entries(upstreamResponse.headers)) {
                if (Array.isArray(value)) {
                    for (const item of value) clientSocket.write(`${name}: ${item}\r\n`);
                } else if (value !== undefined) {
                    clientSocket.write(`${name}: ${value}\r\n`);
                }
            }
            clientSocket.write('\r\n');
            if (head.length > 0) upstreamSocket.write(head);
            let responseBytes = upstreamHead.length;
            if (responseBytes > maxResponseBytes) {
                responseTooLarge = true;
                upstreamSocket.destroy();
                clientSocket.destroy();
                return;
            }
            if (upstreamHead.length > 0) clientSocket.write(upstreamHead);
            upstreamSocket.on('data', (chunk) => {
                responseBytes += chunk.length;
                if (responseBytes > maxResponseBytes) {
                    responseTooLarge = true;
                    upstreamSocket.destroy();
                    clientSocket.destroy();
                    return;
                }
                if (!clientSocket.destroyed) {
                    clientSocket.write(chunk);
                }
            });
            clientSocket.pipe(upstreamSocket);
        });
        outbound.on('response', (upstreamResponse) => {
            const contentLength = upstreamResponse.headers['content-length'];
            if (typeof contentLength === 'string' && /^\d+$/.test(contentLength) && Number(contentLength) > maxResponseBytes) {
                responseTooLarge = true;
                upstreamResponse.resume();
                clientSocket.end('HTTP/1.1 413 Payload Too Large\r\nConnection: close\r\nContent-Length: 0\r\n\r\n');
                return;
            }

            const status = upstreamResponse.statusCode ?? 502;
            const reason = upstreamResponse.statusMessage ?? 'Bad Gateway';
            clientSocket.write(`HTTP/1.1 ${status} ${reason}\r\nConnection: close\r\n\r\n`);
            let bytes = 0;
            const limiter = new Transform({
                transform(chunk, encoding, callback) {
                    bytes += chunk.length;
                    if (bytes > maxResponseBytes) {
                        responseTooLarge = true;
                        callback(new Error('response_too_large'));
                        return;
                    }
                    callback(null, chunk);
                },
            });
            limiter.on('error', () => {
                upstreamResponse.destroy();
                clientSocket.destroy();
            });
            upstreamResponse.pipe(limiter).pipe(clientSocket);
        });
        outbound.on('error', () => clientSocket.end('HTTP/1.1 502 Bad Gateway\r\nConnection: close\r\n\r\n'));
        outbound.end();
    }

    return {
        url: `http://127.0.0.1:${address.port}`,
        certificateSpkiHash: certificate().spkiHash,
        wasBlocked: () => blocked,
        blockedError: () => blockedError,
        wasCredentialRedirectBlocked: () => credentialRedirectBlocked,
        wasResponseTooLarge: () => responseTooLarge,
        close: async () => {
            if (closed) return;
            closed = true;
            for (const socket of sockets) socket.destroy();
            await new Promise((resolve) => server.close(() => resolve()));
        },
    };
}

function pinnedLookup(addresses) {
    return (_hostname, options, callback) => {
        if (typeof options === 'function') {
            callback = options;
            options = {};
        }
        if (options?.all) {
            callback(null, addresses.map(({ address }) => ({ address, family: isIP(address) })));
            return;
        }
        const { address } = addresses[0];
        callback(null, address, isIP(address));
    };
}
