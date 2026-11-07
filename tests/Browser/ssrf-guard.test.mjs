import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createServer, request as httpRequest } from 'node:http';
import { createSsrfGuard, SsrfBlockedError } from '../../scripts/ssrf-guard.mjs';
import { createSsrfProxyServer, pinnedLookup } from '../../scripts/ssrf-proxy.mjs';

function guardFor(addresses, options = {}) {
    return createSsrfGuard({
        ...options,
        lookupHost: async () => addresses.map((address) => ({ address })),
    });
}

test('SSRF guard allows a public host when DNS returns only public addresses', async () => {
    const assertSafeUrl = guardFor(['93.184.216.34']);
    await assert.doesNotReject(assertSafeUrl('https://example.com/movie'));
});

test('SSRF guard allows public Cloudflare addresses mixed with loopback decoy records', async () => {
    // Production input from JOOXCR-399 / JOOXCR-363: en.jable.tv answers include
    // 127.0.0.1 and 0.0.0.0 next to real Cloudflare A records.
    const assertSafeUrl = guardFor(['127.0.0.1', '0.0.0.0', '104.20.42.172', '172.66.167.218']);
    const resolved = await assertSafeUrl.resolve('https://en.jable.tv/models/example/');
    assert.deepEqual(
        resolved.addresses.map(({ address }) => address).sort(),
        ['104.20.42.172', '172.66.167.218'],
    );
});

test('SSRF proxy lookup uses only the public pins and does not resolve DNS again', async () => {
    let lookupCalls = 0;
    const assertSafeUrl = createSsrfGuard({
        lookupHost: async () => {
            lookupCalls += 1;
            return ['127.0.0.1', '104.20.42.172', '0.0.0.0', '172.66.167.218']
                .map((address) => ({ address }));
        },
    });
    const resolved = await assertSafeUrl.resolve('https://decoys.example/movie');
    const connectionAddresses = await new Promise((resolve, reject) => {
        pinnedLookup(resolved.addresses)(resolved.hostname, { all: true }, (error, addresses) => {
            if (error) {
                reject(error);
                return;
            }
            resolve(addresses);
        });
    });

    assert.deepEqual(connectionAddresses, [
        { address: '104.20.42.172', family: 4 },
        { address: '172.66.167.218', family: 4 },
    ]);
    assert.equal(lookupCalls, 1);
});

test('SSRF guard allows missav-style public answers and still drops unspecified decoys', async () => {
    const assertSafeUrl = guardFor(['104.20.31.186', '172.66.167.65', '0.0.0.0', '127.0.0.1']);
    const resolved = await assertSafeUrl.resolve('https://missav.ws/en/new');
    assert.deepEqual(
        resolved.addresses.map(({ address }) => address).sort(),
        ['104.20.31.186', '172.66.167.65'],
    );
});

test('SSRF guard keeps loopback, link-local, and metadata addresses blocked in the lab', async () => {
    for (const address of ['127.0.0.1', '169.254.169.254', '::1', 'fe80::1', 'fd00:ec2::254', '2001:db8::1']) {
        const assertSafeUrl = guardFor([address], { allowPrivate: true });
        await assert.rejects(
            assertSafeUrl(`http://${address.includes(':') ? `[${address}]` : address}/`),
            SsrfBlockedError,
            `address should stay blocked in lab: ${address}`,
        );
    }
});

test('SSRF guard rejects IPv4-mapped loopback, private, and metadata addresses', async () => {
    for (const { address, url } of [
        { address: '::ffff:127.0.0.1', url: 'http://[::ffff:7f00:1]/' },
        { address: '::ffff:10.0.0.1', url: 'http://[::ffff:a00:1]/' },
        { address: '::ffff:169.254.169.254', url: 'http://[::ffff:a9fe:a9fe]/' },
    ]) {
        const assertSafeUrl = guardFor([address]);
        await assert.rejects(
            assertSafeUrl(url),
            SsrfBlockedError,
            `IPv4-mapped address should be blocked: ${address}`,
        );
    }
});

