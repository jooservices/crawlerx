import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);
const root = new URL('../..', import.meta.url).pathname;
const fixtureSite = process.env.FIXTURE_SITE_URL ?? (process.env.CI
    ? 'http://fixture-site:8080'
    : 'http://127.0.0.1:8080');

async function fixtureIsAvailable() {
    try {
        const response = await fetch(`${fixtureSite}/static/movie/browser-pool-probe`);
        return response.status === 200;
    } catch {
        return false;
    }
}

async function waitFor(predicate, timeoutMs = 10000) {
    const started = Date.now();
    while (Date.now() - started < timeoutMs) {
        if (await predicate()) {
            return;
        }
        await new Promise((resolve) => setTimeout(resolve, 50));
    }
    throw new Error('Timed out waiting for browser service');
}

async function startService(options = {}) {
    const port = 19000 + Math.floor(Math.random() * 1000);
    const stdout = [];
    const stderr = [];
    const child = spawn(process.execPath, ['scripts/fetch-server.mjs'], {
        cwd: root,
        env: {
            ...process.env,
            CRAWLERX_BROWSER_SERVICE_PORT: String(port),
            CRAWLERX_BROWSER_MAX_CONCURRENCY: '1',
            CRAWLERX_BROWSER_MAX_QUEUE: '2',
            ...options,
        },
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    child.stdout.setEncoding('utf8');
    child.stderr.setEncoding('utf8');
    child.stdout.on('data', (chunk) => stdout.push(chunk));
    child.stderr.on('data', (chunk) => stderr.push(chunk));

    await waitFor(() => stderr.join('').includes(`listening on ${port}`));
    return {
        child,
        port,
        stdout,
        stderr,
        async stop() {
            if (child.exitCode !== null || child.signalCode !== null) {
                return;
            }
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
    return { response, payload, result: payload.stdout ? JSON.parse(payload.stdout) : null };
}

async function health(service) {
    const response = await fetch(`http://127.0.0.1:${service.port}/health`);
    return { response, payload: await response.json() };
}

async function resetHits() {
    await fetch(`${fixtureSite}/__reset`, { method: 'POST' });
}

async function hits() {
    return (await fetch(`${fixtureSite}/__hits`)).json();
}

async function skipWithoutFixture(t) {
    if (!(await fixtureIsAvailable())) {
        t.skip(`fixture site is not available at ${fixtureSite}`);
        return true;
    }
    return false;
}

test('TC-BR-01 reuses one Chromium and closes each request context', async (t) => {
    if (await skipWithoutFixture(t)) return;
    const service = await startService({ CRAWLERX_BROWSER_MAX_REQUESTS: '50' });
    try {
        for (let index = 0; index < 5; index += 1) {
            const { response, payload, result } = await fetchBrowser(service, {
                url: `${fixtureSite}/static/movie/br01-${index}`,
            });
            assert.equal(response.status, 200);
            assert.equal(payload.exitCode, 0);
            assert.equal(result.status, 200);
        }
        const launches = service.stdout.join('').split('\n').filter((line) => line.includes('"event":"browser_launch"'));
        const relaunches = service.stdout.join('').split('\n').filter((line) => line.includes('"event":"browser_relaunch"'));
        assert.equal(launches.length, 1);
        assert.equal(relaunches.length, 0);
        assert.equal((await health(service)).payload.browser_requests, 5);
    } finally {
        await service.stop();
    }
});

test('TC-BR-02 relaunches after the configured request count', async (t) => {
    if (await skipWithoutFixture(t)) return;
    const service = await startService({ CRAWLERX_BROWSER_MAX_REQUESTS: '10' });
    try {
        for (let index = 0; index < 25; index += 1) {
            const { payload } = await fetchBrowser(service, {
                url: `${fixtureSite}/static/movie/br02-${index}`,
            });
            assert.equal(payload.exitCode, 0);
        }
        const relaunches = service.stdout.join('').split('\n').filter((line) => line.includes('"event":"browser_relaunch"'));
        assert.equal(relaunches.length, 2);
        assert.equal((await health(service)).payload.relaunch_count, 2);
    } finally {
        await service.stop();
    }
});

test('TC-BR-03 relaunches when Chromium disconnects', async (t) => {
    if (await skipWithoutFixture(t)) return;
    const service = await startService({ CRAWLERX_BROWSER_MAX_REQUESTS: '50' });
    try {
        assert.equal((await fetchBrowser(service, { url: `${fixtureSite}/static/movie/br03-a` })).payload.exitCode, 0);
        const { stdout } = await execFileAsync('ps', ['-axo', 'pid=,ppid=,command=']);
        const browserProcess = stdout.split('\n').map((line) => line.trim()).find((line) => (
            line.includes('--disable-blink-features=AutomationControlled') && line.split(/\s+/)[1] === String(service.child.pid)
        ));
        if (!browserProcess) {
            t.skip('Chromium child process was not visible to the test process');
            return;
        }
        process.kill(Number.parseInt(browserProcess.split(/\s+/)[0], 10), 'SIGKILL');
        await waitFor(() => service.stdout.join('').includes('"event":"browser_crash"'));
        const { payload } = await fetchBrowser(service, { url: `${fixtureSite}/static/movie/br03-b` });
        assert.equal(payload.exitCode, 0);
        assert.equal((await health(service)).payload.relaunch_count, 1);
    } finally {
        await service.stop();
    }
});

test('TC-BR-04 waits for a ready marker within the request budget', async (t) => {
    if (await skipWithoutFixture(t)) return;
    const service = await startService();
    try {
        const { result } = await fetchBrowser(service, {
            url: `${fixtureSite}/ready/movie/br04`,
            readyMarkers: ['#movie'],
            readyTimeoutMs: 3000,
        });
        assert.equal(result.status, 200);
        assert.match(result.html, /ready movie br04/);
        assert.equal(result.phases.find((phase) => phase.id === 'ready_marker')?.status, 'ok');
    } finally {
        await service.stop();
    }
});

test('TC-BR-05 blocks images, fonts, and media by default', async (t) => {
    if (await skipWithoutFixture(t)) return;
    await resetHits();
    const service = await startService();
    try {
        const { result } = await fetchBrowser(service, { url: `${fixtureSite}/images/movie/br05` });
        assert.equal(result.status, 200);
        const fixtureHits = await hits();
        assert.equal(fixtureHits['/fixture-assets/image-1.jpg'] ?? 0, 0);
        assert.equal(fixtureHits['/fixture-assets/font.woff2'] ?? 0, 0);
        assert.equal(fixtureHits['/fixture-assets/video.mp4'] ?? 0, 0);
    } finally {
        await service.stop();
    }
});

test('TC-BR-06 allows resources when blockResources is false', async (t) => {
    if (await skipWithoutFixture(t)) return;
    await resetHits();
    const service = await startService();
    try {
        const { result } = await fetchBrowser(service, {
            url: `${fixtureSite}/images/movie/br06`,
            blockResources: false,
        });
        assert.equal(result.status, 200);
        const fixtureHits = await hits();
        assert.ok((fixtureHits['/fixture-assets/image-1.jpg'] ?? 0) > 0);
    } finally {
        await service.stop();
    }
});

test('TC-BR-07 keeps sidecar RSS stable across sequential contexts', async (t) => {
    if (await skipWithoutFixture(t)) return;
    const service = await startService({ CRAWLERX_BROWSER_MAX_REQUESTS: '50' });
    try {
        const baseline = (await health(service)).payload.rss_mb;
        for (let index = 0; index < 5; index += 1) {
            assert.equal((await fetchBrowser(service, { url: `${fixtureSite}/static/movie/br07-${index}` })).payload.exitCode, 0);
        }
        const after = (await health(service)).payload.rss_mb;
        assert.ok(after < baseline * 1.2, `RSS grew from ${baseline} MB to ${after} MB`);
    } finally {
        await service.stop();
    }
});

test('TC-BR-08 benchmark contract includes Puppeteer and relaunch counts', () => {
    const benchmark = readFileSync(new URL('../../tools/benchmark/run.mjs', import.meta.url), 'utf8');
    assert.match(benchmark, /puppeteer_stealth/);
    assert.match(benchmark, /browserRelaunchCount/);
    assert.match(benchmark, /relaunches/);
});

test('TC-BR-09 reports fatal browser launch failure after three retries', async (t) => {
    const service = await startService({ CRAWLERX_BROWSER_EXECUTABLE_PATH: '/definitely/missing/chromium' });
    try {
        for (let index = 0; index < 3; index += 1) {
            await fetchBrowser(service, { url: `${fixtureSite}/static/movie/br09-${index}` }).catch(() => null);
        }
        await waitFor(() => service.child.exitCode !== null || service.child.signalCode !== null, 5000);
        assert.equal(service.child.exitCode, 1);
        assert.ok(service.stdout.join('').includes('"event":"browser_fatal"'));
    } finally {
        await service.stop();
    }
});

test('TC-BR-10 accepts and returns request-scoped storage state', async (t) => {
    if (await skipWithoutFixture(t)) return;
    const service = await startService();
    try {
        const { result } = await fetchBrowser(service, {
            url: `${fixtureSite}/login-wall/br10?cookie=fixture_session`,
            storageState: {
                cookies: [{ name: 'fixture_session', value: 'fixture-value', domain: '127.0.0.1', path: '/' }],
                origins: [],
            },
        });
        assert.equal(result.status, 200);
        assert.match(result.html, /authenticated data/);
        assert.ok(result.storageState.cookies.some((cookie) => cookie.name === 'fixture_session'));
    } finally {
        await service.stop();
    }
});
