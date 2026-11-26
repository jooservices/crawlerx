import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createSsrfGuard, lookupAll, readIpv6GraceMs } from '../../scripts/ssrf-guard.mjs';

// A resolver double: `answers` maps a family (4 or 6) to an array of addresses,
// an Error to reject with, or 'never' for a name server that drops the question.
// A { after, value } entry answers `after` ms later.
function resolver(answers) {
    const calls = [];
    const resolve = async (hostname, options) => {
        calls.push({ hostname, family: options.family });
        const answer = answers[options.family];
        if (answer === 'never') {
            return new Promise(() => {});
        }
        if (answer?.after !== undefined) {
            await new Promise((done) => setTimeout(done, answer.after));
            return settle(answer.value, options.family);
        }
        return settle(answer, options.family);
    };
    resolve.calls = calls;
    return resolve;
}

function settle(value, family) {
    if (value instanceof Error) {
        throw value;
    }
    return (value ?? []).map((address) => ({ address, family }));
}

const addresses = (entries) => entries.map(({ address }) => address);

test('AC1 resolves from IPv4 within the grace period when the IPv6 question is never answered', async () => {
    const resolve = resolver({ 4: ['93.184.216.34'], 6: 'never' });
    const started = Date.now();

    const result = await lookupAll('venus-av.com', { resolve, graceMs: 50 });

    assert.deepEqual(addresses(result), ['93.184.216.34']);
    assert.ok(Date.now() - started < 500, 'must not wait for the unanswered IPv6 lookup');
    assert.deepEqual(resolve.calls.map(({ family }) => family).sort(), [4, 6]);
});

test('AC2 returns IPv4 first and IPv6 after it when both answer in time', async () => {
    const resolve = resolver({ 4: ['93.184.216.35', '93.184.216.36'], 6: ['2606:4700::7'] });

    const result = await lookupAll('example.com', { resolve, graceMs: 200 });

    assert.deepEqual(addresses(result), ['93.184.216.35', '93.184.216.36', '2606:4700::7']);
});

test('AC2 keeps an IPv6 answer that arrives inside the grace period', async () => {
    const resolve = resolver({ 4: ['93.184.216.35'], 6: { after: 30, value: ['2606:4700::7'] } });

    const result = await lookupAll('example.com', { resolve, graceMs: 400 });

    assert.deepEqual(addresses(result), ['93.184.216.35', '2606:4700::7']);
});

test('AC3 waits for the IPv6 lookup and uses it when the IPv4 lookup is empty', async () => {
    const resolve = resolver({ 4: [], 6: { after: 80, value: ['2606:4700::9'] } });

    const result = await lookupAll('ipv6-only.example', { resolve, graceMs: 10 });

    assert.deepEqual(addresses(result), ['2606:4700::9']);
});

test('AC3 waits for the IPv6 lookup and uses it when the IPv4 lookup fails', async () => {
    const resolve = resolver({ 4: Object.assign(new Error('ENODATA'), { code: 'ENODATA' }), 6: ['2606:4700::9'] });

    const result = await lookupAll('ipv6-only.example', { resolve, graceMs: 10 });

    assert.deepEqual(addresses(result), ['2606:4700::9']);
});

test('AC3 rejects with the IPv4 error when both lookups fail', async () => {
    const failure = Object.assign(new Error('ENOTFOUND'), { code: 'ENOTFOUND' });
    const resolve = resolver({ 4: failure, 6: new Error('ENODATA') });

    await assert.rejects(lookupAll('missing.example', { resolve, graceMs: 10 }), (error) => error === failure);
});

test('AC3 the guard still reports dns_failed when both lookups fail', async () => {
    const resolve = resolver({ 4: new Error('ENOTFOUND'), 6: new Error('ENOTFOUND') });
    const guard = createSsrfGuard({ lookupHost: (host) => lookupAll(host, { resolve, graceMs: 10 }) });

    await assert.rejects(guard('https://missing.example/'), (error) => error.reason === 'dns_failed');
});

