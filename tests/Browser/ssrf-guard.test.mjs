import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createServer, request as httpRequest } from 'node:http';
import { createSsrfGuard, SsrfBlockedError } from '../../scripts/ssrf-guard.mjs';
import { createSsrfProxyServer } from '../../scripts/ssrf-proxy.mjs';

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

test('SSRF guard allows missav-style public answers and still drops unspecified decoys', async () => {
    const assertSafeUrl = guardFor(['104.20.31.186', '172.66.167.65', '0.0.0.0', '127.0.0.1']);
    const resolved = await assertSafeUrl.resolve('https://missav.ws/en/new');
    assert.deepEqual(
        resolved.addresses.map(({ address }) => address).sort(),
        ['104.20.31.186', '172.66.167.65'],
    );
});

test('SSRF guard removes private and special-use answers before pinning a mixed DNS result', async () => {
    const assertSafeUrl = guardFor([
        '0.0.0.0',
        '10.0.0.1',
        '100.64.0.1',
        '127.0.0.1',
        '169.254.169.254',
        '172.16.0.1',
        '192.168.0.1',
        '198.18.0.1',
        '::',
        '::1',
        '::a00:1',
        '::ffff:7f00:1',
        'fc00::1',
        'fe80::1',
        'fd00:ec2::254',
        '93.184.216.34',
    ]);
    const resolved = await assertSafeUrl.resolve('https://mixed.example/');

    assert.deepEqual(resolved.addresses, [{ address: '93.184.216.34' }]);
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
        { address: '::ffff:100.64.0.1', url: 'http://[::ffff:6440:1]/' },
        { address: '::ffff:198.18.0.1', url: 'http://[::ffff:c612:1]/' },
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
        ['::'],
        ['::1'],
        ['::a00:1'],
        ['::ffff:127.0.0.1'],
        ['169.254.169.254'],
        ['100.64.0.1'],
        ['198.18.0.1'],
        ['192.0.2.1'],
        ['198.51.100.1'],
        ['203.0.113.1'],
        ['224.0.0.1'],
        ['240.0.0.1'],
        ['fc00::1'],
        ['fe80::1'],
        ['fd00:ec2::254'],
        ['2001:db8::1'],
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

test('SSRF guard rejects decimal, octal, hexadecimal, and shortened IPv4 literals', async () => {
    const assertSafeUrl = guardFor(['93.184.216.34']);
    for (const hostname of ['2130706433', '0177.0.0.1', '0x7f000001', '127.1']) {
        await assert.rejects(
            assertSafeUrl(`http://${hostname}/`),
            SsrfBlockedError,
            `numeric loopback literal should be blocked: ${hostname}`,
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

test('SSRF guard rejects credentials and non-network protocols', async () => {
    const assertSafeUrl = guardFor(['93.184.216.34']);
    await assert.rejects(assertSafeUrl('https://user:pass@example.com/'), SsrfBlockedError);
    await assert.rejects(assertSafeUrl('https://user@example.com/'), SsrfBlockedError);
    await assert.rejects(assertSafeUrl('https://127.0.0.1@example.com/'), SsrfBlockedError);
    await assert.rejects(assertSafeUrl('https://example.com@127.0.0.1/'), SsrfBlockedError);
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

test('SSRF proxy connects to the supplied pin without resolving the target hostname again', async () => {
    const upstream = createServer((request, response) => {
        response.writeHead(200, { 'content-length': '2' });
        response.end('ok');
    });
    await new Promise((resolve, reject) => {
        upstream.once('error', reject);
        upstream.listen(0, '127.0.0.1', resolve);
    });
    const upstreamPort = upstream.address().port;

    let validationCalls = 0;
    const assertSafeUrl = Object.assign(async () => {}, {
        resolve: async (value) => {
            validationCalls += 1;
            const target = new URL(value);
            return {
                target,
                hostname: target.hostname,
                // The local fixture pin isolates the proxy's pinned-lookup behavior.
                addresses: [{ address: '127.0.0.1' }],
            };
        },
    });
    // The filtering behavior is covered by the guard tests above. This hostname
    // is reserved and cannot resolve through ordinary DNS in the test environment.
    const proxy = await createSsrfProxyServer({ assertSafeUrl });

    try {
        const proxyUrl = new URL(proxy.url);
        const target = new URL(`http://unresolvable.invalid:${upstreamPort}/pin`);
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
        assert.equal(validationCalls, 1);
    } finally {
        await proxy.close();
        await new Promise((resolve) => upstream.close(() => resolve()));
    }
});

test('SSRF proxy revalidates redirect targets and preserves the block reason', async () => {
    const redirectServer = createServer((request, response) => {
        response.writeHead(302, { location: 'http://127.0.0.1/private' });
        response.end();
    });
    await new Promise((resolve, reject) => {
        redirectServer.once('error', reject);
        redirectServer.listen(0, '127.0.0.1', resolve);
    });

    const validatedHosts = [];
    const assertSafeUrl = Object.assign(async () => {}, {
        resolve: async (value) => {
            const target = new URL(value);
            validatedHosts.push(target.hostname);
            if (target.hostname === 'redirect.example') {
                return {
                    target,
                    hostname: target.hostname,
                    addresses: [{ address: '127.0.0.1' }],
                };
            }
            throw new SsrfBlockedError({
                reason: 'no_public_address',
                address: '127.0.0.1',
                hostname: target.hostname,
            });
        },
    });
    const proxy = await createSsrfProxyServer({ assertSafeUrl });

    const requestThroughProxy = async (target) => {
        const proxyUrl = new URL(proxy.url);
        return new Promise((resolve, reject) => {
            const request = httpRequest({
                hostname: proxyUrl.hostname,
                port: Number(proxyUrl.port),
                method: 'GET',
                path: target,
            }, (response) => {
                response.resume();
                response.once('end', () => resolve(response));
            });
            request.once('error', reject);
            request.end();
        });
    };

    try {
        const redirect = await requestThroughProxy(`http://redirect.example:${redirectServer.address().port}/start`);
        assert.equal(redirect.statusCode, 302);
        assert.equal(redirect.headers.location, 'http://127.0.0.1/private');

        const blocked = await requestThroughProxy(redirect.headers.location);
        assert.equal(blocked.statusCode, 403);
        assert.deepEqual(validatedHosts, ['redirect.example', '127.0.0.1']);
        assert.equal(proxy.blockedError()?.reason, 'no_public_address');
        assert.equal(proxy.blockedError()?.address, '127.0.0.1');
    } finally {
        await proxy.close();
        await new Promise((resolve) => redirectServer.close(() => resolve()));
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
