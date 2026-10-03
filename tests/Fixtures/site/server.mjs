#!/usr/bin/env node

import { createServer } from 'node:http';

const port = Number.parseInt(process.env.PORT ?? '8080', 10);
const hits = new Map();

function routeKey(pathname) {
    if (/^\/static\/movie\/[^/]+$/.test(pathname)) return '/static/movie/:id';
    if (/^\/js\/movie\/[^/]+$/.test(pathname)) return '/js/movie/:id';
    if (/^\/js-slow\/movie\/[^/]+$/.test(pathname)) return '/js-slow/movie/:id';
    if (/^\/soft404\/[^/]+$/.test(pathname)) return '/soft404/:id';
    if (/^\/(?:ready|shell|drift|images|cf-bound|login-wall|flaky)\/movie?\/?[^/]*$/.test(pathname)) return pathname.split('/').slice(0, 3).join('/') + '/:id';
    if (/^\/flaky\/[^/]+$/.test(pathname)) return '/flaky/:id';
    if (/^\/status\/\d+$/.test(pathname)) return '/status/:code';
    if (/^\/ua-check\/[^/]+$/.test(pathname)) return '/ua-check/:id';
    if (/^\/age-gate\/[^/]+$/.test(pathname)) return '/age-gate/:id';
    return pathname;
}

function count(pathname) {
    const key = routeKey(pathname);
    hits.set(key, (hits.get(key) ?? 0) + 1);
}

function send(response, status, body, headers = {}) {
    response.writeHead(status, {
        'Content-Type': 'text/html; charset=utf-8',
        ...headers,
    });
    response.end(body);
}

function moviePage(id, content) {
    return `<!doctype html><html><head><title>Movie ${id}</title></head><body>${content}</body></html>`;
}

function parseCookie(header) {
    return Object.fromEntries((header ?? '').split(';').map((part) => {
        const separator = part.indexOf('=');
        if (separator < 0) return [part.trim(), ''];
        return [part.slice(0, separator).trim(), part.slice(separator + 1).trim()];
    }).filter(([name]) => name !== ''));
}

