import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { networkInterfaces } from 'node:os';
import { chromium } from 'playwright';

const root = new URL('../..', import.meta.url).pathname;

function hasChromium() {
    return existsSync(chromium.executablePath());
}

function privateInterfaceAddress() {
    for (const entries of Object.values(networkInterfaces())) {
        for (const entry of entries ?? []) {
            if (entry.family === 'IPv4' && !entry.internal && !entry.address.startsWith('127.')) {
                return entry.address;
            }
        }
    }

    return null;
}

async function listen(handler) {
    const server = createServer(handler);
    await new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(0, '0.0.0.0', resolve);
    });
    const address = server.address();

    return {
        port: address.port,
        close: () => new Promise((resolve, reject) => server.close((error) => error ? reject(error) : resolve())),
    };
}

async function waitFor(predicate, timeoutMs = 10000) {
    const deadline = Date.now() + timeoutMs;
    while (Date.now() < deadline) {
        if (predicate()) return;
        await new Promise((resolve) => setTimeout(resolve, 25));
    }
    throw new Error('Timed out waiting for browser service');
}

async function startBrowserService(host) {
    const port = 19000 + Math.floor(Math.random() * 1000);
    const stderr = [];
    const child = spawn(process.execPath, ['scripts/fetch-server.mjs'], {
        cwd: root,
        env: {
            ...process.env,
            PLAYWRIGHT_HOST: '127.0.0.1',
            PLAYWRIGHT_PORT: String(port),
            CRAWLERX_BROWSER_MAX_CONCURRENCY: '1',
            CRAWLERX_FETCH_LAB: '1',
            CRAWLERX_BROWSER_ALLOW_PRIVATE_IPS: '1',
            CRAWLERX_BROWSER_ALLOWED_HOSTS: host,
        },
        stdio: ['ignore', 'ignore', 'pipe'],
    });
    child.stderr.setEncoding('utf8');
    child.stderr.on('data', (chunk) => stderr.push(chunk));

    await waitFor(() => stderr.join('').includes(`listening on ${port}`));

    return {
        port,
        async stop() {
            if (child.exitCode !== null || child.signalCode !== null) return;
            child.kill('SIGTERM');
            await new Promise((resolve) => {
                const timer = setTimeout(() => {
                    child.kill('SIGKILL');
                    resolve();
                }, 5000);
                child.once('close', () => {
                    clearTimeout(timer);
                    resolve();
                });
            });
        },
    };
}

async function fetchBrowser(service, config) {
    const response = await fetch(`http://127.0.0.1:${service.port}/fetch`, {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ script: 'playwright', config }),
    });
    const payload = await response.json();

    return { payload, result: payload.stdout ? JSON.parse(payload.stdout) : null };
}

test('browser cookies reach the configured origin and stay off another origin', async (t) => {
    if (!hasChromium()) {
        t.skip('The Playwright Chromium executable is not installed');
        return;
    }
    const host = privateInterfaceAddress();
    if (host === null) {
        t.skip('No non-loopback IPv4 address is available for the local browser fixture');
        return;
    }

    const targetCookies = [];
    const target = await listen((request, response) => {
        targetCookies.push(request.headers.cookie ?? '');
        response.writeHead(200, { 'Content-Type': 'image/png' });
        response.end('not-a-real-image');
    });
    const sourceCookies = [];
    const source = await listen((request, response) => {
        sourceCookies.push(request.headers.cookie ?? '');
        response.writeHead(200, { 'Content-Type': 'text/html' });
        response.end(`<html><body><img src="http://${host}:${target.port}/pixel"></body></html>`);
    });
    const service = await startBrowserService(host);

    try {
        const { payload, result } = await fetchBrowser(service, {
            url: `http://${host}:${source.port}/start`,
            extraHttpHeaders: { Cookie: 'session=alice' },
            blockResources: false,
            navigationTimeoutMs: 10000,
        });

        assert.equal(payload.exitCode, 0, JSON.stringify(payload));
        assert.equal(result.status, 200);
        await waitFor(() => targetCookies.length > 0);
        assert.match(sourceCookies[0], /session=alice/);
        assert.doesNotMatch(targetCookies[0], /session=alice/);
    } finally {
        await service.stop();
        await source.close();
        await target.close();
    }
});

test('browser follows a same-origin redirect with its sensitive header', async (t) => {
    if (!hasChromium()) {
        t.skip('The Playwright Chromium executable is not installed');
        return;
    }
    const host = privateInterfaceAddress();
    if (host === null) {
        t.skip('No non-loopback IPv4 address is available for the local browser fixture');
        return;
    }

    const authorizations = [];
    const source = await listen((request, response) => {
        authorizations.push(request.headers.authorization ?? '');
        if (request.url === '/start') {
            response.writeHead(302, { Location: '/done' });
            response.end();
            return;
        }
        response.writeHead(200, { 'Content-Type': 'text/html' });
        response.end('<html><body>same origin</body></html>');
    });
    const service = await startBrowserService(host);

    try {
        const { payload, result } = await fetchBrowser(service, {
            url: `http://${host}:${source.port}/start`,
            extraHttpHeaders: { Authorization: 'Bearer private-token' },
            navigationTimeoutMs: 10000,
        });

        assert.equal(payload.exitCode, 0, JSON.stringify(payload));
        assert.equal(result.status, 200);
        assert.deepEqual(authorizations, ['Bearer private-token', 'Bearer private-token']);
    } finally {
        await service.stop();
        await source.close();
    }
});

test('browser strips sensitive headers before a cross-origin redirect destination', async (t) => {
    if (!hasChromium()) {
        t.skip('The Playwright Chromium executable is not installed');
        return;
    }
    const host = privateInterfaceAddress();
    if (host === null) {
        t.skip('No non-loopback IPv4 address is available for the local browser fixture');
        return;
    }

    const targetRequests = [];
    const target = await listen((request, response) => {
        targetRequests.push(request.headers.authorization ?? '');
        response.writeHead(200, { 'Content-Type': 'text/html' });
        response.end('<html><body>target</body></html>');
    });
    const sourceAuthorizations = [];
    const source = await listen((request, response) => {
        sourceAuthorizations.push(request.headers.authorization ?? '');
        response.writeHead(302, { Location: `http://${host}:${target.port}/private` });
        response.end();
    });
    const service = await startBrowserService(host);

    try {
        const { payload, result } = await fetchBrowser(service, {
            url: `http://${host}:${source.port}/start`,
            extraHttpHeaders: { Authorization: 'Bearer private-token' },
            navigationTimeoutMs: 10000,
        });

        assert.equal(payload.exitCode, 0, JSON.stringify(payload));
        assert.equal(result.status, 200);
        assert.match(sourceAuthorizations[0] ?? '', /Bearer private-token/);
        assert.deepEqual(targetRequests, ['']);
    } finally {
        await service.stop();
        await source.close();
        await target.close();
    }
});
