import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { chromium } from 'playwright';
import { dismissInterstitials } from '../../scripts/interstitials.mjs';

const pages = {
    '/star/ej3': '<html><body><h1>Shiratori Miruka</h1><div class="movie-box"><a href="/movie/rct-009">Center Stage Entertainer</a></div></body></html>',
    '/movie/rct-009': '<html><body><h1>RCT-009</h1></body></html>',
    '/gate': '<html><body><p>You must be 18.</p><a href="/gate-passed">Enter</a></body></html>',
    '/gate-passed': '<html><body><h1>Welcome</h1></body></html>',
    '/star/other': '<html><body><h1>Other</h1><a href="/elsewhere">I am 18 and I love this page</a></body></html>',
    '/elsewhere': '<html><body><h1>Elsewhere</h1></body></html>',
};

async function withPage(run) {
    const server = createServer((request, response) => {
        const path = new URL(request.url, 'http://x').pathname;
        // The gate lets a visitor in once the age cookie is set, as the real sites do.
        const body = path === '/gate' && String(request.headers.cookie ?? '').includes('over18=18')
            ? '<html><body><h1>Welcome</h1></body></html>'
            : pages[path];
        response.writeHead(body === undefined ? 404 : 200, { 'content-type': 'text/html' });
        response.end(body ?? 'not found');
    });
    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    const base = `http://127.0.0.1:${server.address().port}`;
    const browser = await chromium.launch();
    try {
        const context = await browser.newContext();
        const page = await context.newPage();
        await run({ page, context, base });
    } finally {
        await browser.close();
        server.close();
    }
}

test('AC1 a content link that merely contains "enter" is not clicked', async () => {
    await withPage(async ({ page, context, base }) => {
        await page.goto(`${base}/star/ej3`);
        await dismissInterstitials(page, context, `${base}/star/ej3`);

        assert.equal(new URL(page.url()).pathname, '/star/ej3');
    });
});

test('AC2 an age gate whose link says exactly Enter is still passed and the requested page is shown', async () => {
    await withPage(async ({ page, context, base }) => {
        await page.goto(`${base}/gate`);
        await dismissInterstitials(page, context, `${base}/gate`);

        assert.equal(new URL(page.url()).pathname, '/gate');
        assert.equal(await page.locator('h1').innerText(), 'Welcome');
    });
});

test('AC3 a click that moves the requested page to another path returns to the requested page', async () => {
    await withPage(async ({ page, context, base }) => {
        await page.goto(`${base}/star/other`);
        await dismissInterstitials(page, context, `${base}/star/other`);

        assert.equal(new URL(page.url()).pathname, '/star/other');
    });
});
