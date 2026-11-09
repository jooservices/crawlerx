import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createServer, request as httpRequest } from 'node:http';
import { createServer as createTcpServer, connect } from 'node:net';
import { createSsrfProxyServer } from '../../scripts/ssrf-proxy.mjs';

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

test('SSRF proxy bounds response bytes through HTTPS CONNECT tunnels', async () => {
    const upstream = createTcpServer((socket) => socket.write('abcdef'));
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
        client.write(`CONNECT tunnel.example:${upstreamPort} HTTP/1.1\r\nHost: tunnel.example:${upstreamPort}\r\n\r\n`);
        await new Promise((resolve, reject) => {
            const deadline = Date.now() + 2000;
            const waitForLimit = () => {
                if (proxy.wasResponseTooLarge()) {
                    resolve();
                    return;
                }
                if (Date.now() >= deadline) {
                    reject(new Error('CONNECT response did not hit the configured byte limit.'));
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
