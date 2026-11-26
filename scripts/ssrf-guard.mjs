import { lookup } from 'node:dns/promises';
import { networkInterfaces } from 'node:os';
import { BlockList, isIP } from 'node:net';

const alwaysBlocked = new BlockList();
const privateNetworks = new BlockList();

for (const [network, prefix, version] of [
    ['0.0.0.0', 8, 'ipv4'],
    ['100.64.0.0', 10, 'ipv4'],
    ['127.0.0.0', 8, 'ipv4'],
    ['169.254.0.0', 16, 'ipv4'],
    ['192.0.0.0', 24, 'ipv4'],
    ['192.0.2.0', 24, 'ipv4'],
    ['192.88.99.0', 24, 'ipv4'],
    ['198.18.0.0', 15, 'ipv4'],
    ['198.51.100.0', 24, 'ipv4'],
    ['203.0.113.0', 24, 'ipv4'],
    ['224.0.0.0', 4, 'ipv4'],
    ['240.0.0.0', 4, 'ipv4'],
    ['::', 96, 'ipv6'],
    ['::', 128, 'ipv6'],
    ['::1', 128, 'ipv6'],
    ['2001:db8::', 32, 'ipv6'],
    ['64:ff9b::', 96, 'ipv6'],
    ['64:ff9b:1::', 48, 'ipv6'],
    ['100::', 64, 'ipv6'],
    ['2001::', 23, 'ipv6'],
    ['2002::', 16, 'ipv6'],
    ['fd00:ec2::254', 128, 'ipv6'],
    ['fe80::', 10, 'ipv6'],
    ['ff00::', 8, 'ipv6'],
]) {
    alwaysBlocked.addSubnet(network, prefix, version);
}

for (const [network, prefix, version] of [
    ['10.0.0.0', 8, 'ipv4'],
    ['172.16.0.0', 12, 'ipv4'],
    ['192.168.0.0', 16, 'ipv4'],
    ['fc00::', 7, 'ipv6'],
]) {
    privateNetworks.addSubnet(network, prefix, version);
}

const blockedHostnames = new Set([
    'localhost',
    'localhost.localdomain',
    'metadata',
    'metadata.google.internal',
    'metadata.goog',
    'metadata.azure.internal',
    'instance-data.ec2.internal',
    'instance-data',
]);

export class SsrfBlockedError extends Error {
    /**
     * @param {{reason?: string, address?: string|null, hostname?: string|null}} [details]
     */
    constructor({ reason = 'blocked', address = null, hostname = null } = {}) {
        super('ssrf_blocked');
        this.name = 'SsrfBlockedError';
        this.code = 'ssrf_blocked';
        this.reason = reason;
        this.address = address;
        this.hostname = hostname;
    }
}

function normalizeHostname(hostname) {
    const unwrapped = hostname.startsWith('[') && hostname.endsWith(']')
        ? hostname.slice(1, -1)
        : hostname;
    return unwrapped.toLowerCase().replace(/\.$/, '');
}

function parseAllowedHosts(value) {
    if (Array.isArray(value)) {
        return value.map(normalizeAllowedHost);
    }

    if (typeof value !== 'string' || value.trim() === '') {
        return [];
    }

    return value.split(',').map((host) => normalizeAllowedHost(host.trim()));
}

function normalizeAllowedHost(entry) {
    const wildcard = entry.startsWith('*.');
    const candidate = wildcard ? entry.slice(2) : entry;
    let hostname;
    try {
        const parsed = new URL(`http://${candidate}`);
        if (parsed.port !== '' || parsed.pathname !== '/' || parsed.username !== '' || parsed.password !== '') {
            throw new Error('invalid host');
        }
        hostname = normalizeHostname(parsed.hostname);
    } catch {
        throw new Error('Invalid CRAWLERX_BROWSER_ALLOWED_HOSTS entry');
    }

    if (hostname === '' || hostname.includes('*')) {
        throw new Error('Invalid CRAWLERX_BROWSER_ALLOWED_HOSTS entry');
    }

    return wildcard ? `*.${hostname}` : hostname;
}

