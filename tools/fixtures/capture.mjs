#!/usr/bin/env node

/**
 * Capture real DOM HTML from a live URL using Playwright.
 * Usage:
 *   node tools/fixtures/capture.mjs --url='https://onejav.com/new' --out=tests/Fixtures/onejav/listing-page-1.html
 *   node tools/fixtures/capture.mjs --manifest
 */

import { spawn } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { tmpdir } from 'node:os';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const script = join(root, 'scripts/playwright-fetch.mjs');

const args = process.argv.slice(2);
let url = '';
let out = '';
let waitMs = 8000;
let manifestMode = false;
let headless = true;
let flaresolverrUrl = '';

for (const arg of args) {
    if (arg.startsWith('--url=')) {
        url = arg.slice('--url='.length);
    } else if (arg.startsWith('--out=')) {
        out = arg.slice('--out='.length);
    } else if (arg.startsWith('--wait-ms=')) {
        waitMs = Number.parseInt(arg.slice('--wait-ms='.length), 10) || 8000;
    } else if (arg === '--manifest') {
        manifestMode = true;
    } else if (arg === '--headed') {
        headless = false;
    } else if (arg.startsWith('--flaresolverr-url=')) {
        flaresolverrUrl = arg.slice('--flaresolverr-url='.length);
    }
}

async function runFlaresolverr(targetUrl, saveHtmlPath) {
    const response = await fetch(flaresolverrUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ cmd: 'request.get', url: targetUrl, maxTimeout: 90000 }),
    });
    const payload = await response.json();
    const solution = payload.solution ?? {};
    const html = typeof solution.response === 'string' ? solution.response : '';
    const title = html.match(/<title[^>]*>([^<]*)<\/title>/i)?.[1] ?? '';
    const challenge = payload.status !== 'ok' ||
        title.toLowerCase().includes('just a moment') ||
        title.toLowerCase().includes('age verification javbus') ||
        html.includes('driver-verify') ||
        html.includes('cf-browser-verification');
    if (challenge || html.trim() === '') {
        throw new Error(payload.message ?? `challenge=${challenge} status=${solution.status ?? 0}`);
    }
    writeFileSync(saveHtmlPath, html, 'utf8');

    return {
        status: solution.status ?? 200,
        finalUrl: solution.url ?? targetUrl,
        htmlBytes: html.length,
        elapsedMs: 0,
        challenge: false,
    };
}

function runPlaywright(targetUrl, saveHtmlPath, extraWait = waitMs) {
    return new Promise((resolvePromise, reject) => {
        const configPath = join(tmpdir(), `crawlerx-capture-${Date.now()}-${Math.random().toString(16).slice(2)}.json`);
        writeFileSync(configPath, JSON.stringify({
            url: targetUrl,
            waitMs: extraWait,
            browser: 'chromium',
            headless,
            navigationTimeoutMs: 90000,
            viewport: { width: 1440, height: 900 },
            locale: 'en-US',
            stealthEnabled: true,
            stealthLevel: 'enhanced',
            saveHtmlPath,
        }));

        const child = spawn(process.execPath, [script, `--config=${configPath}`], {
            cwd: root,
            stdio: ['ignore', 'pipe', 'pipe'],
        });

        let stdout = '';
        let stderr = '';
        child.stdout.on('data', (chunk) => {
            stdout += chunk;
        });
        child.stderr.on('data', (chunk) => {
            stderr += chunk;
        });
        child.on('close', (code) => {
            try {
                const parsed = JSON.parse(stdout);
                if (code !== 0 || parsed.challenge || parsed.error) {
                    reject(new Error(parsed.error || `challenge=${parsed.challenge} status=${parsed.status} ${stderr}`));
                    return;
                }
                resolvePromise(parsed);
            } catch (error) {
                reject(new Error(`invalid sidecar JSON (exit ${code}): ${stderr || stdout || error}`));
            }
        });
    });
}

function fixtureFolder(slug) {
    return {
        avfan: 'Avfan',
        javlibrary: 'JavLibrary',
        onefouronejav: '141jav',
    }[slug] ?? slug;
}

async function captureOne(targetUrl, relativeOut) {
    const outPath = resolve(root, relativeOut);
    mkdirSync(dirname(outPath), { recursive: true });
    console.error(`capturing ${targetUrl} -> ${relativeOut}`);
    const result = flaresolverrUrl === ''
        ? await runPlaywright(targetUrl, outPath)
        : await runFlaresolverr(targetUrl, outPath);
    if (!existsSync(outPath) && result.html) {
        writeFileSync(outPath, result.html, 'utf8');
    }
    console.error(`ok ${relativeOut} (${result.htmlBytes} bytes, ${result.elapsedMs}ms)`);
    return result;
}

if (manifestMode) {
    const { readdirSync } = await import('node:fs');
    const adapters = join(root, 'src/Adapters');
    const jobs = [];
    for (const dir of readdirSync(adapters)) {
        const manifestPath = join(adapters, dir, 'manifest.json');
        if (!existsSync(manifestPath)) {
            continue;
        }
        const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
        const slug = manifest.slug;
        const samples = manifest.runtime?.fixtureSamples ?? [];
        for (const sample of samples) {
            jobs.push({
                url: sample.url,
                out: `tests/Fixtures/${fixtureFolder(slug)}/${sample.name}`,
            });
        }
    }

    const failures = [];
    for (const job of jobs) {
        try {
            await captureOne(job.url, job.out);
        } catch (error) {
            failures.push(`${job.url}: ${error instanceof Error ? error.message : error}`);
            console.error(`FAIL ${job.url}`);
        }
    }

    if (failures.length > 0) {
        console.error(`Failed ${failures.length} captures:\n${failures.join('\n')}`);
        process.exit(1);
    }
    process.exit(0);
}

if (url === '' || out === '') {
    console.error('Usage: node tools/fixtures/capture.mjs --url=URL --out=tests/Fixtures/site/file.html');
    process.exit(1);
}

await captureOne(url, out);
