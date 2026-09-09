#!/usr/bin/env node

import { chromium, firefox, webkit } from 'playwright';
import { readFileSync, writeFileSync, readdirSync, renameSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const DEFAULT_USER_AGENT =
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

const args = process.argv.slice(2);
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
    } else if (arg.startsWith('--out=')) {
        // handled via config.saveHtmlPath
    }
}

function loadConfig() {
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
        waitMs: legacyWaitMs,
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

    const ageSelectors = [
        'button[data-action="over18#accept"]',
        'button:has-text("Yes, I am of legal age")',
        'button:has-text("I am 18")',
        'button:has-text("I\'m 18")',
        'a:has-text("I am 18")',
        'a:has-text("Enter")',
        'button:has-text("Enter")',
        'input[value="I am over 18"]',
        '#age-verify-yes',
        'a:has-text("18歳以上なので進む")',
        'a:has-text("はい（アダルトへ）")',
        'a:has-text("はい（入室する）")',
        'a:has-text("18歳以上です")',
        'a:has(img[alt*="18歳以上"])',
        'button:has-text("はい")',
        'a:has-text("はい")',
    ];

    for (const selector of ageSelectors) {
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

const config = loadConfig();
const url = config.url;
const waitMs = config.waitMs ?? 8000;
const navigationTimeoutMs = config.navigationTimeoutMs ?? 90000;
const viewport = config.viewport ?? { width: 1440, height: 900 };
const userAgent = config.userAgent ?? DEFAULT_USER_AGENT;
const phases = [];

function closePhase(id, label, startMs, status = 'ok') {
    phases.push({
        id,
        label,
        status,
        started_at: new Date(startMs).toISOString(),
        duration_ms: Math.max(0, Date.now() - startMs),
    });
}

let browser = null;
const started = Date.now();

const shutdown = async (signal) => {
    if (browser !== null) {
        await browser.close().catch(() => {});
        browser = null;
    }
    if (signal) {
        process.exit(signal === 'SIGTERM' ? 143 : 130);
    }
};

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));

try {
    const launchStarted = Date.now();
    const launcher = browserType(config.browser ?? 'chromium');
    browser = await launcher.launch({
        headless: config.headless ?? true,
        args: ['--disable-blink-features=AutomationControlled'],
    });
    closePhase('browser_launch', 'Open Playwright browser', launchStarted);

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
    if (config.storageStatePath && existsSync(config.storageStatePath)) {
        contextOptions.storageState = config.storageStatePath;
    }
    if (config.recordVideoDir) {
        contextOptions.recordVideo = {
            dir: config.recordVideoDir,
            size: { width: viewport.width, height: viewport.height },
        };
    }

    const context = await browser.newContext(contextOptions);
    try {
        const hostname = new URL(url).hostname;
        const cookieDomain = hostname.startsWith('www.') ? hostname.slice(3) : hostname;
        await context.addCookies([
            { name: 'over18', value: '18', domain: cookieDomain, path: '/' },
            { name: 'over18', value: '18', domain: '.' + cookieDomain.replace(/^\./, ''), path: '/' },
            { name: 'age', value: 'verified', domain: cookieDomain, path: '/' },
            { name: 'dv', value: '1', domain: cookieDomain, path: '/' },
            { name: 'existmag', value: 'all', domain: cookieDomain, path: '/' },
        ]);
    } catch {
        // ignore
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
    try {
        await page.waitForLoadState('networkidle', { timeout: 30000 });
    } catch {
        // best-effort
    }

    const elapsed = Date.now() - waitStarted;
    if (elapsed < waitMs) {
        await page.waitForTimeout(waitMs - elapsed);
    }
    closePhase('network_idle', 'Wait for network idle / settle', waitStarted);

    const extractStarted = Date.now();
    await page.evaluate('window.scrollTo(0, Math.max(document.body.scrollHeight / 2, 0))');
    await page.waitForTimeout(1000);

    const html = await page.content();
    const finalUrl = page.url();
    const pageTitle = await page.title();
    const cookies = await context.cookies();
    closePhase('extract_html', 'Capture page HTML', extractStarted);

    const challenge = isChallenge(pageTitle, html);
    closePhase(challenge ? 'challenge_detected' : 'load_success', challenge ? 'Challenge page detected' : 'Page load success', Date.now(), challenge ? 'error' : 'ok');

    // A blocked response is diagnostic evidence, not a fixture. Preserve the
    // last known-good capture instead of replacing it with an interstitial.
    if (config.saveHtmlPath && !challenge) {
        writeFileSync(config.saveHtmlPath, html.replace(/[\t ]+$/gm, ''), 'utf8');
    }

    await context.close();
    await browser.close();
    browser = null;

    const artifacts = {};
    if (config.saveHtmlPath && !challenge) {
        artifacts.html = config.saveHtmlPath;
    }
    const videoPath = resolveRecordedVideo(config.recordVideoDir ?? null);
    if (videoPath) {
        artifacts.video = videoPath;
    }

    console.log(
        JSON.stringify({
            status: challenge ? 403 : (response?.status() ?? 200),
            finalUrl,
            pageTitle,
            htmlBytes: html.length,
            html,
            cookies,
            elapsedMs: Date.now() - started,
            challenge,
            artifacts,
            phases,
        }),
    );
} catch (error) {
    if (browser !== null) {
        await browser.close().catch(() => {});
    }

    console.log(
        JSON.stringify({
            error: error instanceof Error ? error.message : String(error),
            status: 1,
            elapsedMs: Date.now() - started,
            challenge: false,
        }),
    );
    process.exit(1);
}