function hostIsAllowed(hostname, allowedHosts) {
    if (allowedHosts.length === 0) {
        return true;
    }

    return allowedHosts.some((allowed) => {
        if (allowed.startsWith('*.')) {
            const suffix = allowed.slice(1);
            return hostname.endsWith(suffix) && hostname.length > suffix.length;
        }
        return hostname === allowed;
    });
}

function isBlockedAddress(address, allowPrivate) {
    const version = isIP(address);
    if (version === 0) {
        return true;
    }

    const family = version === 4 ? 'ipv4' : 'ipv6';
    if (alwaysBlocked.check(address, family)) {
        return true;
    }

    return !allowPrivate && privateNetworks.check(address, family);
}

/**
 * Create a request policy for the browser sidecar.
 *
 * DNS may include loopback/unspecified decoy records next to real public
 * answers (JOOXCR-363 / JOOXCR-399). Drop every blocked answer and pin only
 * the remaining public addresses. Fail closed when no public address remains.
 *
 * @param {{allowPrivate?: boolean, allowedHosts?: string|string[], lookupHost?: (hostname: string) => Promise<Array<{address: string}>>}} options
 */
export function createSsrfGuard({ allowPrivate = false, allowedHosts = '', lookupHost = (host) => lookupAll(host, { allowPrivate }) } = {}) {
    const hosts = parseAllowedHosts(allowedHosts);

    const resolveSafeUrl = async (value) => {
        let target;
        try {
            target = new URL(value);
        } catch {
            throw new SsrfBlockedError({ reason: 'invalid_url' });
        }

        if (!['http:', 'https:', 'ws:', 'wss:'].includes(target.protocol)
            || target.username !== ''
            || target.password !== '') {
            throw new SsrfBlockedError({ reason: 'invalid_url', hostname: target.hostname || null });
        }

        const hostname = normalizeHostname(target.hostname);
        if (hostname === ''
            || blockedHostnames.has(hostname)
            || hostname.endsWith('.localhost')
            || hostname.endsWith('.local')
            || hostname.endsWith('.internal')) {
            throw new SsrfBlockedError({ reason: 'blocked_hostname', hostname });
        }
        if (!hostIsAllowed(hostname, hosts)) {
            throw new SsrfBlockedError({ reason: 'host_not_allowed', hostname });
        }

        const literalVersion = isIP(hostname);
        let addresses;
        if (literalVersion !== 0) {
            addresses = [{ address: hostname }];
        } else {
            try {
                addresses = await lookupHost(hostname);
            } catch {
                throw new SsrfBlockedError({ reason: 'dns_failed', hostname });
            }
        }

        if (!Array.isArray(addresses) || addresses.length === 0) {
            throw new SsrfBlockedError({ reason: 'dns_failed', hostname });
        }

        const publicAddresses = [];
        let firstBlocked = null;
        for (const entry of addresses) {
            const address = entry?.address;
            if (typeof address !== 'string' || address === '') {
                firstBlocked ??= 'invalid';
                continue;
            }
            if (isBlockedAddress(address, allowPrivate)) {
                firstBlocked ??= address;
                continue;
            }
            publicAddresses.push({ address });
        }

        if (publicAddresses.length === 0) {
            throw new SsrfBlockedError({
                reason: 'no_public_address',
                address: firstBlocked,
                hostname,
            });
        }

        return { target, hostname, addresses: publicAddresses };
    };

    const assertSafeUrl = async (value) => {
        await resolveSafeUrl(value);
    };
    assertSafeUrl.resolve = resolveSafeUrl;
    return assertSafeUrl;
}

const DEFAULT_IPV6_GRACE_MS = 1000;

/**
 * How long a finished IPv4 answer waits for the IPv6 answer
 * (`CRAWLERX_DNS_IPV6_GRACE_MS`, whole milliseconds; anything else is ignored).
 *
 * @param {Record<string, string | undefined>} env
 */
export function readIpv6GraceMs(env = process.env) {
    const raw = env.CRAWLERX_DNS_IPV6_GRACE_MS ?? '';
    return /^\d+$/.test(raw) ? Number(raw) : DEFAULT_IPV6_GRACE_MS;
}

