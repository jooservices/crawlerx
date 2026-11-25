import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { chromium } from 'playwright';
import { dismissInterstitials } from '../../scripts/interstitials.mjs';

const pages = {
    '/star/ej3': '<html><body><h1>Shiratori Miruka</h1><div class="movie-box"><a href="/movie/rct-009">Center Stage Entertainer</a></div></body></html>',
    '/star/dragon': '<html><body><h1>Dragon fan</h1><a href="/movie/dragon">Enter the Dragon</a></body></html>',
    '/movie/rct-009': '<html><body><h1>RCT-009</h1></body></html>',
    '/movie/dragon': '<html><body><h1>Dragon</h1></body></html>',
    '/gate': '<html><body><p>You must be 18.</p><a href="/gate-passed">Enter</a></body></html>',
    '/nested-gate': '<html><body><p>You must be 18.</p><a href="/gate-passed"><span>Enter</span></a></body></html>',
    '/gate-passed': '<html><body><h1>Welcome</h1></body></html>',
    '/star/other': '<html><body><h1>Other</h1><a href="/elsewhere">I am 18 and I love this page</a></body></html>',
    '/elsewhere': '<html><body><h1>Elsewhere</h1></body></html>',
};

async function withPage(run) {
    const visited = [];
    const hits = {};
    const server = createServer((request, response) => {
        const path = new URL(request.url, 'http://x').pathname;
        visited.push(path);
        hits[path] = (hits[path] ?? 0) + 1;
        // The gates let a visitor in once the age cookie is set, as the real sites do.
        const passed = String(request.headers.cookie ?? '').includes('over18=18');
        if ((path === '/gate' || path === '/nested-gate') && passed) {
            response.writeHead(200, { 'content-type': 'text/html' });
            response.end('<html><body><h1>Welcome</h1></body></html>');
            return;
        }
        // A page that stops answering after the first visit.
        if (path === '/star/flaky') {
            const first = hits[path] === 1;
            response.writeHead(first ? 200 : 503, { 'content-type': 'text/html' });
            response.end(first ? '<html><body><a href="/elsewhere">I am 18 and I love this page</a></body></html>' : 'unavailable');
            return;
        }
        const body = pages[path];
        response.writeHead(body === undefined ? 404 : 200, { 'content-type': 'text/html' });
        response.end(body ?? 'not found');
    });
    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    const base = `http://127.0.0.1:${server.address().port}`;
    let browser = null;
    try {
        browser = await chromium.launch();
        const context = await browser.newContext();
        const page = await context.newPage();
        await run({ page, context, base, visited });
    } finally {
        await browser?.close();
        server.close();
    }
}

test('AC1 a content link that merely contains "enter" is never clicked', async () => {
    await withPage(async ({ page, context, base, visited }) => {
        await page.goto(`${base}/star/ej3`);
        await dismissInterstitials(page, context, `${base}/star/ej3`);

        assert.deepEqual(visited.filter((path) => path.startsWith('/movie/')), [], 'the movie link must not be requested');
        assert.equal(new URL(page.url()).pathname, '/star/ej3');
    });
});

test('AC1 a link whose title starts with Enter is not an age gate', async () => {
    await withPage(async ({ page, context, base, visited }) => {
        await page.goto(`${base}/star/dragon`);
        await dismissInterstitials(page, context, `${base}/star/dragon`);

        assert.deepEqual(visited.filter((path) => path.startsWith('/movie/')), []);
        assert.equal(new URL(page.url()).pathname, '/star/dragon');
    });
});

for (const gate of ['/gate', '/nested-gate']) {
    test(`AC2 the age gate at ${gate} whose control says exactly Enter is passed and the requested page is shown`, async () => {
        await withPage(async ({ page, context, base, visited }) => {
            await page.goto(`${base}${gate}`);
            await dismissInterstitials(page, context, `${base}${gate}`);

            assert.ok(visited.includes('/gate-passed'), 'the Enter control must be clicked');
            assert.equal(new URL(page.url()).pathname, gate);
            assert.equal(await page.locator('h1').innerText(), 'Welcome');
        });
    });
}

test('AC3 a click that moves the requested page to another path returns to the requested page', async () => {
    await withPage(async ({ page, context, base }) => {
        await page.goto(`${base}/star/other`);
        await dismissInterstitials(page, context, `${base}/star/other`);

        assert.equal(new URL(page.url()).pathname, '/star/other');
    });
});

test('AC4 the answer of the page the browser returned to is handed back to the caller', async () => {
    await withPage(async ({ page, context, base }) => {
        await page.goto(`${base}/star/flaky`);
        const returned = await dismissInterstitials(page, context, `${base}/star/flaky`);

        assert.equal(returned?.status(), 503, 'the caller must see the status of the page it is given');
    });
});
