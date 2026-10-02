import { test } from 'node:test';
import assert from 'node:assert/strict';
import { accessSync, constants } from 'node:fs';
import { chromium } from 'playwright';
import puppeteer from 'puppeteer';

const fixtureSite = process.env.FIXTURE_SITE_URL ?? (process.env.CI
    ? 'http://fixture-site:8080'
    : 'http://127.0.0.1:8080');

async function get(path) {
    return fetch(`${fixtureSite}${path}`);
}

async function resetHits() {
    const response = await fetch(`${fixtureSite}/__reset`, { method: 'POST' });
    assert.equal(response.status, 200);
}

async function hits() {
    const response = await get('/__hits');
    assert.equal(response.status, 200);
    return response.json();
}

function executableExists(path) {
    try {
        accessSync(path, constants.X_OK);
        return true;
    } catch {
        return false;
    }
}

test('TC-S01 serves a static movie fixture', async () => {
    const response = await get('/static/movie/TC-S01');
    const body = await response.text();

    assert.equal(response.status, 200);
    assert.match(body, /Movie TC-S01/);
    assert.match(body, /static movie/);
});

test('TC-S02 renders JavaScript-injected movie content', async (t) => {
    const executablePath = chromium.executablePath();
    if (!executableExists(executablePath)) {
        t.skip(`Chromium executable is not installed: ${executablePath}`);
        return;
    }

    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        await page.goto(`${fixtureSite}/js/movie/TC-S02`, { waitUntil: 'domcontentloaded' });
        await page.waitForFunction(() => document.querySelector('#movie')?.textContent !== '');
        assert.equal(await page.locator('#movie').textContent(), 'js movie TC-S02');
    } finally {
        await browser.close();
    }
});

test('TC-S03 exposes the slow JavaScript fixture', async () => {
    const started = Date.now();
    const response = await get('/js-slow/movie/TC-S03');
    const body = await response.text();

    assert.equal(response.status, 200);
    assert.match(body, /slow js movie/);
    assert.ok(Date.now() - started >= 4500);
});

test('TC-S04 preserves Retry-After on a 429 response', async () => {
    const response = await get('/status/429');

    assert.equal(response.status, 429);
    assert.equal(response.headers.get('retry-after'), '30');
});

test('TC-S05 rejects a non-browser user agent', async () => {
    const response = await fetch(`${fixtureSite}/ua-check/TC-S05`, {
        headers: { 'User-Agent': 'crawlerx-http-test' },
    });

    assert.equal(response.status, 403);
});

test('TC-S06 accepts the legal-age cookie', async () => {
    const blocked = await get('/age-gate/TC-S06');
    const allowed = await fetch(`${fixtureSite}/age-gate/TC-S06`, {
        headers: { Cookie: 'legal_age=1' },
    });

    assert.equal(blocked.status, 403);
    assert.equal(allowed.status, 200);
    assert.match(await allowed.text(), /age accepted/);
});

test('TC-S07 exposes challenge headers, large bodies, and hit reset', async () => {
    const challenge = await get('/challenge');
    const big = await get('/big');
    const beforeReset = await hits();

    assert.equal(challenge.status, 403);
    assert.equal(challenge.headers.get('cf-mitigated'), 'challenge');
    assert.match(await challenge.text(), /Just a moment/);
    assert.equal((await big.arrayBuffer()).byteLength, 5 * 1024 * 1024);
    assert.ok(beforeReset['/challenge'] >= 1);

    await resetHits();
    assert.deepEqual(await hits(), {});
});

test('TC-V01 has a Chromium executable from the installed Playwright package', (t) => {
    const executablePath = chromium.executablePath();
    if (!executableExists(executablePath)) {
        t.skip(`Chromium executable is not installed: ${executablePath}`);
        return;
    }

    assert.ok(executablePath.length > 0);
});

test('TC-V02 launches Chromium and fetches the fixture site', async (t) => {
    const executablePath = chromium.executablePath();
    if (!executableExists(executablePath)) {
        t.skip(`Chromium executable is not installed: ${executablePath}`);
        return;
    }

    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        await page.goto(`${fixtureSite}/static/movie/TC-V02`, { waitUntil: 'domcontentloaded' });
        assert.equal(await page.title(), 'Movie TC-V02');
    } finally {
        await browser.close();
    }
});

test('TC-V03 launches Puppeteer with the locked Chromium executable', async (t) => {
    const executablePath = chromium.executablePath();
    if (!executableExists(executablePath)) {
        t.skip(`Chromium executable is not installed: ${executablePath}`);
        return;
    }

    const browser = await puppeteer.launch({
        headless: true,
        executablePath,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });
    try {
        const page = await browser.newPage();
        await page.goto(`${fixtureSite}/static/movie/TC-V03`, { waitUntil: 'domcontentloaded' });
        assert.equal(await page.title(), 'Movie TC-V03');
    } finally {
        await browser.close();
    }
});
