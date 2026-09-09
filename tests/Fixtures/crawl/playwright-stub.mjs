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
const dir = dirname(fileURLToPath(import.meta.url));

let fixture = join(dir, '../jable/listing-new-release.html');
if (url.includes('/videos/')) {
    fixture = join(dir, '../jable/detail-fjin-091.html');
}

const html = readFileSync(fixture, 'utf8');
const artifacts = {};

if (config.saveHtmlPath) {
    writeFileSync(config.saveHtmlPath, html, 'utf8');
    artifacts.html = config.saveHtmlPath;
}

if (config.recordVideoDir) {
    mkdirSync(config.recordVideoDir, { recursive: true });
    const videoPath = join(config.recordVideoDir, 'session.webm');
    writeFileSync(videoPath, 'stub-video', 'utf8');
    artifacts.video = videoPath;
}

console.log(
    JSON.stringify({
        status: 200,
        finalUrl: url,
        pageTitle: 'Stub page',
        htmlBytes: html.length,
        html,
        elapsedMs: 10,
        challenge: false,
        artifacts,
        phases: [],
    }),
);
