import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createServer, request as httpRequest } from 'node:http';
import { gzipSync } from 'node:zlib';
import { createSsrfProxyServer } from '../../scripts/ssrf-proxy.mjs';
import { SsrfBlockedError } from '../../scripts/ssrf-guard.mjs';

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

function requestThroughProxy(proxy, url) {
    const proxyUrl = new URL(proxy.url);
    return new Promise((resolve, reject) => {
        const request = httpRequest({
            hostname: proxyUrl.hostname,
            port: Number(proxyUrl.port),
            path: url,
            headers: { host: new URL(url).host },
        }, (response) => {
            const chunks = [];
            response.on('data', (chunk) => chunks.push(chunk));
            response.once('end', () => resolve({
                status: response.statusCode,
                headers: response.headers,
                body: Buffer.concat(chunks).toString('utf8'),
            }));
        });
        request.once('error', reject);
        request.end();
    });
}

async function followRedirectsThroughProxy(proxy, startUrl) {
    let url = startUrl;
    for (let count = 0; count <= 5; count += 1) {
        const response = await requestThroughProxy(proxy, url);
        const location = response.headers.location;
        if (response.status < 300 || response.status >= 400 || typeof location !== 'string') return response;
        url = new URL(location, url).href;
    }
    throw new Error('The test redirect limit was exceeded.');
}

test('browser egress checks redirected paths against robots before forwarding them', async () => {
    const requestedPaths = [];
    const origin = createServer((request, response) => {
        requestedPaths.push(request.url);
        if (request.url === '/robots.txt') {
            response.writeHead(200, {
                'Content-Type': 'text/plain',
                'Content-Encoding': 'gzip',
            });
            response.end(gzipSync('User-agent: *\nDisallow: /private\n'));
            return;
        }
        if (request.url === '/start') {
            response.writeHead(302, { Location: '/private/page' });
            response.end();
            return;
        }
        response.writeHead(200, { 'Content-Type': 'text/html' });
        response.end('<html><body>private page</body></html>');
    });
    await listen(origin);
    const targetUrl = `http://browser-origin.invalid:${origin.address().port}/start`;
    const proxy = await createSsrfProxyServer({
        assertSafeUrl: allowLocalTargets(),
        requestOrigin: new URL(targetUrl).origin,
        respectRobots: true,
        robotsUserAgent: 'CrawlerX test',
    });

    try {
        const response = await followRedirectsThroughProxy(proxy, targetUrl);

        assert.equal(response.status, 403);
        assert.equal(response.body, 'robots_disallowed');
        assert.deepEqual(requestedPaths, ['/robots.txt', '/start']);
        assert.equal(proxy.wasRobotsBlocked(), true);
        assert.equal(proxy.robotsBlockedUrl(), `${new URL(targetUrl).origin}/private/page`);
    } finally {
        await proxy.close();
        await close(origin);
    }
});

test('robots redirects are SSRF-checked before the redirect target is contacted', async () => {
    const requestedPaths = [];
    const origin = createServer((request, response) => {
        requestedPaths.push(request.url);
        if (request.url === '/robots.txt') {
            response.writeHead(302, { Location: `http://private-target.invalid:${origin.address().port}/robots.txt` });
            response.end();
            return;
        }
        response.writeHead(200, { 'Content-Type': 'text/html' });
        response.end('<html><body>must not be requested</body></html>');
    });
    await listen(origin);
    const targetUrl = `http://browser-origin.invalid:${origin.address().port}/page`;
    const safeTargets = Object.assign(async () => {}, {
        resolve: async (value) => {
            const target = new URL(value);
            if (target.hostname === 'private-target.invalid') {
                throw new SsrfBlockedError({ reason: 'private_address', hostname: target.hostname });
            }
            return { target, hostname: target.hostname, addresses: [{ address: '127.0.0.1' }] };
        },
    });
    const proxy = await createSsrfProxyServer({
        assertSafeUrl: safeTargets,
        requestOrigin: new URL(targetUrl).origin,
        respectRobots: true,
    });

    try {
        const response = await requestThroughProxy(proxy, targetUrl);

        assert.equal(response.status, 403);
        assert.equal(response.body, 'ssrf_blocked');
        assert.deepEqual(requestedPaths, ['/robots.txt']);
        assert.equal(proxy.wasBlocked(), true);
    } finally {
        await proxy.close();
        await close(origin);
    }
});
