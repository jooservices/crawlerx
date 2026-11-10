import { request as httpRequest } from 'node:http';
import { request as httpsRequest } from 'node:https';
import { createBrotliDecompress, createGunzip, createInflate } from 'node:zlib';

const redirectStatuses = new Set([301, 302, 303, 307, 308]);
const robotsRedirectLimit = 5;
const unavailable = Symbol('robots-unavailable');

export async function fetchRobotsTxt(origin, { resolveUrl, userAgent, maxResponseBytes }) {
    let url = new URL('/robots.txt', origin);

    for (let redirectCount = 0; redirectCount <= robotsRedirectLimit; redirectCount += 1) {
        const resolved = await resolveUrl(url.href);
        const result = await requestRobots(url, resolved, userAgent, maxResponseBytes);
        if (result === unavailable) return null;
        if (typeof result.body === 'string') return result.body;
        if (!redirectStatuses.has(result.status) || typeof result.location !== 'string' || redirectCount === robotsRedirectLimit) {
            return null;
        }

        try {
            url = new URL(result.location, url);
            if (!['http:', 'https:'].includes(url.protocol)) return null;
        } catch {
            return null;
        }
    }

    return null;
}

function requestRobots(url, resolved, userAgent, maxResponseBytes) {
    const transport = url.protocol === 'https:' ? httpsRequest : httpRequest;

    return new Promise((resolve) => {
        const request = transport({
            protocol: url.protocol,
            hostname: resolved.hostname,
            port: Number(url.port) || (url.protocol === 'https:' ? 443 : 80),
            path: `${url.pathname}${url.search}`,
            method: 'GET',
            headers: {
                'user-agent': userAgent,
                'accept-encoding': 'identity',
                connection: 'close',
            },
            lookup: pinnedLookup(resolved.addresses),
        }, (response) => {
            const status = response.statusCode ?? 0;
            const location = response.headers.location;
            if (status === 404) {
                response.resume();
                resolve({ body: '' });
                return;
            }
            if (redirectStatuses.has(status)) {
                response.resume();
                resolve({ status, location: Array.isArray(location) ? location[0] : location });
                return;
            }
            if (status < 200 || status >= 300) {
                response.resume();
                resolve(unavailable);
                return;
            }

            const encoding = String(response.headers['content-encoding'] ?? '').toLowerCase();
            const decoder = encoding === '' || encoding === 'identity'
                ? null
                : encoding === 'gzip' || encoding === 'x-gzip'
                    ? createGunzip()
                    : encoding === 'deflate'
                        ? createInflate()
                        : encoding === 'br'
                            ? createBrotliDecompress()
                            : undefined;
            if (decoder === undefined) {
                response.resume();
                resolve({ body: 'User-agent: *\nDisallow: /\n' });
                return;
            }

            const bodyStream = decoder === null ? response : response.pipe(decoder);
            let body = '';
            let bytes = 0;
            bodyStream.on('data', (chunk) => {
                bytes += chunk.length;
                if (bytes > maxResponseBytes) {
                    decoder?.destroy();
                    response.destroy();
                    resolve({ body: 'User-agent: *\nDisallow: /\n' });
                    return;
                }
                body += chunk.toString('utf8');
            });
            bodyStream.once('end', () => resolve({ body }));
            bodyStream.once('error', () => {
                response.destroy();
                resolve(unavailable);
            });
            response.once('error', () => resolve(unavailable));
        });

        request.setTimeout(10000, () => request.destroy(new Error('robots request timed out')));
        request.once('error', () => resolve(unavailable));
        request.end();
    });
}

function pinnedLookup(addresses) {
    return (_hostname, options, callback) => {
        if (typeof options === 'function') {
            callback = options;
            options = {};
        }
        if (options?.all) {
            callback(null, addresses.map(({ address }) => ({ address, family: address.includes(':') ? 6 : 4 })));
            return;
        }
        callback(null, addresses[0].address, addresses[0].address.includes(':') ? 6 : 4);
    };
}