test('AC4 a late IPv6 answer after the result was returned raises nothing and changes nothing', async () => {
    const unhandled = [];
    const onUnhandled = (reason) => unhandled.push(reason);
    process.on('unhandledRejection', onUnhandled);
    try {
        const resolve = resolver({ 4: ['93.184.216.34'], 6: { after: 120, value: new Error('late failure') } });

        const result = await lookupAll('venus-av.com', { resolve, graceMs: 20 });
        await new Promise((done) => setTimeout(done, 300));

        assert.deepEqual(addresses(result), ['93.184.216.34']);
        assert.deepEqual(unhandled, []);
    } finally {
        process.off('unhandledRejection', onUnhandled);
    }
});

test('AC4 a late IPv6 success after the result was returned does not change the result', async () => {
    const resolve = resolver({ 4: ['93.184.216.34'], 6: { after: 100, value: ['2606:4700::1'] } });

    const result = await lookupAll('venus-av.com', { resolve, graceMs: 20 });
    await new Promise((done) => setTimeout(done, 250));

    assert.deepEqual(addresses(result), ['93.184.216.34']);
});

test('AC5 reads the grace period from CRAWLERX_DNS_IPV6_GRACE_MS with a safe default', () => {
    const read = (value) => readIpv6GraceMs(value === undefined ? {} : { CRAWLERX_DNS_IPV6_GRACE_MS: value });

    assert.equal(read(undefined), 1000);
    assert.equal(read('0'), 0);
    assert.equal(read('250'), 250);
    assert.equal(read('abc'), 1000);
    assert.equal(read('-5'), 1000);
    assert.equal(read('1.5'), 1000);
    assert.equal(read(''), 1000);
});

test('AC6 the guard still drops loopback and private answers of both families', async () => {
    const resolve = resolver({
        4: ['127.0.0.1', '0.0.0.0', '104.20.42.172'],
        6: ['::1', 'fd00::1', '2606:4700::6810:2aac'],
    });
    const guard = createSsrfGuard({ lookupHost: (host) => lookupAll(host, { resolve, graceMs: 200 }) });

    const resolved = await guard.resolve('https://en.jable.tv/models/example/');

    assert.deepEqual(addresses(resolved.addresses), ['104.20.42.172', '2606:4700::6810:2aac']);
});

test('AC6 the guard rejects a host whose every answer is private', async () => {
    const resolve = resolver({ 4: ['10.0.0.5'], 6: ['fd00::5'] });
    const guard = createSsrfGuard({ lookupHost: (host) => lookupAll(host, { resolve, graceMs: 50 }) });

    await assert.rejects(guard('https://internal.example/'), (error) => error.name === 'SsrfBlockedError');
});

test('AC6 a blocked IPv4 decoy does not start the grace period, so a slower public IPv6 answer is kept', async () => {
    const resolve = resolver({ 4: ['0.0.0.0', '127.0.0.1', '10.1.2.3'], 6: { after: 120, value: ['2606:4700::6810:2aac'] } });
    const guard = createSsrfGuard({ lookupHost: (host) => lookupAll(host, { resolve, graceMs: 20 }) });

    const resolved = await guard.resolve('https://decoy.example/');

    assert.deepEqual(addresses(resolved.addresses), ['2606:4700::6810:2aac']);
});

test('AC6 a private IPv4 answer counts as usable when private addresses are allowed (fetch lab)', async () => {
    const resolve = resolver({ 4: ['10.1.2.3'], 6: { after: 120, value: ['2606:4700::6810:2aac'] } });

    const result = await lookupAll('fixture-site', { resolve, graceMs: 20, allowPrivate: true });

    assert.deepEqual(addresses(result), ['10.1.2.3']);
});

test('AC1 starts the IPv6 lookup in the same tick as the IPv4 lookup, not after it settles', async () => {
    const resolve = resolver({ 4: { after: 100, value: ['93.184.216.34'] }, 6: ['2606:4700::1'] });

    const pending = lookupAll('venus-av.com', { resolve, graceMs: 200 });

    assert.deepEqual(resolve.calls.map(({ family }) => family), [4, 6], 'both lookups must be started before any answer');
    assert.deepEqual(addresses(await pending), ['93.184.216.34', '2606:4700::1']);
});
