#!/usr/bin/env node

import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { chromium } from 'playwright';
import { fetchWithBrowser } from './playwright-fetch.mjs';
import { assertSafeBrowserUrl, SsrfBlockedError } from './ssrf-guard.mjs';

const root = resolve(import.meta.dirname, '..');
const port = Number.parseInt(process.env.CRAWLERX_BROWSER_SERVICE_PORT ?? '3000', 10);
const maxRequestBytes = 1024 * 1024;
const maxConcurrency = positiveInteger(process.env.CRAWLERX_BROWSER_MAX_CONCURRENCY, 2);
const maxQueue = nonNegativeInteger(process.env.CRAWLERX_BROWSER_MAX_QUEUE, 8);
const requestTimeoutMs = positiveInteger(process.env.CRAWLERX_BROWSER_REQUEST_TIMEOUT_MS, 120000);
const maxBrowserRequests = positiveInteger(process.env.CRAWLERX_BROWSER_MAX_REQUESTS, 200);
const maxBrowserRssMb = positiveInteger(process.env.CRAWLERX_BROWSER_MAX_RSS_MB, 0);
const playwrightVersion = readPackageVersion('playwright-core');
const chromiumRevision = readChromiumRevision();
const crawlerxVersion = process.env.CRAWLERX_VERSION ?? 'dev';

const jobs = [];
let inFlight = 0;
let stopping = false;
let server;
let shutdownStarted = false;
let browser = null;
let browserLaunchPromise = null;
let requestsSinceLaunch = 0;
let relaunchCount = 0;
let consecutiveLaunchFailures = 0;
let fatalLaunchFailures = false;
let hasLaunchedBrowser = false;
let launchStatus = { ok: false, error: 'browser launch has not been checked' };

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
    const chromiumBrowser = browsersJson.browsers?.find((candidate) => candidate.name === 'chromium');
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

function respondBlocked(response) {
    respond(response, 200, {
        exitCode: 1,
        stderr: 'ssrf_blocked',
        stdout: JSON.stringify({ error: 'ssrf_blocked', errorCode: 'ssrf_blocked', status: 403, challenge: false }),
    });
}

