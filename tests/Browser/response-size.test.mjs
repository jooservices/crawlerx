import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createServer, request as httpRequest } from 'node:http';
import { createServer as createHttpsServer, globalAgent as httpsAgent } from 'node:https';
import { createServer as createTcpServer, connect } from 'node:net';
import { connect as tlsConnect } from 'node:tls';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { gzipSync } from 'node:zlib';
import { existsSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { networkInterfaces } from 'node:os';
import { chromium } from 'playwright';
import { createSsrfProxyServer } from '../../scripts/ssrf-proxy.mjs';

const root = new URL('../..', import.meta.url).pathname;

test('SSRF proxy rejects a response over its configured byte limit', async () => {
    const upstream = createServer((_request, response) => {
        response.writeHead(200, { 'content-length': '6' });
        response.end('abcdef');
    });
    await new Promise((resolve, reject) => {
        upstream.once('error', reject);
        upstream.listen(0, '127.0.0.1', resolve);
    });

    const upstreamPort = upstream.address().port;
    const allowFixture = Object.assign(async () => {}, {
        resolve: async (value) => ({
            target: new URL(value),
            hostname: '127.0.0.1',
            addresses: [{ address: '127.0.0.1' }],
        }),
    });
    const proxy = await createSsrfProxyServer({ assertSafeUrl: allowFixture, maxResponseBytes: 4 });

    try {
        const proxyUrl = new URL(proxy.url);
        const response = await new Promise((resolve, reject) => {
            const request = httpRequest({
                hostname: proxyUrl.hostname,
                port: Number(proxyUrl.port),
                method: 'GET',
                path: `http://127.0.0.1:${upstreamPort}/large`,
            }, (incoming) => {
                incoming.resume();
                incoming.once('end', () => resolve(incoming));
            });
            request.once('error', reject);
            request.end();
        });

        assert.equal(response.statusCode, 413);
        assert.equal(proxy.wasResponseTooLarge(), true);
    } finally {
        await proxy.close();
        await new Promise((resolve) => upstream.close(resolve));
    }
});

test('SSRF proxy bounds decoded response bytes through HTTPS CONNECT tunnels', async () => {
    const directory = mkdtempSync(join(tmpdir(), 'crawlerx-test-tls-'));
    const keyPath = join(directory, 'key.pem');
    const certificatePath = join(directory, 'certificate.pem');
    execFileSync('openssl', [
        'req', '-x509', '-newkey', 'rsa:2048', '-nodes',
        '-keyout', keyPath,
        '-out', certificatePath,
        '-days', '1',
        '-subj', '/CN=fixture.example',
        '-addext', 'subjectAltName=DNS:fixture.example',
    ], { stdio: 'ignore' });
    const certificate = readFileSync(certificatePath);
    const originalCa = httpsAgent.options.ca;
    httpsAgent.options.ca = certificate;
    const compressed = gzipSync(Buffer.alloc(2_000_000, 'x'));
    const upstream = createHttpsServer({ key: readFileSync(keyPath), cert: certificate }, (_request, response) => {
        response.writeHead(200, {
            'content-encoding': 'gzip',
            'content-length': compressed.length,
        });
        response.end(compressed);
    });
    await new Promise((resolve, reject) => {
        upstream.once('error', reject);
        upstream.listen(0, '127.0.0.1', resolve);
    });

    const upstreamPort = upstream.address().port;
    const allowFixture = Object.assign(async () => {}, {
        resolve: async (value) => ({
            target: new URL(value),
            hostname: new URL(value).hostname,
            addresses: [{ address: '127.0.0.1' }],
        }),
    });
    const proxy = await createSsrfProxyServer({ assertSafeUrl: allowFixture, maxResponseBytes: 4 });

    try {
        const proxyUrl = new URL(proxy.url);
        const client = connect(Number(proxyUrl.port), proxyUrl.hostname);
        client.on('error', () => {});
        client.write(`CONNECT fixture.example:${upstreamPort} HTTP/1.1\r\nHost: fixture.example:${upstreamPort}\r\n\r\n`);
        await readHeaders(client);
        const secureClient = tlsConnect({
            socket: client,
            servername: 'fixture.example',
            rejectUnauthorized: false,
            ALPNProtocols: ['http/1.1'],
        });
        secureClient.on('error', () => {});
        await new Promise((resolve, reject) => {
            secureClient.once('secureConnect', resolve);
            secureClient.once('error', reject);
        });
        const responseChunks = [];
        secureClient.on('data', (chunk) => responseChunks.push(chunk));
        secureClient.write(`GET /compressed HTTP/1.1\r\nHost: fixture.example:${upstreamPort}\r\nAccept-Encoding: gzip\r\nConnection: close\r\n\r\n`);
        await new Promise((resolve) => secureClient.once('close', resolve));

        assert.equal(proxy.wasResponseTooLarge(), true);
        const received = Buffer.concat(responseChunks);
        const bodyStart = received.indexOf(Buffer.from('\r\n\r\n')) + 4;
        assert.ok(received.length < 65_536 + 4096);
        assert.ok(bodyStart > 3 || received.length === 0, received.toString('latin1'));
    } finally {
        await proxy.close();
        await new Promise((resolve) => upstream.close(resolve));
        httpsAgent.options.ca = originalCa;
        rmSync(directory, { recursive: true, force: true });
    }
});

function readHeaders(socket) {
    return new Promise((resolve, reject) => {
        let received = Buffer.alloc(0);
        const onData = (chunk) => {
            received = Buffer.concat([received, chunk]);
            const end = received.indexOf(Buffer.from('\r\n\r\n'));
            if (end === -1) return;
            socket.removeListener('data', onData);
            socket.pause();
            const remaining = received.subarray(end + 4);
            if (remaining.length > 0) socket.unshift(remaining);
            socket.resume();
            resolve(received.subarray(0, end + 4).toString('latin1'));
        };
        socket.on('data', onData);
        socket.once('error', reject);
    });
}

test('both browser engines reject oversized decoded HTTPS documents and subresources', async (t) => {
    if (!existsSync(chromium.executablePath())) {
        t.skip('The Playwright Chromium executable is not installed');
        return;
    }
    const host = privateInterfaceAddress();
    if (host === null) {
        t.skip('No non-loopback IPv4 address is available for the local browser fixture');
        return;
    }

    const directory = mkdtempSync(join(tmpdir(), 'crawlerx-browser-tls-'));
    const keyPath = join(directory, 'key.pem');
    const certificatePath = join(directory, 'certificate.pem');
    execFileSync('openssl', [
        'req', '-x509', '-newkey', 'rsa:2048', '-nodes',
        '-keyout', keyPath,
        '-out', certificatePath,
        '-days', '1',
        '-subj', `/CN=${host}`,
        '-addext', `subjectAltName=IP:${host}`,
    ], { stdio: 'ignore' });
    const largeDocument = gzipSync(Buffer.alloc(1_000_000, 'x'));
    const largeScript = gzipSync(Buffer.alloc(1_000_000, 'x'));
    const server = createHttpsServer({ key: readFileSync(keyPath), cert: readFileSync(certificatePath) }, (request, response) => {
        if (request.url === '/large-document') {
            response.writeHead(200, {
                'content-encoding': 'gzip',
                'content-length': largeDocument.length,
                'content-type': 'text/html',
            });
            response.end(largeDocument);
            return;
        }
        if (request.url === '/large-script.js') {
            response.writeHead(200, {
                'content-encoding': 'gzip',
                'content-length': largeScript.length,
                'content-type': 'application/javascript',
            });
            response.end(largeScript);
            return;
        }
        response.writeHead(200, { 'content-type': 'text/html' });
        response.end('<html><body><script src="/large-script.js"></script><p>small page</p></body></html>');
    });
    await new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(0, '0.0.0.0', resolve);
    });
    const service = await startBrowserService(host, certificatePath);

    try {
        for (const script of ['playwright', 'puppeteer']) {
            for (const path of ['/large-document', '/page']) {
                const { payload, result } = await fetchBrowser(service, {
                    url: `https://${host}:${server.address().port}${path}`,
                    maxResponseBytes: 65_536,
                    blockResources: false,
                    navigationTimeoutMs: 10000,
                    waitMs: 100,
                }, script);

                assert.equal(payload.exitCode, 1, `${script} ${path}: ${JSON.stringify(payload)}`);
                assert.equal(result.error, 'response_too_large', `${script} ${path}: ${JSON.stringify(result)}`);
            }
        }
    } finally {
        await service.stop();
        await new Promise((resolve, reject) => server.close((error) => error ? reject(error) : resolve()));
        rmSync(directory, { recursive: true, force: true });
    }
});

