#!/usr/bin/env node

import { readFileSync, existsSync } from 'node:fs';
import { chromium } from 'playwright';

const args = process.argv.slice(2);
let configPath = '';
for (const arg of args) {
    if (arg.startsWith('--config=')) {
        configPath = arg.slice('--config='.length);
    }
}

if (configPath === '' || !existsSync(configPath)) {
    console.log(JSON.stringify({ error: 'missing --config', status: 1 }));
    process.exit(1);
}

let puppeteer;
try {
    const extra = await import('puppeteer-extra');
    const stealth = await import('puppeteer-extra-plugin-stealth');
    puppeteer = extra.default;
    puppeteer.use(stealth.default());
} catch (error) {
    console.log(JSON.stringify({
        error: 'puppeteer-extra is not installed: ' + (error instanceof Error ? error.message : String(error)),
        status: 1,
        challenge: false,
    }));
    process.exit(1);
}

const config = JSON.parse(readFileSync(configPath, 'utf8'));
const started = Date.now();
const url = config.url;
if (!url) {
    console.log(JSON.stringify({ error: 'missing url', status: 1 }));
    process.exit(1);
}

const browser = await puppeteer.launch({
    headless: config.headless ?? true,
    executablePath: process.env.PUPPETEER_EXECUTABLE_PATH ?? chromium.executablePath(),
    args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-blink-features=AutomationControlled',
    ],
});

try {
    const page = await browser.newPage();
    const hostname = new URL(url).hostname;
    await page.setCookie(
        { name: 'over18', value: '18', domain: hostname, path: '/' },
        { name: 'age', value: 'verified', domain: hostname, path: '/' },
        { name: 'dv', value: '1', domain: hostname, path: '/' },
        { name: 'existmag', value: 'all', domain: hostname, path: '/' },
    );
    if (config.userAgent) {
        await page.setUserAgent(config.userAgent);
    }
    const response = await page.goto(url, {
        waitUntil: 'domcontentloaded',
        timeout: config.navigationTimeoutMs ?? 90000,
    });

    try {
        const ageForm = await page.$('#ageVerify form#form1');
        if (ageForm) {
            const checkbox = await ageForm.$('input[type="checkbox"]');
            if (checkbox) {
                await checkbox.click();
            }
            const submit = await ageForm.$('input[type="submit"], button[type="submit"]');
            if (submit) {
                await Promise.all([
                    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => null),
                    ageForm.evaluate((element) => {
                        const submitValue = document.createElement('input');
                        submitValue.type = 'hidden';
                        submitValue.name = 'Submit';
                        submitValue.value = 'confirm';
                        element.appendChild(submitValue);
                        element.submit();
                    }),
                ]);
                await new Promise((resolve) => setTimeout(resolve, 1500));
                if (page.url().includes('/doc/driver-verify')) {
                    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });
                }
            }
        }
    } catch {
        // best-effort JavBus age form submission
    }

    for (const selector of [
        'button[data-action="over18#accept"]',
        '#age-verify-yes',
        'input[value="I am over 18"]',
    ]) {
        try {
            const element = await page.$(selector);
            if (element) {
                await element.click();
                await new Promise((resolve) => setTimeout(resolve, 1500));
            }
        } catch {
            // best-effort interstitial dismissal
        }
    }

    await new Promise((resolve) => setTimeout(resolve, config.waitMs ?? 8000));
    await page.evaluate(() => window.scrollTo(0, Math.max(document.body.scrollHeight / 2, 0)));
    await new Promise((resolve) => setTimeout(resolve, 1000));
    const html = await page.content();
    const pageTitle = await page.title();
    const normalizedTitle = pageTitle.toLowerCase();
    const challenge =
        normalizedTitle.includes('just a moment') ||
        normalizedTitle.includes('age verification javbus') ||
        html.includes('driver-verify') ||
        html.includes('cf-browser-verification') ||
        (html.includes('Just a moment...') && html.includes('challenges.cloudflare.com') && html.length < 20000);
    const cookies = await page.cookies();

    console.log(JSON.stringify({
        status: challenge ? 403 : (response?.status() ?? 200),
        finalUrl: page.url(),
        pageTitle,
        htmlBytes: html.length,
        html,
        elapsedMs: Date.now() - started,
        challenge,
        cookies,
    }));
} catch (error) {
    console.log(JSON.stringify({
        error: error instanceof Error ? error.message : String(error),
        status: 1,
        elapsedMs: Date.now() - started,
        challenge: false,
    }));
    process.exit(1);
} finally {
    await browser.close();
}
