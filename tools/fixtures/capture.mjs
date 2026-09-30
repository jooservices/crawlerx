#!/usr/bin/env node

/**
 * Capture real DOM HTML from a live URL using Playwright.
 * Usage:
 *   node tools/fixtures/capture.mjs --url='https://onejav.com/new' --out=tests/Fixtures/onejav/listing-page-1.html
 *   node tools/fixtures/capture.mjs --manifest
 */

import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
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
let targetType = 'listing';
let fetchMethod = 'auto';

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
    } else if (arg.startsWith('--type=')) {
        targetType = arg.slice('--type='.length);
    } else if (arg.startsWith('--fetch-method=')) {
        fetchMethod = arg.slice('--fetch-method='.length);
    }
}

async function runFlaresolverr(targetUrl) {
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
    return {
        status: solution.status ?? 200,
        finalUrl: solution.url ?? targetUrl,
        html,
        htmlBytes: html.length,
        elapsedMs: 0,
        challenge: false,
    };
}

async function runHttp(targetUrl) {
    const started = Date.now();
    const response = await fetch(targetUrl, {
        headers: {
            Accept: 'application/json, text/html;q=0.9, */*;q=0.8',
            'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        },
        redirect: 'follow',
    });
    const html = await response.text();

    return {
        status: response.status,
        finalUrl: response.url,
        html,
        htmlBytes: Buffer.byteLength(html),
        elapsedMs: Date.now() - started,
        challenge: false,
    };
}

function runPlaywright(targetUrl, extraWait = waitMs) {
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

function assertUsableCapture(result, outPath) {
    if (
        result.challenge === true ||
        ! Number.isInteger(result.status) ||
        result.status < 200 ||
        result.status >= 300 ||
        typeof result.html !== 'string' ||
        result.html.trim() === ''
    ) {
        throw new Error(`unusable response: status=${result.status} challenge=${result.challenge === true}`);
    }

    if (outPath.endsWith('.json')) {
        try {
            JSON.parse(result.html);
        } catch {
            throw new Error('unusable response: expected JSON payload');
        }
    }
}

async function fetchWithMethod(method, targetUrl) {
    switch (method) {
        case 'http':
            return runHttp(targetUrl);
        case 'playwright':
            return runPlaywright(targetUrl);
        case 'puppeteer':
            return runPuppeteer(targetUrl);
        case 'flaresolverr':
            if (flaresolverrUrl !== '') {
                return runFlaresolverr(targetUrl);
            }
    }

    throw new Error(`Unsupported or unconfigured fetch method: ${method}`);
}

function captureMethods(outPath) {
    if (fetchMethod !== 'auto') {
        return [fetchMethod];
    }

    const methods = outPath.endsWith('.json')
        ? ['http', 'playwright', 'puppeteer']
        : ['playwright', 'puppeteer'];

    return [
        ...methods,
        ...(flaresolverrUrl === '' ? [] : ['flaresolverr']),
    ];
}

function runPuppeteer(targetUrl, extraWait = waitMs) {
    return new Promise((resolvePromise, reject) => {
        const configPath = join(tmpdir(), `crawlerx-capture-${Date.now()}-${Math.random().toString(16).slice(2)}.json`);
        writeFileSync(configPath, JSON.stringify({
            url: targetUrl,
            waitMs: extraWait,
            headless,
            navigationTimeoutMs: 90000,
        }));

        const child = spawn(process.execPath, [join(root, 'scripts/puppeteer-stealth-fetch.mjs'), `--config=${configPath}`], {
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
                if (code !== 0 || parsed.challenge || parsed.error || typeof parsed.html !== 'string' || parsed.html === '') {
                    reject(new Error(parsed.error || `challenge=${parsed.challenge} status=${parsed.status} ${stderr}`));
                    return;
                }
                resolvePromise(parsed);
            } catch (error) {
                reject(new Error(`invalid Puppeteer JSON (exit ${code}): ${stderr || stdout || error}`));
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

function fixtureOutputPath(relativeOut) {
    const fixturesRoot = resolve(root, 'tests/Fixtures');
    const outPath = resolve(root, relativeOut);
    const extension = outPath.split('.').pop();

    if (!outPath.startsWith(`${fixturesRoot}/`) || !['html', 'json'].includes(extension)) {
        throw new Error('Fixtures must be .html or .json files under tests/Fixtures.');
    }

    return outPath;
}

function writeMetadata(outPath, sourceUrl, type, method, result) {
    const body = readFileSync(outPath);
    const metadata = {
        source_url: sourceUrl,
        final_url: result.finalUrl ?? sourceUrl,
        captured_at: new Date().toISOString(),
        http_status: result.status,
        content_hash: createHash('sha256').update(body).digest('hex'),
        target_type: type,
        fetch_method: method,
        sanitized: false,
    };

    writeFileSync(`${outPath}.meta.json`, `${JSON.stringify(metadata, null, 2)}\n`, 'utf8');
}

async function captureOne(targetUrl, relativeOut, type = targetType) {
    const outPath = resolve(root, relativeOut);
    fixtureOutputPath(relativeOut);
    mkdirSync(dirname(outPath), { recursive: true });
    console.error(`capturing ${targetUrl} -> ${relativeOut}`);
    const attempts = captureMethods(outPath);
    const errors = [];

    for (const method of attempts) {
        try {
            const result = await fetchWithMethod(method, targetUrl);
            assertUsableCapture(result, outPath);
            writeFileSync(outPath, result.html, 'utf8');
            writeMetadata(outPath, targetUrl, type, method, result);
            console.error(`ok ${relativeOut} via ${method} (${result.htmlBytes} bytes, ${result.elapsedMs}ms)`);
            return result;
        } catch (error) {
            errors.push(`${method}: ${error instanceof Error ? error.message : error}`);
        }
    }

    throw new Error(errors.join('; '));
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
                type: sample.type,
            });
        }
    }

    const failures = [];
    for (const job of jobs) {
        try {
            await captureOne(job.url, job.out, job.type);
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
    console.error('Usage: node tools/fixtures/capture.mjs --url=URL --out=tests/Fixtures/site/file.html --type=listing [--fetch-method=auto|http|playwright|puppeteer|flaresolverr]');
    process.exit(1);
}

await captureOne(url, out, targetType);