test('both browser engines reject oversized decoded HTTP documents and subresources', async (t) => {
    if (!existsSync(chromium.executablePath())) {
        t.skip('The Playwright Chromium executable is not installed');
        return;
    }
    const host = privateInterfaceAddress();
    if (host === null) {
        t.skip('No non-loopback IPv4 address is available for the local browser fixture');
        return;
    }

    const largeDocument = gzipSync(Buffer.alloc(1_000_000, 'x'));
    const largeScript = gzipSync(Buffer.alloc(1_000_000, 'x'));
    const server = createServer((request, response) => {
        if (request.url === '/large-document') {
            response.writeHead(200, {
                'content-encoding': 'gzip',
                'content-length': largeDocument.length,
                'content-type': 'text/html',
            });
            response.end(largeDocument);
            return;
        }
        if (request.url === '/large-script.js') {
            response.writeHead(200, {
                'content-encoding': 'gzip',
                'content-length': largeScript.length,
                'content-type': 'application/javascript',
            });
            response.end(largeScript);
            return;
        }
        response.writeHead(200, { 'content-type': 'text/html' });
        response.end('<html><body><script src="/large-script.js"></script><p>small page</p></body></html>');
    });
    await new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(0, '0.0.0.0', resolve);
    });
    const service = await startBrowserService(host);

    try {
        for (const script of ['playwright', 'puppeteer']) {
            for (const path of ['/large-document', '/page']) {
                const { payload, result } = await fetchBrowser(service, {
                    url: `http://${host}:${server.address().port}${path}`,
                    maxResponseBytes: 65_536,
                    blockResources: false,
                    navigationTimeoutMs: 10000,
                    waitMs: 100,
                }, script);

                assert.equal(payload.exitCode, 1, `${script} ${path}: ${JSON.stringify(payload)}`);
                assert.equal(result.error, 'response_too_large', `${script} ${path}: ${JSON.stringify(result)}`);
            }
        }
    } finally {
        await service.stop();
        await new Promise((resolve, reject) => server.close((error) => error ? reject(error) : resolve()));
    }
});