test('SSRF guard allows RFC1918 and ULA only with the lab option', async () => {
    for (const address of ['10.0.0.1', '172.16.1.1', '192.168.1.1', 'fc00::1']) {
        const target = `http://${address.includes(':') ? `[${address}]` : address}/`;
        await assert.rejects(guardFor([address])(target), SsrfBlockedError);
        await assert.doesNotReject(guardFor([address], { allowPrivate: true })(target));
    }
});

test('SSRF guard blocks hosts whose DNS answers are only private or special-use', async () => {
    for (const addresses of [
        ['10.0.0.1'],
        ['172.16.1.1'],
        ['192.168.1.1'],
        ['127.0.0.1', '::1'],
        ['169.254.169.254'],
        ['100.64.0.1'],
        ['100.127.255.254'],
        ['198.18.0.1'],
        ['198.19.255.254'],
        ['::'],
        ['::1'],
        ['::ffff:127.0.0.1'],
        ['::ffff:192.168.1.1'],
        ['::ffff:169.254.169.254'],
        ['fc00::1'],
        ['fdff:ffff::1'],
        ['fe80::1'],
        ['febf:ffff::1'],
        ['fd00:ec2::254'],
        ['0.0.0.0', '127.0.0.1'],
    ]) {
        const assertSafeUrl = guardFor(addresses);
        await assert.rejects(
            assertSafeUrl('https://sinkhole.example/'),
            (error) => error instanceof SsrfBlockedError
                && error.reason === 'no_public_address'
                && typeof error.address === 'string',
            `expected no_public_address for ${addresses.join(',')}`,
        );
    }
});

test('SSRF guard fails closed when hostname resolution fails', async () => {
    const assertSafeUrl = createSsrfGuard({ lookupHost: async () => { throw new Error('NXDOMAIN'); } });
    await assert.rejects(
        assertSafeUrl('https://missing.example/'),
        (error) => error instanceof SsrfBlockedError && error.reason === 'dns_failed',
    );
});

test('SSRF guard re-checks a second resolution in the DNS-rebinding style', async () => {
    let calls = 0;
    const assertSafeUrl = createSsrfGuard({
        lookupHost: async () => {
            calls += 1;
            return calls === 1
                ? [{ address: '93.184.216.34' }]
                : [{ address: '127.0.0.1' }];
        },
    });
    await assert.doesNotReject(assertSafeUrl('https://rebind.example/'));
    await assert.rejects(
        assertSafeUrl('https://rebind.example/'),
        (error) => error instanceof SsrfBlockedError && error.reason === 'no_public_address' && error.address === '127.0.0.1',
    );
});

test('host allowlist supports exact and wildcard hosts without matching the wildcard apex', async () => {
    const assertSafeUrl = guardFor(['93.184.216.34'], { allowedHosts: 'media.example,*.cdn.example' });
    await assert.doesNotReject(assertSafeUrl('https://media.example/'));
    await assert.doesNotReject(assertSafeUrl('https://img.cdn.example/'));
    await assert.rejects(assertSafeUrl('https://cdn.example/'), SsrfBlockedError);
    await assert.rejects(assertSafeUrl('https://other.example/'), SsrfBlockedError);
});

test('host allowlist never overrides address checks and rejects malformed entries', async () => {
    await assert.rejects(guardFor(['127.0.0.1'], { allowedHosts: '*.example' })('http://private.example/'), SsrfBlockedError);
    assert.throws(() => createSsrfGuard({ allowedHosts: 'https://example.com/path' }), /Invalid CRAWLERX_BROWSER_ALLOWED_HOSTS/);
});

