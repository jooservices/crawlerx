#!/usr/bin/env node

import { readFileSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const args = process.argv.slice(2);
let configPath = '';
let legacyUrl = '';

for (const arg of args) {
    if (arg.startsWith('--config=')) {
        configPath = arg.slice('--config='.length);
    } else if (arg.startsWith('--url=')) {
        legacyUrl = arg.slice('--url='.length);
    }
}

const dir = dirname(fileURLToPath(import.meta.url));
const html = readFileSync(
    join(dir, '../jable/detail-fjin-091.html'),
    'utf8',
);

let finalUrl = 'https://en.jable.tv/videos/fjin-091/';
if (configPath !== '' && existsSync(configPath)) {
    const config = JSON.parse(readFileSync(configPath, 'utf8'));
    finalUrl = config.url ?? finalUrl;
} else if (legacyUrl !== '') {
    finalUrl = legacyUrl;
}

console.log(JSON.stringify({
    status: 200,
    finalUrl,
    html,
    elapsedMs: 10,
    challenge: false,
    artifacts: {},
}));