function privateInterfaceAddress() {
    for (const entries of Object.values(networkInterfaces())) {
        for (const entry of entries ?? []) {
            if (entry.family === 'IPv4' && !entry.internal && !entry.address.startsWith('127.')) {
                return entry.address;
            }
        }
    }

    return null;
}

async function startBrowserService(host, certificatePath) {
    const port = await availablePort();
    const stderr = [];
    const child = spawn(process.execPath, ['scripts/fetch-server.mjs'], {
        cwd: root,
        env: {
            ...process.env,
            PLAYWRIGHT_HOST: '127.0.0.1',
            PLAYWRIGHT_PORT: String(port),
            CRAWLERX_BROWSER_MAX_CONCURRENCY: '1',
            CRAWLERX_FETCH_LAB: '1',
            CRAWLERX_BROWSER_ALLOW_PRIVATE_IPS: '1',
            CRAWLERX_BROWSER_ALLOWED_HOSTS: host,
            ...(certificatePath ? { NODE_EXTRA_CA_CERTS: certificatePath } : {}),
        },
        stdio: ['ignore', 'ignore', 'pipe'],
    });
    child.stderr.setEncoding('utf8');
    child.stderr.on('data', (chunk) => stderr.push(chunk));
    try {
        await waitFor(() => child.exitCode !== null || child.signalCode !== null || stderr.join('').includes(`listening on ${port}`));
        if (child.exitCode !== null || child.signalCode !== null) {
            throw new Error(`Browser service exited before listening: ${stderr.join('')}`);
        }
    } catch (error) {
        child.kill('SIGTERM');
        throw error;
    }

    return {
        port,
        async stop() {
            if (child.exitCode !== null || child.signalCode !== null) return;
            child.kill('SIGTERM');
            await new Promise((resolve) => {
                const timer = setTimeout(() => {
                    child.kill('SIGKILL');
                    resolve();
                }, 5000);
                child.once('close', () => {
                    clearTimeout(timer);
                    resolve();
                });
            });
        },
    };
}

async function availablePort() {
    const server = createTcpServer();
    await new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(0, '127.0.0.1', resolve);
    });
    const port = server.address().port;
    await new Promise((resolve, reject) => server.close((error) => error ? reject(error) : resolve()));

    return port;
}

