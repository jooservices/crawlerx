#!/usr/bin/env node

import { chromium, firefox, webkit } from 'playwright';
import { existsSync, readFileSync, readdirSync, renameSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

export const DEFAULT_USER_AGENT =
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const MINIMAL_STEALTH = `
Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
Object.defineProperty(navigator, 'languages', { get: () => ['en-US', 'en'] });
if (!window.chrome) {
    window.chrome = { runtime: {}, loadTimes: function(){}, csi: function(){} };
}
`;

const ENHANCED_STEALTH = MINIMAL_STEALTH + `
Object.defineProperty(navigator, 'plugins', { get: () => [1, 2, 3, 4, 5] });
Object.defineProperty(navigator, 'hardwareConcurrency', { get: () => 8 });
Object.defineProperty(navigator, 'deviceMemory', { get: () => 8 });
Object.defineProperty(navigator, 'maxTouchPoints', { get: () => 0 });
const originalQuery = window.navigator.permissions && window.navigator.permissions.query;
if (originalQuery) {
    window.navigator.permissions.query = (parameters) => (
        parameters && parameters.name === 'notifications'
            ? Promise.resolve({ state: Notification.permission })
            : originalQuery(parameters)
    );
}
`;

function browserType(browserName) {
    switch (browserName) {
        case 'firefox':
            return firefox;
        case 'webkit':
            return webkit;
        default:
            return chromium;
    }
}

function resolveRecordedVideo(dir) {
    if (!dir || !existsSync(dir)) {
        return null;
    }

    const files = readdirSync(dir).filter((name) => name.endsWith('.webm'));
    if (files.length === 0) {
        return null;
    }

    const source = join(dir, files[0]);
    const target = join(dir, 'session.webm');
    if (source !== target) {
        renameSync(source, target);
    }

    return target;
}

async function dismissInterstitials(page, context, targetUrl) {
    try {
        const form = page.locator('#ageVerify form#form1').first();
        if ((await form.count()) > 0 && (await form.isVisible())) {
            const checkbox = form.locator('input[type="checkbox"]').first();
            if ((await checkbox.count()) > 0) {
                await checkbox.check({ timeout: 3000 });
            }
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => null),
                form.evaluate((element) => {
                    const submitValue = document.createElement('input');
                    submitValue.type = 'hidden';
                    submitValue.name = 'Submit';
                    submitValue.value = 'confirm';
                    element.appendChild(submitValue);
                    element.submit();
                }),
            ]);
            await page.waitForTimeout(1500);
            if (page.url().includes('/doc/driver-verify')) {
                await page.goto(targetUrl, { waitUntil: 'domcontentloaded', timeout: 15000 });
            }
        }
    } catch {
        // best-effort JavBus age form submission
    }

    for (const selector of [
        'button[data-action="over18#accept"]',
        'button:has-text("Yes, I am of legal age")',
        'button:has-text("I am 18")',
        'button:has-text("I\'m 18")',
        'a:has-text("I am 18")',
        'a:has-text("Enter")',
        'button:has-text("Enter")',
        'input[value="I am over 18"]',
        '#age-verify-yes',
    ]) {
        try {
            const locator = page.locator(selector).first();
            if ((await locator.count()) > 0 && (await locator.isVisible())) {
                await locator.click({ timeout: 3000 });
                await page.waitForTimeout(1500);
            }
        } catch {
            // best-effort
        }
    }

    try {
        const hostname = new URL(page.url()).hostname;
        const domain = hostname.startsWith('www.') ? hostname.slice(3) : hostname;
        await context.addCookies([
            { name: 'over18', value: '18', domain, path: '/' },
            { name: 'age', value: 'verified', domain, path: '/' },
            { name: 'dv', value: '1', domain, path: '/' },
            { name: 'existmag', value: 'all', domain, path: '/' },
        ]);
    } catch {
        // ignore cookie failures
    }
}

function isChallenge(pageTitle, html) {
    const normalizedTitle = pageTitle.toLowerCase();

    return (
        normalizedTitle.includes('just a moment') ||
        normalizedTitle.includes('age verification javbus') ||
        html.includes('driver-verify') ||
        html.includes('cf-browser-verification') ||
        (html.includes('Just a moment...') && html.includes('challenges.cloudflare.com') && html.length < 20000)
    );
}

