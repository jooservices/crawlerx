#!/usr/bin/env node

import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { chromium } from 'playwright';

const root = resolve(import.meta.dirname, '..');
const port = Number.parseInt(process.env.CRAWLERX_BROWSER_SERVICE_PORT ?? '3000', 10);
const maxRequestBytes = 1024 * 1024;
const maxConcurrency = positiveInteger(process.env.CRAWLERX_BROWSER_MAX_CONCURRENCY, 2);
const maxQueue = nonNegativeInteger(process.env.CRAWLERX_BROWSER_MAX_QUEUE, 8);
const requestTimeoutMs = positiveInteger(process.env.CRAWLERX_BROWSER_REQUEST_TIMEOUT_MS, 120000);
const playwrightVersion = readPackageVersion('playwright-core');
const chromiumRevision = readChromiumRevision();
const crawlerxVersion = process.env.CRAWLERX_VERSION ?? 'dev';

const jobs = [];
let inFlight = 0;
let stopping = false;
let server;
let shutdownStarted = false;
let launchStatus = { ok: false, error: 'browser launch has not been checked' };
let launchCheck = null;

function positiveInteger(value, fallback) {
    const parsed = Number.parseInt(value ?? '', 10);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
}

function nonNegativeInteger(value, fallback) {
    const parsed = Number.parseInt(value ?? '', 10);
    return Number.isInteger(parsed) && parsed >= 0 ? parsed : fallback;
}

function readPackageVersion(packageName) {
    const packagePath = join(root, 'node_modules', packageName, 'package.json');
    const packageJson = JSON.parse(readFileSync(packagePath, 'utf8'));
    return typeof packageJson.version === 'string' ? packageJson.version : 'unknown';
}

function readChromiumRevision() {
    const browsersPath = join(root, 'node_modules', 'playwright-core', 'browsers.json');
    if (!existsSync(browsersPath)) {
        return null;
    }

    const browsersJson = JSON.parse(readFileSync(browsersPath, 'utf8'));
    const chromiumBrowser = browsersJson.browsers?.find((browser) => browser.name === 'chromium');
    return typeof chromiumBrowser?.revision === 'string' ? chromiumBrowser.revision : null;
}

function respond(response, status, body) {
    if (response.writableEnded) {
        return;
    }

    response.writeHead(status, {
        'Cache-Control': 'no-store',
        'Content-Type': 'application/json',
    });
    response.end(JSON.stringify(body));
}

function killProcessTree(child) {
    if (!child.pid) {
        return;
    }

    try {
        process.kill(-child.pid, 'SIGTERM');
    } catch {
        child.kill('SIGTERM');
    }

    const forceKill = setTimeout(() => {
        try {
            process.kill(-child.pid, 'SIGKILL');
        } catch {
            try {
                child.kill('SIGKILL');
            } catch {
                // The child already exited.
            }
        }
    }, 250);
    forceKill.unref();

    return forceKill;
}

function execute(scriptName, config) {
    return new Promise((resolvePromise) => {
        const directory = mkdtempSync(join(tmpdir(), 'crawlerx-browser-'));
        const configPath = join(directory, 'config.json');
        writeFileSync(configPath, JSON.stringify(config));
        const script = scriptName === 'puppeteer'
            ? join(root, 'scripts/puppeteer-stealth-fetch.mjs')
            : join(root, 'scripts/playwright-fetch.mjs');
        const child = spawn(process.execPath, [script, `--config=${configPath}`], {
            cwd: root,
            detached: true,
            stdio: ['ignore', 'pipe', 'pipe'],
        });
        let stdout = '';
        let stderr = '';
        let timedOut = false;
        let settled = false;
        let forceKill;
        const timeout = setTimeout(() => {
            timedOut = true;
            forceKill = killProcessTree(child);
        }, requestTimeoutMs);

        child.stdout.on('data', (chunk) => { stdout += chunk; });
        child.stderr.on('data', (chunk) => { stderr += chunk; });
        child.on('error', (error) => {
            if (settled) {
                return;
            }
            settled = true;
            clearTimeout(timeout);
            if (forceKill !== undefined) {
                clearTimeout(forceKill);
            }
            rmSync(directory, { recursive: true, force: true });
            resolvePromise({
                exitCode: 1,
                stderr: error instanceof Error ? error.message : String(error),
                stdout,
                timedOut,
            });
        });
        child.on('close', (code) => {
            if (settled) {
                return;
            }
            settled = true;
            clearTimeout(timeout);
            if (forceKill !== undefined) {
                clearTimeout(forceKill);
            }
            rmSync(directory, { recursive: true, force: true });
            resolvePromise({
                exitCode: timedOut ? 124 : code ?? 1,
                stderr: timedOut && stderr === '' ? 'browser request timed out' : stderr,
                stdout,
                timedOut,
            });
        });
    });
}

async function checkBrowserLaunch() {
    if (launchCheck !== null) {
        return launchCheck;
    }

    launchCheck = (async () => {
        let browser;
        try {
            browser = await chromium.launch({ headless: true });
            launchStatus = { ok: true, error: null };
        } catch (error) {
            launchStatus = {
                ok: false,
                error: error instanceof Error ? error.message : String(error),
            };
        } finally {
            if (browser !== undefined) {
                await browser.close().catch(() => {});
            }
        }
        return launchStatus;
    })().finally(() => {
        launchCheck = null;
    });

    return launchCheck;
}

