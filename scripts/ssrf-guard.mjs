import { lookup } from 'node:dns/promises';
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
    constructor() {
        super('ssrf_blocked');
        this.name = 'SsrfBlockedError';
        this.code = 'ssrf_blocked';
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
 * Create a request policy for the browser sidecar. All DNS answers must be
 * public (unless the dedicated Fetch Lab opt-in allows private networks).
 *
 * @param {{allowPrivate?: boolean, allowedHosts?: string|string[], lookupHost?: (hostname: string) => Promise<Array<{address: string}>>}} options
 */
export function createSsrfGuard({ allowPrivate = false, allowedHosts = '', lookupHost = lookupAll } = {}) {
    const hosts = parseAllowedHosts(allowedHosts);

    const resolveSafeUrl = async (value) => {
        let target;
        try {
            target = new URL(value);
        } catch {
            throw new SsrfBlockedError();
        }

        if (!['http:', 'https:', 'ws:', 'wss:'].includes(target.protocol)
            || target.username !== ''
            || target.password !== '') {
            throw new SsrfBlockedError();
        }

        const hostname = normalizeHostname(target.hostname);
        if (hostname === ''
            || blockedHostnames.has(hostname)
            || hostname.endsWith('.localhost')
            || hostname.endsWith('.local')
            || hostname.endsWith('.internal')
            || !hostIsAllowed(hostname, hosts)) {
            throw new SsrfBlockedError();
        }

        const literalVersion = isIP(hostname);
        let addresses;
        if (literalVersion !== 0) {
            addresses = [{ address: hostname }];
        } else {
            try {
                addresses = await lookupHost(hostname);
            } catch {
                throw new SsrfBlockedError();
            }
        }

        if (addresses.length === 0 || addresses.some(({ address }) => isBlockedAddress(address, allowPrivate))) {
            throw new SsrfBlockedError();
        }

        return { target, hostname, addresses };
    };

    const assertSafeUrl = async (value) => {
        await resolveSafeUrl(value);
    };
    assertSafeUrl.resolve = resolveSafeUrl;
    return assertSafeUrl;
}

async function lookupAll(hostname) {
    return lookup(hostname, { all: true, verbatim: true });
}

const fetchLab = process.env.CRAWLERX_FETCH_LAB === '1';
const allowPrivate = fetchLab && process.env.CRAWLERX_BROWSER_ALLOW_PRIVATE_IPS === '1';

export const assertSafeBrowserUrl = createSsrfGuard({
    allowPrivate,
    allowedHosts: process.env.CRAWLERX_BROWSER_ALLOWED_HOSTS ?? '',
});
