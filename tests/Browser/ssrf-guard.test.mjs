import { test } from 'node:test';
import assert from 'node:assert/strict';
import { request as httpRequest } from 'node:http';
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

test('SSRF guard rejects DNS names with any private answer', async () => {
    const assertSafeUrl = guardFor(['93.184.216.34', '10.10.0.1']);
    await assert.rejects(assertSafeUrl('https://rebound.example/'), SsrfBlockedError);
});

test('SSRF guard fails closed when hostname resolution fails', async () => {
    const assertSafeUrl = createSsrfGuard({ lookupHost: async () => { throw new Error('NXDOMAIN'); } });
    await assert.rejects(assertSafeUrl('https://missing.example/'), SsrfBlockedError);
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