test('SSRF guard rejects numeric IP encodings, userinfo tricks, and non-network protocols', async () => {
    const assertSafeUrl = guardFor(['93.184.216.34']);
    for (const url of [
        'http://2130706433/',
        'http://0177.0.0.1/',
        'http://0x7f000001/',
        'http://127.1/',
        'https://user:pass@example.com/',
        'https://127.0.0.1@example.com/',
        'https://example.com@127.0.0.1/',
    ]) {
        await assert.rejects(assertSafeUrl(url), SsrfBlockedError, `URL should be rejected: ${url}`);
    }
    await assert.rejects(assertSafeUrl('file:///etc/passwd'), SsrfBlockedError);
});

test('SSRF proxy rejects private HTTP targets before opening an outbound connection', async () => {
    const proxy = await createSsrfProxyServer({ assertSafeUrl: createSsrfGuard() });
    try {
        const proxyUrl = new URL(proxy.url);
        for (const target of ['http://10.254.46.2:8080/private-probe', 'http://127.0.0.1/private-probe', 'http://169.254.169.254/latest/meta-data/']) {
            const response = await new Promise((resolve, reject) => {
                const request = httpRequest({
                    hostname: proxyUrl.hostname,
                    port: Number(proxyUrl.port),
                    method: 'GET',
                    path: target,
                }, (incoming) => {
                    incoming.resume();
                    incoming.once('end', () => resolve(incoming));
                });
                request.once('error', reject);
                request.end();
            });
            assert.equal(response.statusCode, 403, `target should be rejected: ${target}`);
        }
        assert.equal(proxy.wasBlocked(), true);
    } finally {
        await proxy.close();
    }
});

test('SSRF proxy allows a decoy-record host and pins only the public address', async () => {
    let upstreamHost = null;
    const upstream = createServer((request, response) => {
        upstreamHost = request.socket.localAddress;
        response.writeHead(200, { 'content-length': '2' });
        response.end('ok');
    });
    await new Promise((resolve, reject) => {
        upstream.once('error', reject);
        upstream.listen(0, '127.0.0.1', resolve);
    });
    const upstreamPort = upstream.address().port;

    const assertSafeUrl = Object.assign(async () => {}, {
        resolve: async (value) => {
            const target = new URL(value);
            return {
                target,
                hostname: target.hostname,
                // Mimic guard output after decoy filtering: only public pin remains.
                addresses: [{ address: '127.0.0.1' }],
            };
        },
    });
    // Lab fixture: the proxy receives already-filtered public pins. Here we use
    // loopback only as the upstream fixture host, which the caller intentionally allows.
    const proxy = await createSsrfProxyServer({ assertSafeUrl });

    try {
        const proxyUrl = new URL(proxy.url);
        const target = new URL(`http://decoy.example:${upstreamPort}/pin`);
        const { body } = await new Promise((resolve, reject) => {
            const request = httpRequest({
                hostname: proxyUrl.hostname,
                port: Number(proxyUrl.port),
                method: 'GET',
                path: target.href,
                headers: { host: target.host },
            }, (incoming) => {
                const chunks = [];
                incoming.on('data', (chunk) => chunks.push(chunk));
                incoming.once('end', () => resolve({ response: incoming, body: Buffer.concat(chunks).toString() }));
            });
            request.once('error', reject);
            request.end();
        });
        assert.equal(body, 'ok');
        assert.ok(upstreamHost === '127.0.0.1' || upstreamHost === '::ffff:127.0.0.1');
    } finally {
        await proxy.close();
        await new Promise((resolve) => upstream.close(() => resolve()));
    }
});