function healthPayload() {
    return {
        ok: launchStatus.ok,
        playwright_version: playwrightVersion,
        chromium_revision: chromiumRevision,
        browser_launch_ok: launchStatus.ok,
        crawlerx_version: crawlerxVersion,
        in_flight: inFlight,
        queued: jobs.length,
    };
}

function logRequest(config, script, receivedAt, startedAt, queuedAt, result) {
    let host = '';
    try {
        host = new URL(config.url).host;
    } catch {
        // Request validation handles malformed URLs before execution.
    }

    process.stderr.write(`${JSON.stringify({
        host,
        script,
        ms: Date.now() - receivedAt,
        exit_code: result.exitCode,
        queued_ms: queuedAt === null ? 0 : startedAt - queuedAt,
    })}\n`);
}

function finishShutdown() {
    if (stopping && inFlight === 0 && jobs.length === 0) {
        process.exitCode = 0;
    }
}

function rejectQueuedJobs() {
    while (jobs.length > 0) {
        const job = jobs.shift();
        respond(job.response, 503, { error: 'shutting down' });
    }
}

function startJob(job, queuedAt = null) {
    inFlight += 1;
    const startedAt = Date.now();

    execute(job.script, job.config).then((result) => {
        logRequest(job.config, job.script, job.receivedAt, startedAt, queuedAt, result);
        if (result.timedOut) {
            respond(job.response, 504, {
                error: 'browser request timed out',
                exitCode: result.exitCode,
                stderr: result.stderr,
                stdout: result.stdout,
            });
            return;
        }
        respond(job.response, 200, result);
    }).catch((error) => {
        const result = {
            exitCode: 1,
            stderr: error instanceof Error ? error.message : String(error),
            stdout: '',
        };
        logRequest(job.config, job.script, job.receivedAt, startedAt, queuedAt, result);
        respond(job.response, 500, { error: result.stderr });
    }).finally(() => {
        inFlight -= 1;
        while (!stopping && inFlight < maxConcurrency && jobs.length > 0) {
            const nextJob = jobs.shift();
            startJob(nextJob, nextJob.queuedAt);
        }
        finishShutdown();
    });
}

function enqueue(job) {
    if (inFlight < maxConcurrency) {
        startJob(job);
        return;
    }

    if (jobs.length >= maxQueue) {
        respond(job.response, 429, { error: 'busy' });
        return;
    }

    jobs.push({ ...job, queuedAt: Date.now() });
}

function beginShutdown(signal) {
    if (shutdownStarted) {
        return;
    }
    shutdownStarted = true;
    stopping = true;
    rejectQueuedJobs();
    server.close(() => finishShutdown());
    finishShutdown();
    process.stderr.write(`${JSON.stringify({ event: 'shutdown', signal })}\n`);
}

async function handleRequest(request, response) {
    const requestUrl = new URL(request.url ?? '/', `http://${request.headers.host ?? 'localhost'}`);

    if (request.method === 'GET' && requestUrl.pathname === '/health') {
        if (requestUrl.searchParams.get('deep') === '1') {
            await checkBrowserLaunch();
        }
        const payload = healthPayload();
        respond(response, payload.ok ? 200 : 503, payload);
        return;
    }

    if (request.method !== 'POST' || requestUrl.pathname !== '/fetch') {
        respond(response, 404, { error: 'not found' });
        return;
    }

    if (stopping) {
        respond(response, 503, { error: 'shutting down' });
        return;
    }

    let body = '';
    let oversized = false;
    request.on('data', (chunk) => {
        if (oversized) {
            return;
        }
        body += chunk;
        if (Buffer.byteLength(body) > maxRequestBytes) {
            oversized = true;
            respond(response, 413, { error: 'request body too large' });
        }
    });
    request.on('end', () => {
        if (oversized) {
            return;
        }
        try {
            const payload = JSON.parse(body);
            const targetUrl = payload.config?.url;
            let validUrl = false;
            try {
                validUrl = ['http:', 'https:'].includes(new URL(targetUrl).protocol);
            } catch {
                validUrl = false;
            }
            if (!validUrl || !['playwright', 'puppeteer'].includes(payload.script)) {
                respond(response, 422, { error: 'invalid browser request' });
                return;
            }
            enqueue({
                config: payload.config,
                receivedAt: Date.now(),
                response,
                script: payload.script,
            });
        } catch (error) {
            respond(response, 500, { error: error instanceof Error ? error.message : String(error) });
        }
    });
}

async function start() {
    await checkBrowserLaunch();
    server = createServer((request, response) => {
        handleRequest(request, response).catch((error) => {
            respond(response, 500, { error: error instanceof Error ? error.message : String(error) });
        });
    });
    process.on('SIGTERM', () => beginShutdown('SIGTERM'));
    process.on('SIGINT', () => beginShutdown('SIGINT'));
    server.listen(port, '0.0.0.0', () => {
        process.stderr.write(`CrawlerX browser service listening on ${port}\n`);
    });
}

start().catch((error) => {
    process.stderr.write(`${error instanceof Error ? error.stack ?? error.message : String(error)}\n`);
    process.exitCode = 1;
});