const server = createServer((request, response) => {
    const url = new URL(request.url ?? '/', `http://${request.headers.host ?? 'localhost'}`);

    if (request.method === 'POST' && url.pathname === '/__reset') {
        hits.clear();
        send(response, 200, JSON.stringify({ ok: true }), { 'Content-Type': 'application/json' });
        return;
    }

    if (request.method === 'GET' && url.pathname === '/__hits') {
        send(response, 200, JSON.stringify(Object.fromEntries(hits)), { 'Content-Type': 'application/json' });
        return;
    }

    if (request.method === 'POST' && url.pathname === '/__flare/v1') {
        let body = '';
        request.on('data', (chunk) => { body += chunk; });
        request.on('end', () => {
            let targetUrl = '';
            try {
                targetUrl = JSON.parse(body).url ?? '';
            } catch {
                targetUrl = '';
            }
            const target = new URL(targetUrl || 'http://fixture-site:8080/cf-bound/fixture');
            const id = target.pathname.split('/').filter(Boolean).at(-1) ?? 'fixture';
            const content = target.pathname.startsWith('/static/movie/')
                ? '<p id="movie">static movie</p>'
                : '<p id="movie">flare solved</p>';
            send(response, 200, JSON.stringify({
                status: 'ok',
                solution: {
                    url: targetUrl,
                    status: 200,
                    response: moviePage(id, content),
                    cookies: [{ name: 'cf_clearance', value: 'fixture-clearance' }],
                    userAgent: 'CrawlerX-Fake-Flare/1.0',
                },
            }), { 'Content-Type': 'application/json' });
        });
        return;
    }

    if (request.method !== 'GET') {
        send(response, 405, '<h1>Method Not Allowed</h1>');
        return;
    }

    count(url.pathname);

    if (url.pathname === '/hang') {
        return;
    }

    if (url.pathname === '/challenge') {
        send(response, 403, '<html><head><title>Just a moment...</title></head><body>challenge</body></html>', {
            'cf-mitigated': 'challenge',
        });
        return;
    }

    if (url.pathname === '/big') {
        send(response, 200, 'x'.repeat(5 * 1024 * 1024));
        return;
    }

    if (url.pathname.startsWith('/fixture-assets/')) {
        const extension = url.pathname.split('.').at(-1);
        const contentType = extension === 'woff2' ? 'font/woff2' : extension === 'mp4' ? 'video/mp4' : 'image/jpeg';
        send(response, 200, extension === 'mp4' ? '' : 'fixture asset', { 'Content-Type': contentType });
        return;
    }

    const staticMatch = url.pathname.match(/^\/static\/movie\/([^/]+)$/);
    if (staticMatch) {
        send(response, 200, moviePage(decodeURIComponent(staticMatch[1]), '<p id="movie">static movie</p>'));
        return;
    }

    const jsMatch = url.pathname.match(/^\/js\/movie\/([^/]+)$/);
    if (jsMatch) {
        const id = decodeURIComponent(jsMatch[1]);
        send(response, 200, moviePage(id, '<p id="movie"></p><script>setTimeout(() => { document.querySelector("#movie").textContent = "js movie ' + id + '"; }, 500);</script>'));
        return;
    }

    const slowMatch = url.pathname.match(/^\/js-slow\/movie\/([^/]+)$/);
    if (slowMatch) {
        const id = decodeURIComponent(slowMatch[1]);
        setTimeout(() => send(response, 200, moviePage(id, '<p id="movie">slow js movie</p>')), 5000);
        return;
    }

    const soft404Match = url.pathname.match(/^\/soft404\/([^/]+)$/);
    if (soft404Match) {
        send(response, 200, moviePage(soft404Match[1], '<h1>Page not found</h1>'));
        return;
    }

    const readyMatch = url.pathname.match(/^\/ready\/movie\/([^/]+)$/);
    if (readyMatch) {
        send(response, 200, moviePage(readyMatch[1], `<p id="movie">ready movie ${readyMatch[1]}</p>`));
        return;
    }

    const shellMatch = url.pathname.match(/^\/shell\/movie\/([^/]+)$/);
    if (shellMatch) {
        send(response, 200, moviePage(shellMatch[1], '<div id="app">shell</div><script>setTimeout(() => { document.querySelector("#app").innerHTML = "<p id=\\"movie\\">shell movie ' + shellMatch[1] + '</p>"; }, 100);</script>'));
        return;
    }

    const driftMatch = url.pathname.match(/^\/drift\/movie\/([^/]+)$/);
    if (driftMatch) {
        send(response, 200, moviePage(driftMatch[1], `<p id="movie-renamed">drift movie ${driftMatch[1]}</p>`));
        return;
    }

    const imagesMatch = url.pathname.match(/^\/images\/movie\/([^/]+)$/);
    if (imagesMatch) {
        const images = Array.from({ length: 5 }, (_, index) => `<img src="/fixture-assets/image-${index + 1}.jpg" alt="image ${index + 1}">`).join('');
        send(response, 200, moviePage(imagesMatch[1], `<section id="movie">${images}<style>@font-face{font-family:fixture;src:url('/fixture-assets/font.woff2')}</style><video src="/fixture-assets/video.mp4"></video></section>`));
        return;
    }

    const cfBoundMatch = url.pathname.match(/^\/cf-bound\/([^/]+)$/);
    if (cfBoundMatch) {
        const cookies = parseCookie(request.headers.cookie);
        const browserUserAgent = request.headers['user-agent'] ?? '';
        const browserSolved = /(?:HeadlessChrome|Chrome\/\d+)/i.test(browserUserAgent);
        const matched = cookies.cf_clearance === 'fixture-clearance'
            && request.headers['user-agent'] === 'CrawlerX-Fake-Flare/1.0';
        if (! matched && ! browserSolved) {
            send(response, 403, '<html><head><title>Just a moment...</title></head><body>challenge</body></html>', { 'cf-mitigated': 'challenge' });
            return;
        }
        send(response, 200, moviePage(cfBoundMatch[1], '<p id="movie">cf bound data</p>'));
        return;
    }

    const flakyMatch = url.pathname.match(/^\/flaky\/([^/]+)$/);
    if (flakyMatch) {
        if ((hits.get('/flaky/:id') ?? 0) === 1) {
            send(response, 502, '<html><body>temporary upstream failure</body></html>');
            return;
        }
        send(response, 200, moviePage(flakyMatch[1], '<p id="movie">flaky data</p>'));
        return;
    }

    const loginWallMatch = url.pathname.match(/^\/login-wall\/([^/]+)$/);
    if (loginWallMatch) {
        const requiredCookie = url.searchParams.get('cookie') ?? 'remember_token';
        const cookies = parseCookie(request.headers.cookie);
        if (! cookies[requiredCookie]) {
            send(response, 401, '<html><body>login required</body></html>');
            return;
        }
        send(response, 200, moviePage(loginWallMatch[1], '<p id="movie">authenticated data</p>'));
        return;
    }

    const statusMatch = url.pathname.match(/^\/status\/(\d+)$/);
    if (statusMatch) {
        const status = Number.parseInt(statusMatch[1], 10);
        const retryAfter = url.searchParams.get('retry');
        const headers = status === 429 || (status === 503 && retryAfter !== null)
            ? { 'Retry-After': retryAfter ?? '30' }
            : {};
        send(response, status, `<html><body>status ${status}</body></html>`, headers);
        return;
    }

    const uaMatch = url.pathname.match(/^\/ua-check\/([^/]+)$/);
    if (uaMatch) {
        const userAgent = request.headers['user-agent'] ?? '';
        const browserLike = /Chrome|Firefox|Safari/i.test(userAgent);
        send(response, browserLike ? 200 : 403, moviePage(decodeURIComponent(uaMatch[1]), '<p id="movie">ua accepted</p>'));
        return;
    }

    const ageMatch = url.pathname.match(/^\/age-gate\/([^/]+)$/);
    if (ageMatch) {
        const cookies = parseCookie(request.headers.cookie);
        if (cookies.legal_age !== '1') {
            send(response, 403, '<html><head><title>Age gate</title></head><body>legal age required</body></html>');
            return;
        }
        send(response, 200, moviePage(decodeURIComponent(ageMatch[1]), '<p id="movie">age accepted</p>'));
        return;
    }

    send(response, 404, '<html><body>not found</body></html>');
});

server.listen(port, '0.0.0.0', () => {
    process.stderr.write(`Fixture site listening on ${port}\n`);
});

function close(signal) {
    server.close(() => process.exit(signal === 'SIGTERM' ? 0 : 0));
}

process.on('SIGTERM', () => close('SIGTERM'));
process.on('SIGINT', () => close('SIGINT'));