test('SSRF proxy applies the same guard to a redirected request', async () => {
    const server = createServer((_request, response) => {
        response.writeHead(302, { location: 'http://127.0.0.1:1/private' });
        response.end();
    });
    await new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(0, '127.0.0.1', resolve);
    });

    const checkedUrls = [];
    const strictGuard = createSsrfGuard();
    const assertSafeUrl = Object.assign(async (value) => strictGuard(value), {
        resolve: async (value) => {
            checkedUrls.push(value);
            const target = new URL(value);
            if (target.hostname === 'redirect.example') {
                return {
                    target,
                    hostname: target.hostname,
                    addresses: [{ address: '127.0.0.1' }],
                };
            }
            return strictGuard.resolve(value);
        },
    });
    const proxy = await createSsrfProxyServer({ assertSafeUrl });
    const proxyUrl = new URL(proxy.url);
    const startUrl = `http://redirect.example:${server.address().port}/start`;
    const redirectedUrl = 'http://127.0.0.1:1/private';

    const requestThroughProxy = (target) => new Promise((resolve, reject) => {
        const request = httpRequest({
            hostname: proxyUrl.hostname,
            port: Number(proxyUrl.port),
            method: 'GET',
            path: target,
            headers: { host: new URL(target).host },
        }, (response) => {
            response.resume();
            response.once('end', () => resolve(response.statusCode));
        });
        request.once('error', reject);
        request.end();
    });

    try {
        assert.equal(await requestThroughProxy(startUrl), 302);
        assert.equal(await requestThroughProxy(redirectedUrl), 403);
        assert.deepEqual(checkedUrls, [startUrl, redirectedUrl]);
        assert.equal(proxy.wasBlocked(), true);
    } finally {
        await proxy.close();
        await new Promise((resolve) => server.close(() => resolve()));
    }
});

test('SSRF proxy strips fixed and Connection-nominated hop-by-hop headers', async () => {
    let upstreamHeaders;
    const upstream = createServer((request, response) => {
        upstreamHeaders = request.headers;
        response.writeHead(200, {
            connection: 'x-response-hop, keep-alive',
            'keep-alive': 'timeout=5',
            'x-response-hop': 'response-secret',
            'proxy-authenticate': 'Basic realm="test"',
            'content-length': '2',
        });
        response.end('ok');
    });
    await new Promise((resolve, reject) => {
        upstream.once('error', reject);
        upstream.listen(0, '127.0.0.1', resolve);
    });

    const upstreamPort = upstream.address().port;
    const allowLoopbackFixture = Object.assign(async () => {}, {
        resolve: async (value) => ({
            target: new URL(value),
            hostname: new URL(value).hostname,
            addresses: [{ address: '127.0.0.1' }],
        }),
    });
    const proxy = await createSsrfProxyServer({ assertSafeUrl: allowLoopbackFixture });

    try {
        const proxyUrl = new URL(proxy.url);
        const target = new URL(`http://127.0.0.1:${upstreamPort}/hop-by-hop`);
        const { response, body } = await new Promise((resolve, reject) => {
            const request = httpRequest({
                hostname: proxyUrl.hostname,
                port: Number(proxyUrl.port),
                method: 'GET',
                path: target.href,
                headers: {
                    host: target.host,
                    connection: 'x-request-hop, close',
                    'keep-alive': 'timeout=5',
                    'x-request-hop': 'request-secret',
                    'proxy-authorization': 'Basic test-secret',
                    te: 'trailers',
                },
            }, (incoming) => {
                const chunks = [];
                incoming.on('data', (chunk) => chunks.push(chunk));
                incoming.once('end', () => resolve({ response: incoming, body: Buffer.concat(chunks).toString() }));
            });
            request.once('error', reject);
            request.end();
        });

        assert.equal(body, 'ok');
        assert.equal(upstreamHeaders.connection, 'close');
        assert.equal(upstreamHeaders.host, target.host);
        assert.equal(upstreamHeaders['x-request-hop'], undefined);
        assert.equal(upstreamHeaders['keep-alive'], undefined);
        assert.equal(upstreamHeaders['proxy-authorization'], undefined);
        assert.equal(upstreamHeaders.te, undefined);
        assert.equal(response.headers['x-response-hop'], undefined);
        assert.equal(response.headers['keep-alive'], undefined);
        assert.equal(response.headers['proxy-authenticate'], undefined);
    } finally {
        await proxy.close();
        await new Promise((resolve) => upstream.close(() => resolve()));
    }
});
