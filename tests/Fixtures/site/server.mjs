#!/usr/bin/env node

import { createServer } from 'node:http';

const port = Number.parseInt(process.env.PORT ?? '8080', 10);
const hits = new Map();

function routeKey(pathname) {
    if (/^\/static\/movie\/[^/]+$/.test(pathname)) return '/static/movie/:id';
    if (/^\/js\/movie\/[^/]+$/.test(pathname)) return '/js/movie/:id';
    if (/^\/js-slow\/movie\/[^/]+$/.test(pathname)) return '/js-slow/movie/:id';
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

    const statusMatch = url.pathname.match(/^\/status\/(\d+)$/);
    if (statusMatch) {
        const status = Number.parseInt(statusMatch[1], 10);
        const headers = status === 429 ? { 'Retry-After': '30' } : {};
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
