#!/usr/bin/env node

import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

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

const config = JSON.parse(readFileSync(configPath, 'utf8'));
const url = config.url ?? '';
const html = '<html><title>Just a moment</title><body>challenges.cloudflare.com</body></html>';
const artifacts = {};

if (config.saveHtmlPath) {
    writeFileSync(config.saveHtmlPath, html, 'utf8');
    artifacts.html = config.saveHtmlPath;
}

if (config.recordVideoDir) {
    mkdirSync(config.recordVideoDir, { recursive: true });
    const videoPath = join(config.recordVideoDir, 'session.webm');
    writeFileSync(videoPath, 'stub-challenge-video', 'utf8');
    artifacts.video = videoPath;
}

console.log(
    JSON.stringify({
        status: 403,
        finalUrl: url,
        pageTitle: 'Just a moment',
        htmlBytes: html.length,
        html,
        elapsedMs: 42,
        challenge: true,
        artifacts,
        phases: [
            {
                id: 'browser_launch',
                label: 'Open Playwright browser',
                status: 'ok',
                started_at: new Date(Date.now() - 40).toISOString(),
                duration_ms: 5,
            },
            {
                id: 'challenge_detected',
                label: 'Challenge page detected',
                status: 'error',
                started_at: new Date(Date.now() - 2).toISOString(),
                duration_ms: 2,
            },
        ],
    }),
);