function logEvent(event, fields = {}) {
    // Keep operational events JSON-only and never include request config/cookies.
    process.stdout.write(`${JSON.stringify({ event, ...fields })}\n`);
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

function executePuppeteer(config) {
    return new Promise((resolvePromise) => {
        const directory = mkdtempSync(join(tmpdir(), 'crawlerx-browser-'));
        const configPath = join(directory, 'config.json');
        writeFileSync(configPath, JSON.stringify(config));
        const script = join(root, 'scripts/puppeteer-stealth-fetch.mjs');
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

async function closeBrowser(reason) {
    const current = browser;
    browser = null;
    requestsSinceLaunch = 0;
    if (current !== null) {
        logEvent('browser_close', { reason, relaunch_count: relaunchCount });
        await current.close().catch(() => {});
    }
}

async function launchBrowser(relaunch = false) {
    if (browser !== null) {
        return browser;
    }
    if (browserLaunchPromise !== null) {
        return browserLaunchPromise;
    }

    browserLaunchPromise = (async () => {
        try {
            const launched = await chromium.launch({
                headless: true,
                ...(process.env.CRAWLERX_BROWSER_EXECUTABLE_PATH
                    ? { executablePath: process.env.CRAWLERX_BROWSER_EXECUTABLE_PATH }
                    : {}),
                args: ['--disable-blink-features=AutomationControlled', '--proxy-bypass-list=<-loopback>'],
            });
            browser = launched;
            hasLaunchedBrowser = true;
            requestsSinceLaunch = 0;
            consecutiveLaunchFailures = 0;
            launchStatus = { ok: true, error: null };
            if (relaunch) {
                relaunchCount += 1;
            }
            logEvent(relaunch ? 'browser_relaunch' : 'browser_launch', {
                relaunch_count: relaunchCount,
                max_requests: maxBrowserRequests,
            });
            launched.on('disconnected', () => {
                if (browser !== launched) {
                    return;
                }
                browser = null;
                requestsSinceLaunch = 0;
                launchStatus = { ok: false, error: 'browser disconnected' };
                logEvent('browser_crash', { relaunch_count: relaunchCount });
            });
            return launched;
        } catch (error) {
            consecutiveLaunchFailures += 1;
            launchStatus = {
                ok: false,
                error: error instanceof Error ? error.message : String(error),
            };
            logEvent('browser_launch_failed', { consecutive_failures: consecutiveLaunchFailures });
            if (consecutiveLaunchFailures >= 3) {
                fatalLaunchFailures = true;
                logEvent('browser_fatal', { consecutive_failures: consecutiveLaunchFailures });
                setImmediate(() => process.exit(1));
            }
            throw error;
        } finally {
            browserLaunchPromise = null;
        }
    })();

    return browserLaunchPromise;
}

async function browserForRequest() {
    if (fatalLaunchFailures) {
        throw new Error('browser launch failed three consecutive times');
    }

    const rssMb = process.memoryUsage().rss / (1024 * 1024);
    const needsRelaunch = browser !== null && (
        requestsSinceLaunch >= maxBrowserRequests ||
        (maxBrowserRssMb > 0 && rssMb >= maxBrowserRssMb)
    );
    if (needsRelaunch) {
        await closeBrowser(maxBrowserRssMb > 0 && rssMb >= maxBrowserRssMb ? 'rss_limit' : 'max_requests');
        return launchBrowser(true);
    }

    return launchBrowser(browser !== null ? false : hasLaunchedBrowser);
}

async function execute(scriptName, config) {
    if (scriptName === 'puppeteer') {
        return executePuppeteer(config);
    }

    let currentBrowser;
    try {
        currentBrowser = await browserForRequest();
    } catch (error) {
        return {
            exitCode: 1,
            stderr: error instanceof Error ? error.message : String(error),
            stdout: '',
            browserUnavailable: true,
        };
    }

    requestsSinceLaunch += 1;
    let timer;
    let timedOut = false;
    const operation = fetchWithBrowser(config, currentBrowser).then((payload) => ({
        exitCode: payload.error && !payload.html ? 1 : 0,
        stderr: payload.error && !payload.html ? payload.error : '',
        stdout: JSON.stringify(payload),
        timedOut: false,
    }));
    const timeout = new Promise((resolvePromise) => {
        timer = setTimeout(async () => {
            timedOut = true;
            await closeBrowser('request_timeout');
            resolvePromise({
                exitCode: 124,
                stderr: 'browser request timed out',
                stdout: '',
                timedOut: true,
            });
        }, requestTimeoutMs);
    });
    const result = await Promise.race([operation, timeout]);
    clearTimeout(timer);
    if (timedOut) {
        operation.catch(() => {});
    }
    return result;
}

function healthPayload() {
    return {
        ok: launchStatus.ok && !fatalLaunchFailures,
        playwright_version: playwrightVersion,
        chromium_revision: chromiumRevision,
        browser_launch_ok: launchStatus.ok,
        crawlerx_version: crawlerxVersion,
        in_flight: inFlight,
        queued: jobs.length,
        browser_requests: requestsSinceLaunch,
        relaunch_count: relaunchCount,
        browser_max_requests: maxBrowserRequests,
        browser_max_rss_mb: maxBrowserRssMb,
        rss_mb: Math.round((process.memoryUsage().rss / (1024 * 1024)) * 100) / 100,
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
        relaunch_count: relaunchCount,
        rss_mb: Math.round((process.memoryUsage().rss / (1024 * 1024)) * 100) / 100,
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
        if (result.browserUnavailable) {
            respond(job.response, 503, { error: 'browser unavailable' });
            return;
        }
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
    void closeBrowser('shutdown');
    server.close(() => finishShutdown());
    finishShutdown();
    logEvent('shutdown', { signal });
}

async function handleRequest(request, response) {
    const requestUrl = new URL(request.url ?? '/', `http://${request.headers.host ?? 'localhost'}`);

    if (request.method === 'GET' && requestUrl.pathname === '/health') {
        if (requestUrl.searchParams.get('deep') === '1') {
            await launchBrowser().catch(() => {});
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
    request.on('end', async () => {
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
            try {
                await assertSafeBrowserUrl(targetUrl);
            } catch (error) {
                if (error instanceof SsrfBlockedError) {
                    respondBlocked(response);
                    return;
                }
                throw error;
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
    await launchBrowser().catch(() => {});
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
