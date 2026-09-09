#!/usr/bin/env node

import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { tmpdir } from 'node:os';

const root = resolve(import.meta.dirname, '..');
const port = Number.parseInt(process.env.CRAWLERX_BROWSER_SERVICE_PORT ?? '3000', 10);
const maxRequestBytes = 1024 * 1024;

function respond(response, status, body) {
    response.writeHead(status, { 'Content-Type': 'application/json' });
    response.end(JSON.stringify(body));
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
            stdio: ['ignore', 'pipe', 'pipe'],
        });
        let stdout = '';
        let stderr = '';
        child.stdout.on('data', (chunk) => { stdout += chunk; });
        child.stderr.on('data', (chunk) => { stderr += chunk; });
        child.on('close', (code) => {
            rmSync(directory, { recursive: true, force: true });
            resolvePromise({ exitCode: code ?? 1, stdout, stderr });
        });
    });
}

createServer((request, response) => {
    if (request.method === 'GET' && request.url === '/health') {
        respond(response, 200, { ok: true });
        return;
    }

    if (request.method !== 'POST' || request.url !== '/fetch') {
        respond(response, 404, { error: 'not found' });
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
            respond(response, 200, await execute(payload.script, payload.config));
        } catch (error) {
            respond(response, 500, { error: error instanceof Error ? error.message : String(error) });
        }
    });
}).listen(port, '0.0.0.0', () => {
    process.stderr.write(`CrawlerX browser service listening on ${port}\n`);
});
