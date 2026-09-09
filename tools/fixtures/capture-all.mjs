#!/usr/bin/env node

import { spawn } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { tmpdir } from 'node:os';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const script = join(root, 'scripts/playwright-fetch.mjs');

function fixtureFolder(slug) {
    return { avfan: 'Avfan', javlibrary: 'JavLibrary', onefouronejav: '141jav' }[slug] ?? slug;
}

function runPlaywright(targetUrl, saveHtmlPath, waitMs) {
    return new Promise((resolvePromise) => {
        const configPath = join(tmpdir(), `crawlerx-capture-${Date.now()}-${Math.random().toString(16).slice(2)}.json`);
        writeFileSync(configPath, JSON.stringify({
            url: targetUrl,
            waitMs,
            browser: 'chromium',
            headless: true,
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
        child.stdout.on('data', (c) => { stdout += c; });
        child.stderr.on('data', (c) => { stderr += c; });
        child.on('close', (code) => {
            try {
                const parsed = JSON.parse(stdout);
                resolvePromise({ code, parsed, stderr });
            } catch {
                resolvePromise({ code, parsed: { error: stdout || stderr || `exit ${code}` }, stderr });
            }
        });
    });
}

const jobs = [];
for (const dir of readdirSync(join(root, 'src/Adapters'))) {
    const manifestPath = join(root, 'src/Adapters', dir, 'manifest.json');
    if (!existsSync(manifestPath)) continue;
    const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    for (const sample of manifest.runtime?.fixtureSamples ?? []) {
        jobs.push({
            slug: manifest.slug,
            url: sample.url,
            type: sample.type,
            out: join(root, 'tests/Fixtures', fixtureFolder(manifest.slug), sample.name),
            waitMs: manifest.runtime?.playwrightFetchEnabled ? 10000 : 3000,
        });
    }
}

const report = [];
for (const job of jobs) {
    mkdirSync(dirname(job.out), { recursive: true });
    process.stderr.write(`CAPTURE ${job.slug} ${job.type} ${job.url}\n`);
    const { code, parsed, stderr } = await runPlaywright(job.url, job.out, job.waitMs);
    const challenge = Boolean(parsed.challenge);
    const ok = code === 0 && !challenge && !parsed.error && existsSync(job.out);
    const bytes = parsed.htmlBytes ?? (existsSync(job.out) ? readFileSync(job.out).length : 0);
    const row = {
        slug: job.slug,
        type: job.type,
        url: job.url,
        out: job.out.replace(root + '/', ''),
        ok,
        challenge,
        status: parsed.status ?? code,
        bytes,
        error: parsed.error || (challenge ? 'challenge' : null),
        finalUrl: parsed.finalUrl ?? null,
    };
    report.push(row);
    process.stderr.write(`  -> ${ok ? 'OK' : 'FAIL'} ${bytes} bytes ${row.error ?? ''}\n`);
    if (stderr) process.stderr.write(stderr.slice(0, 400) + '\n');
}

writeFileSync(join(root, 'tools/fixtures/capture-report.json'), JSON.stringify(report, null, 2));
const failed = report.filter((r) => !r.ok);
process.stderr.write(`\nDone. ${report.length - failed.length}/${report.length} ok, ${failed.length} failed\n`);
process.exit(failed.length > 0 ? 1 : 0);