async function fetchBrowser(service, config, script = 'playwright') {
    const response = await fetch(`http://127.0.0.1:${service.port}/fetch`, {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ script, config }),
    });
    const payload = await response.json();

    return { payload, result: payload.stdout ? JSON.parse(payload.stdout) : null };
}

async function waitFor(predicate, timeoutMs = 10000) {
    const deadline = Date.now() + timeoutMs;
    while (Date.now() < deadline) {
        if (predicate()) return;
        await new Promise((resolve) => setTimeout(resolve, 25));
    }
    throw new Error('Timed out waiting for browser service');
}

test('SSRF proxy bounds a regular HTTP response to a browser upgrade request', async () => {
    const upstream = createServer((_request, response) => {
        response.writeHead(200);
        response.write('abc');
        response.end('def');
    });
    await new Promise((resolve, reject) => {
        upstream.once('error', reject);
        upstream.listen(0, '127.0.0.1', resolve);
    });

    const upstreamPort = upstream.address().port;
    const allowFixture = Object.assign(async () => {}, {
        resolve: async (value) => ({
            target: new URL(value),
            hostname: '127.0.0.1',
            addresses: [{ address: '127.0.0.1' }],
        }),
    });
    const proxy = await createSsrfProxyServer({ assertSafeUrl: allowFixture, maxResponseBytes: 4 });

    try {
        const proxyUrl = new URL(proxy.url);
        const client = connect(Number(proxyUrl.port), proxyUrl.hostname);
        client.on('error', () => {});
        client.write([
            `GET ws://127.0.0.1:${upstreamPort}/upgrade HTTP/1.1`,
            `Host: 127.0.0.1:${upstreamPort}`,
            'Connection: Upgrade',
            'Upgrade: websocket',
            '',
            '',
        ].join('\r\n'));
        await new Promise((resolve, reject) => {
            const deadline = Date.now() + 2000;
            const waitForLimit = () => {
                if (proxy.wasResponseTooLarge()) {
                    resolve();
                    return;
                }
                if (Date.now() >= deadline) {
                    reject(new Error('upgrade fallback response did not hit the configured byte limit.'));
                    return;
                }
                setTimeout(waitForLimit, 10);
            };
            waitForLimit();
        });

        assert.equal(proxy.wasResponseTooLarge(), true);
    } finally {
        await proxy.close();
        await new Promise((resolve) => upstream.close(resolve));
    }
});

test('SSRF proxy strips credentials from cross-origin websocket upgrades', async () => {
    let authorization = null;
    const upstream = createServer((request, response) => {
        authorization = request.headers.authorization ?? null;
        response.writeHead(200);
        response.end('upgrade fallback');
    });
    await new Promise((resolve, reject) => {
        upstream.once('error', reject);
        upstream.listen(0, '127.0.0.1', resolve);
    });

    const upstreamPort = upstream.address().port;
    const allowFixture = Object.assign(async () => {}, {
        resolve: async (value) => ({
            target: new URL(value),
            hostname: '127.0.0.1',
            addresses: [{ address: '127.0.0.1' }],
        }),
    });
    const proxy = await createSsrfProxyServer({
        assertSafeUrl: allowFixture,
        requestOrigin: 'http://source.example',
        sensitiveHeaders: ['authorization'],
    });

    try {
        const proxyUrl = new URL(proxy.url);
        const client = connect(Number(proxyUrl.port), proxyUrl.hostname);
        const responsePromise = new Promise((resolve, reject) => {
            let response = '';
            client.setEncoding('latin1');
            client.on('data', (chunk) => {
                response += chunk;
                if (response.includes('upgrade fallback')) resolve(response);
            });
            client.once('error', reject);
        });
        client.write([
            `GET ws://127.0.0.1:${upstreamPort}/socket HTTP/1.1`,
            `Host: 127.0.0.1:${upstreamPort}`,
            'Connection: Upgrade',
            'Upgrade: websocket',
            'Authorization: Bearer private-token',
            'Cookie: session=alice',
            '',
            '',
        ].join('\r\n'));
        await responsePromise;

        assert.equal(authorization, null);
    } finally {
        await proxy.close();
        await new Promise((resolve) => upstream.close(resolve));
    }
});
