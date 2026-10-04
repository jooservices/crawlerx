import { connect, isIP } from 'node:net';
import { createServer, request as httpRequest } from 'node:http';
import { request as httpsRequest } from 'node:https';
import { assertSafeBrowserUrl, SsrfBlockedError } from './ssrf-guard.mjs';

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
export async function createSsrfProxyServer({ assertSafeUrl = assertSafeBrowserUrl } = {}) {
    const sockets = new Set();
    let blocked = false;
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

    async function forwardHttpRequest(request, response) {
        let target;
        let resolved;
        try {
            target = new URL(request.url ?? '');
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

        const headers = stripHopByHopHeaders(request.headers);
        headers.host = target.host;
        headers.connection = 'close';
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
            response.writeHead(
                upstream.statusCode ?? 502,
                upstream.statusMessage,
                stripHopByHopHeaders(upstream.headers),
            );
            upstream.on('error', () => response.destroy());
            upstream.pipe(response);
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

        let resolved;
        try {
            resolved = await resolveTarget(`https://${hostname.includes(':') ? `[${hostname}]` : hostname}:${port}/`);
        } catch (error) {
            if (error instanceof SsrfBlockedError) {
                rejectSocket(clientSocket);
            } else {
                clientSocket.end('HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n');
            }
            return;
        }

        const upstream = connect({ host: resolved.addresses[0].address, port });
        trackSocket(upstream);
        upstream.once('connect', () => {
            if (clientSocket.destroyed) {
                upstream.destroy();
                return;
            }
            clientSocket.write('HTTP/1.1 200 Connection Established\r\n\r\n');
            if (head.length > 0) upstream.write(head);
            clientSocket.pipe(upstream).pipe(clientSocket);
        });
        upstream.once('error', () => {
            if (!clientSocket.destroyed) {
                clientSocket.end('HTTP/1.1 502 Bad Gateway\r\nConnection: close\r\n\r\n');
            }
        });
        clientSocket.once('close', () => upstream.destroy());
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

        const headers = { ...request.headers, host: target.host, connection: 'Upgrade' };
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
            if (upstreamHead.length > 0) clientSocket.write(upstreamHead);
            clientSocket.pipe(upstreamSocket).pipe(clientSocket);
        });
        outbound.on('response', (upstreamResponse) => {
            const status = upstreamResponse.statusCode ?? 502;
            const reason = upstreamResponse.statusMessage ?? 'Bad Gateway';
            clientSocket.write(`HTTP/1.1 ${status} ${reason}\r\nConnection: close\r\n\r\n`);
            upstreamResponse.pipe(clientSocket);
        });
        outbound.on('error', () => clientSocket.end('HTTP/1.1 502 Bad Gateway\r\nConnection: close\r\n\r\n'));
        outbound.end();
    }

    return {
        url: `http://127.0.0.1:${address.port}`,
        wasBlocked: () => blocked,
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