/**
 * `CRAWLERX_DNS_IPV6`: `on`, `off` or `auto` (default; anything else is `auto`).
 *
 * @param {Record<string, string | undefined>} env
 * @returns {'on' | 'off' | 'auto'}
 */
export function readIpv6Mode(env = process.env) {
    const raw = (env.CRAWLERX_DNS_IPV6 ?? '').toLowerCase();
    if (['on', '1', 'true'].includes(raw)) {
        return 'on';
    }

    return ['off', '0', 'false'].includes(raw) ? 'off' : 'auto';
}

/**
 * Whether the host has an IPv6 address other than loopback and link-local.
 * Without one no IPv6 connection can be made, so the AAAA question is not
 * worth asking.
 */
export function hasGlobalIpv6(interfaces = networkInterfaces()) {
    return Object.values(interfaces).flat().some((entry) => entry
        && entry.family === 'IPv6'
        && !entry.internal
        && !/^fe[89ab][0-9a-f]:/i.test(entry.address));
}

function shouldAskIpv6(env = process.env) {
    const mode = readIpv6Mode(env);

    return mode === 'auto' ? hasGlobalIpv6() : mode === 'on';
}

/**
 * Resolve a host through parallel lookups, one per address family.
 *
 * Some name servers never answer the AAAA question (JOOXCR-727), and a stalled
 * AAAA lookup also slows the A lookups behind it, so the IPv6 question is not
 * asked at all on a host with no global IPv6 address (`CRAWLERX_DNS_IPV6`
 * overrides this). When it is asked, the IPv4 answer is the primary: once it
 * has an address the guard accepts, the IPv6 answer is waited for only
 * `graceMs` and is dropped when late. A blocked decoy answer such as 127.0.0.1
 * (JOOXCR-399) does not count, so a host whose IPv4 answers are all blocked or
 * missing waits for the IPv6 answer in full. Addresses come back IPv4 first.
 *
 * @param {string} hostname
 * @param {{resolve?: typeof lookup, graceMs?: number, allowPrivate?: boolean, ipv6?: boolean}} options
 * @returns {Promise<Array<{address: string, family: number}>>}
 */
export async function lookupAll(hostname, { resolve = lookup, graceMs = readIpv6GraceMs(), allowPrivate = false, ipv6 = shouldAskIpv6() } = {}) {
    const ask = (family) => outcome(resolve(hostname, { all: true, verbatim: true, family }));
    const ipv4Pending = ask(4);
    const ipv6Pending = ipv6 ? ask(6) : null;

    const ipv4 = await ipv4Pending;
    if (ipv6Pending === null) {
        if (ipv4.error) {
            throw ipv4.error;
        }

        return ipv4.value;
    }

    const hasUsableIpv4 = (ipv4.value ?? []).some(({ address }) => !isBlockedAddress(address, allowPrivate));
    const answer = hasUsableIpv4 ? await within(ipv6Pending, graceMs) : await ipv6Pending;
    if (ipv4.error && answer?.error) {
        throw ipv4.error;
    }

    return [...(ipv4.value ?? []), ...(answer?.value ?? [])];
}

/** Turn a promise into one that never rejects, so a late failure is never unhandled. */
function outcome(promise) {
    return promise.then((value) => ({ value }), (error) => ({ error }));
}

/** The settled outcome, or null when it is not there after `ms`. */
async function within(pending, ms) {
    let timer;
    const expired = new Promise((done) => {
        timer = setTimeout(() => done(null), ms);
    });
    try {
        return await Promise.race([pending, expired]);
    } finally {
        clearTimeout(timer);
    }
}

const fetchLab = process.env.CRAWLERX_FETCH_LAB === '1';
const allowPrivate = fetchLab && process.env.CRAWLERX_BROWSER_ALLOW_PRIVATE_IPS === '1';

export const assertSafeBrowserUrl = createSsrfGuard({
    allowPrivate,
    allowedHosts: process.env.CRAWLERX_BROWSER_ALLOWED_HOSTS ?? '',
});