function positiveTimeout(value, fallback) {
    const parsed = Number.parseInt(value ?? '', 10);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
}

/**
 * Fetch one page in a caller-owned browser. The context is request scoped and
 * is closed before this function resolves.
 *
 * @param {Record<string, any>} config
 * @param {import('playwright').Browser} browser
 * @returns {Promise<Record<string, any>>}
 */
export async function fetchWithBrowser(config, browser) {
    const started = Date.now();
    const url = config.url;
    const navigationTimeoutMs = positiveTimeout(config.navigationTimeoutMs, 90000);
    const viewport = config.viewport ?? { width: 1440, height: 900 };
    const userAgent = config.userAgent ?? DEFAULT_USER_AGENT;
    const phases = [];
    let context = null;

    const closePhase = (id, label, startMs, status = 'ok') => {
        phases.push({
            id,
            label,
            status,
            started_at: new Date(startMs).toISOString(),
            duration_ms: Math.max(0, Date.now() - startMs),
        });
    };

    try {
        const contextStarted = Date.now();
        const contextOptions = {
            userAgent,
            locale: config.locale ?? 'en-US',
            viewport,
            extraHTTPHeaders: {
                'Accept-Language': 'en-US,en;q=0.9',
                ...(config.extraHttpHeaders ?? {}),
            },
        };

        if (config.timezoneId) {
            contextOptions.timezoneId = config.timezoneId;
        }
        if (config.storageState && typeof config.storageState === 'object') {
            contextOptions.storageState = config.storageState;
        } else if (config.storageStatePath && existsSync(config.storageStatePath)) {
            contextOptions.storageState = config.storageStatePath;
        }
        if (config.recordVideoDir) {
            contextOptions.recordVideo = {
                dir: config.recordVideoDir,
                size: { width: viewport.width, height: viewport.height },
            };
        }

        context = await browser.newContext(contextOptions);
        if (config.blockResources !== false) {
            await context.route('**/*', (route) => {
                const type = route.request().resourceType();
                if (['image', 'font', 'media'].includes(type)) {
                    return route.abort();
                }
                return route.continue();
            });
        }

        try {
            const hostname = new URL(url).hostname;
            const cookieDomain = hostname.startsWith('www.') ? hostname.slice(3) : hostname;
            await context.addCookies([
                { name: 'over18', value: '18', domain: cookieDomain, path: '/' },
                { name: 'over18', value: '18', domain: `.${cookieDomain.replace(/^\./, '')}`, path: '/' },
                { name: 'age', value: 'verified', domain: cookieDomain, path: '/' },
                { name: 'dv', value: '1', domain: cookieDomain, path: '/' },
                { name: 'existmag', value: 'all', domain: cookieDomain, path: '/' },
            ]);
        } catch {
            // ignore age-cookie failures
        }

        const page = await context.newPage();
        closePhase('browser_context', 'Create browser context', contextStarted);

        if (config.stealthEnabled !== false) {
            const script = config.stealthLevel === 'minimal' ? MINIMAL_STEALTH : ENHANCED_STEALTH;
            await page.addInitScript(script);
        }

        const navigationStarted = Date.now();
        const response = await page.goto(url, {
            waitUntil: 'domcontentloaded',
            timeout: navigationTimeoutMs,
        });
        closePhase('navigation', 'Load page (domcontentloaded)', navigationStarted);

        await dismissInterstitials(page, context, url);

        const waitStarted = Date.now();
        const readyMarkers = Array.isArray(config.readyMarkers)
            ? [...new Set(config.readyMarkers.filter((marker) => typeof marker === 'string' && marker !== ''))]
            : [];
        const readyTimeoutMs = Math.min(
            positiveTimeout(config.readyTimeoutMs, 45000),
            positiveTimeout(config.remainingTimeoutMs, 45000),
        );
        let readyMarker = null;
        if (readyMarkers.length > 0) {
            try {
                readyMarker = await Promise.any(readyMarkers.map((marker) => (
                    page.waitForSelector(marker, { state: 'attached', timeout: readyTimeoutMs }).then(() => marker)
                )));
            } catch {
                // The PHP layer performs the final marker validation.
            }
        } else {
            try {
                await page.waitForLoadState('networkidle', { timeout: Math.min(5000, readyTimeoutMs) });
            } catch {
                // best-effort network settle
            }
        }
        closePhase(
            readyMarkers.length > 0 ? 'ready_marker' : 'network_idle',
            readyMarkers.length > 0 ? 'Wait for ready marker' : 'Wait for network idle',
            waitStarted,
            readyMarkers.length > 0 && readyMarker === null ? 'error' : 'ok',
        );

        const extractStarted = Date.now();
        const html = await page.content();
        const finalUrl = page.url();
        const pageTitle = await page.title();
        const cookies = await context.cookies();
        const storageState = await context.storageState();
        const actualUserAgent = await page.evaluate(() => navigator.userAgent);
        closePhase('extract_html', 'Capture page HTML', extractStarted);

        const challenge = isChallenge(pageTitle, html);
        closePhase(
            challenge ? 'challenge_detected' : 'load_success',
            challenge ? 'Challenge page detected' : 'Page load success',
            Date.now(),
            challenge ? 'error' : 'ok',
        );

        if (config.saveHtmlPath && !challenge) {
            writeFileSync(config.saveHtmlPath, html, 'utf8');
        }

        const artifacts = {};
        if (config.saveHtmlPath && !challenge) {
            artifacts.html = config.saveHtmlPath;
        }
        const videoPath = resolveRecordedVideo(config.recordVideoDir ?? null);
        if (videoPath) {
            artifacts.video = videoPath;
        }

        return {
            status: challenge ? 403 : (response?.status() ?? 200),
            finalUrl,
            pageTitle,
            htmlBytes: html.length,
            html,
            cookies,
            storageState,
            userAgent: actualUserAgent,
            elapsedMs: Date.now() - started,
            challenge,
            artifacts,
            phases,
        };
    } catch (error) {
        return {
            error: error instanceof Error ? error.message : String(error),
            status: 1,
            elapsedMs: Date.now() - started,
            challenge: false,
        };
    } finally {
        if (context !== null) {
            await context.close().catch(() => {});
        }
    }
}

