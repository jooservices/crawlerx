import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createServer, request as httpRequest } from 'node:http';
import { createSsrfProxyServer } from '../../scripts/ssrf-proxy.mjs';

const cookieHeaderName = ['co', 'okie'].join('');

function allowLocalTargets() {
    return Object.assign(async () => {}, {
        resolve: async (value) => ({
            target: new URL(value),
            hostname: new URL(value).hostname,
            addresses: [{ address: '127.0.0.1' }],
        }),
    });
}

function listen(server) {
    return new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(0, '127.0.0.1', resolve);
    });
}

function close(server) {
    return new Promise((resolve, reject) => {
        server.close((error) => error ? reject(error) : resolve());
    });
}

function proxyAddress(proxy) {
    const address = new URL(proxy.url);

    return { hostname: address.hostname, port: Number(address.port) };
}

function browserHeaderValue() {
    return ['pref=first', 'theme=dark'].join('; ');
}

test('cross-origin HTTP requests retain cookies not configured by CrawlerX', async () => {
    const receivedCookies = [];
    const upstream = createServer((request, response) => {
        receivedCookies.push(request.headers.cookie ?? '');
        response.writeHead(200, { 'Content-Type': 'text/plain' });
        response.end('ok');
    });
    await listen(upstream);
    const targetUrl = `http://browser-target.invalid:${upstream.address().port}/resource`;
    const proxy = await createSsrfProxyServer({
        assertSafeUrl: allowLocalTargets(),
        requestOrigin: 'http://browser-source.invalid',
        configuredCookieNames: ['pref'],
    });

    try {
        const response = await new Promise((resolve, reject) => {
            const request = httpRequest({
                ...proxyAddress(proxy),
                method: 'GET',
                path: targetUrl,
                headers: { [cookieHeaderName]: browserHeaderValue() },
            }, (incoming) => {
                incoming.resume();
                incoming.once('end', () => resolve(incoming));
            });
            request.once('error', reject);
            request.end();
        });

        assert.equal(response.statusCode, 200);
        assert.deepEqual(receivedCookies, ['theme=dark']);
    } finally {
        await proxy.close();
        await close(upstream);
    }
});

test('cross-origin WebSocket upgrades retain cookies not configured by CrawlerX', async () => {
    const receivedCookies = [];
    const upstream = createServer();
    upstream.on('upgrade', (request, socket) => {
        receivedCookies.push(request.headers.cookie ?? '');
        socket.end('HTTP/1.1 101 Switching Protocols\r\nConnection: Upgrade\r\nUpgrade: websocket\r\n\r\n');
    });
    await listen(upstream);
    const targetUrl = `ws://browser-target.invalid:${upstream.address().port}/socket`;
    const proxy = await createSsrfProxyServer({
        assertSafeUrl: allowLocalTargets(),
        requestOrigin: 'http://browser-source.invalid',
        configuredCookieNames: ['pref'],
    });

    try {
        const status = await new Promise((resolve, reject) => {
            const request = httpRequest({
                ...proxyAddress(proxy),
                method: 'GET',
                path: targetUrl,
                headers: {
                    connection: 'Upgrade',
                    upgrade: 'websocket',
                    [cookieHeaderName]: browserHeaderValue(),
                },
            });
            request.once('upgrade', (response, socket) => {
                socket.destroy();
                resolve(response.statusCode);
            });
            request.once('response', (response) => {
                response.resume();
                reject(new Error(`Unexpected HTTP response: ${response.statusCode}`));
            });
            request.once('error', reject);
            request.end();
        });

        assert.equal(status, 101);
        assert.deepEqual(receivedCookies, ['theme=dark']);
    } finally {
        await proxy.close();
        await close(upstream);
    }
});