function loadConfig(args) {
    let configPath = '';
    let legacyUrl = '';
    let legacyWaitMs = 8000;

    for (const arg of args) {
        if (arg.startsWith('--config=')) {
            configPath = arg.slice('--config='.length);
        } else if (arg.startsWith('--url=')) {
            legacyUrl = arg.slice('--url='.length);
        } else if (arg.startsWith('--wait-ms=')) {
            legacyWaitMs = Number.parseInt(arg.slice('--wait-ms='.length), 10) || 8000;
        }
    }

    if (configPath !== '' && existsSync(configPath)) {
        const parsed = JSON.parse(readFileSync(configPath, 'utf8'));
        if (!parsed.url) {
            throw new Error('Playwright config missing url');
        }
        return parsed;
    }

    if (legacyUrl === '') {
        throw new Error('missing --url or --config');
    }

    return {
        url: legacyUrl,
        readyTimeoutMs: legacyWaitMs,
        browser: 'chromium',
        headless: true,
        navigationTimeoutMs: 90000,
        viewport: { width: 1440, height: 900 },
        locale: 'en-US',
        stealthEnabled: true,
        stealthLevel: 'enhanced',
        userAgent: DEFAULT_USER_AGENT,
    };
}

async function main() {
    const config = loadConfig(process.argv.slice(2));
    const launcher = browserType(config.browser ?? 'chromium');
    const browser = await launcher.launch({
        headless: config.headless ?? true,
        args: ['--disable-blink-features=AutomationControlled'],
    });

    try {
        console.log(JSON.stringify(await fetchWithBrowser(config, browser)));
    } finally {
        await browser.close().catch(() => {});
    }
}

if (process.argv[1] && pathToFileURL(resolve(process.argv[1])).href === import.meta.url) {
    main().catch((error) => {
        console.log(JSON.stringify({
            error: error instanceof Error ? error.message : String(error),
            status: 1,
            challenge: false,
        }));
        process.exitCode = 1;
    });
}
