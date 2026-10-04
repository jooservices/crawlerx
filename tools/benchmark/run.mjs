#!/usr/bin/env node

import { execFileSync, spawn } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const count = Number.parseInt(process.argv.find((argument) => argument.startsWith('--count='))?.split('=')[1] ?? '3', 10);
const baseUrl = process.env.CRAWLERX_BENCHMARK_BASE_URL ?? 'http://fixture-site:8080';
const methods = (process.argv.find((argument) => argument.startsWith('--methods='))?.split('=')[1]
    ?? 'http,playwright,puppeteer_stealth').split(',').filter(Boolean);
const urls = [
    `${baseUrl}/static/movie/benchmark`,
    `${baseUrl}/shell/movie/benchmark`,
    `${baseUrl}/images/movie/benchmark`,
];
const gitSha = execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim();
const outputDirectory = resolve(root, 'build/benchmark');
mkdirSync(outputDirectory, { recursive: true });
const samples = [];
const active = new Map();

function containerIds() {
    try {
        return execFileSync('docker', ['compose', 'ps', '-q', 'node', 'flaresolverr'], {
            cwd: root,
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'ignore'],
        }).trim().split(/\s+/).filter(Boolean);
    } catch {
        return [];
    }
}

function browserRelaunchCount() {
    try {
        const output = execFileSync('docker', ['compose', 'logs', '--no-color', 'node'], {
            cwd: root,
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'ignore'],
        });
        return (output.match(/"event":"browser_relaunch"/g) ?? []).length;
    } catch {
        return 0;
    }
}

function memoryBytes(value) {
    const match = /^([\d.]+)\s*(B|KiB|MiB|GiB|kB|MB|GB)$/i.exec(value.trim());
    if (!match) return 0;
    const multipliers = { b: 1, kib: 1024, mib: 1024 ** 2, gib: 1024 ** 3, kb: 1000, mb: 1000 ** 2, gb: 1000 ** 3 };
    return Math.round(Number.parseFloat(match[1]) * (multipliers[match[2].toLowerCase()] ?? 1));
}

function stats() {
    const ids = containerIds();
    if (ids.length === 0) return [];
    try {
        const output = execFileSync('docker', ['stats', '--no-stream', '--format', '{{json .}}', ...ids], {
            cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'],
        });
        return output.trim().split('\n').filter(Boolean).map((line) => {
            try {
                const item = JSON.parse(line);
                return {
                    name: String(item.Name ?? ''),
                    cpuPercent: Number.parseFloat(String(item.CPUPerc ?? '').replace('%', '')) || 0,
                    rssBytes: memoryBytes(String(item.MemUsage ?? '').split('/')[0] ?? ''),
                };
            } catch {
                return null;
            }
        }).filter(Boolean);
    } catch {
        return [];
    }
}

function poll() {
    const now = Date.now();
    for (const sample of active.values()) {
        const elapsed = Math.max(0, now - sample.lastAt) / 1000;
        sample.lastAt = now;
        for (const stat of stats()) {
            const key = stat.name.includes('flare') ? 'flaresolverr' : stat.name.includes('node') ? 'node' : null;
            if (key === null) continue;
            const resource = sample.resources[key];
            resource.cpu_seconds += stat.cpuPercent / 100 * elapsed;
            resource.peak_rss_bytes = Math.max(resource.peak_rss_bytes, stat.rssBytes);
        }
    }
}

function execute(url, method, index) {
    return new Promise((resolvePromise) => {
        const id = `${method}:${index}:${url}`;
        const sample = {
            id,
            method,
            url,
            lastAt: Date.now(),
            resources: {
                node: { cpu_seconds: 0, peak_rss_bytes: 0 },
                flaresolverr: { cpu_seconds: 0, peak_rss_bytes: 0 },
            },
        };
        const relaunchesBefore = browserRelaunchCount();
        active.set(id, sample);
        const child = spawn('docker', [
            'compose', 'run', '--rm', '--no-deps', 'php',
            'php', 'tools/benchmark/probe.php', `--url=${url}`, `--method=${method}`,
        ], { cwd: root, stdio: ['ignore', 'pipe', 'pipe'] });
        let stdout = '';
        let stderr = '';
        child.stdout.on('data', (chunk) => { stdout += chunk; });
        child.stderr.on('data', (chunk) => { stderr += chunk; });
        child.on('close', (code) => {
            poll();
            active.delete(id);
            sample.resources.node.relaunches = Math.max(0, browserRelaunchCount() - relaunchesBefore);
            let payload;
            try {
                const lines = stdout.trim().split('\n').filter(Boolean);
                payload = JSON.parse(lines.at(-1) ?? '{}');
            } catch {
                payload = { ok: false, error: stderr.trim() || 'benchmark probe returned invalid JSON' };
            }
            resolvePromise({ ...payload, exit_code: code, resources: sample.resources });
        });
    });
}

const poller = setInterval(poll, 500);
for (const method of methods) {
    for (let index = 1; index <= count; index += 1) {
        for (const url of urls) {
            samples.push(await execute(url, method, index));
        }
    }
}
clearInterval(poller);
poll();

function percentile(values, percentage) {
    if (values.length === 0) return 0;
    const sorted = [...values].sort((a, b) => a - b);
    const index = Math.min(sorted.length - 1, Math.ceil(percentage / 100 * sorted.length) - 1);
    return sorted[Math.max(0, index)];
}

const byMethod = {};
for (const sample of samples) {
    const entry = byMethod[sample.method] ?? { samples: 0, wall_ms: [], php_cpu_seconds: 0, php_peak_rss_bytes: 0, node: { cpu_seconds: 0, peak_rss_bytes: 0, relaunches: 0 }, flaresolverr: { cpu_seconds: 0, peak_rss_bytes: 0 } };
    entry.samples += 1;
    entry.wall_ms.push(sample.wall_ms ?? 0);
    entry.php_cpu_seconds += sample.php_cpu_seconds ?? 0;
    entry.php_peak_rss_bytes = Math.max(entry.php_peak_rss_bytes, sample.php_peak_rss_bytes ?? 0);
    for (const service of ['node', 'flaresolverr']) {
        entry[service].cpu_seconds += sample.resources?.[service]?.cpu_seconds ?? 0;
        entry[service].peak_rss_bytes = Math.max(entry[service].peak_rss_bytes, sample.resources?.[service]?.peak_rss_bytes ?? 0);
    }
    entry.node.relaunches += sample.resources?.node?.relaunches ?? 0;
    byMethod[sample.method] = entry;
}
for (const entry of Object.values(byMethod)) {
    entry.p50_ms = percentile(entry.wall_ms, 50);
    entry.p95_ms = percentile(entry.wall_ms, 95);
    delete entry.wall_ms;
}

const report = {
    schema: 'crawlerx.benchmark.v1',
    generated_at: new Date().toISOString(),
    git_sha: gitSha,
    count_per_method_page: count,
    urls,
    methods,
    samples,
    summary: byMethod,
};
const outputPath = resolve(outputDirectory, `${gitSha}.json`);
writeFileSync(outputPath, `${JSON.stringify(report, null, 2)}\n`, 'utf8');

const lines = [
    `# CrawlerX benchmark (${gitSha})`,
    '',
    `URLs per method/page: ${count}`,
    '',
    '| Method | Samples | p50 ms | p95 ms | PHP CPU-s | PHP peak RSS | Node CPU-s | Node peak RSS | Node relaunches | Flare CPU-s | Flare peak RSS |',
    '| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |',
];
for (const [method, data] of Object.entries(byMethod)) {
    lines.push(`| ${method} | ${data.samples} | ${data.p50_ms} | ${data.p95_ms} | ${data.php_cpu_seconds.toFixed(3)} | ${data.php_peak_rss_bytes} | ${data.node.cpu_seconds.toFixed(3)} | ${data.node.peak_rss_bytes} | ${data.node.relaunches} | ${data.flaresolverr.cpu_seconds.toFixed(3)} | ${data.flaresolverr.peak_rss_bytes} |`);
}
const markdown = `${lines.join('\n')}\n`;
process.stdout.write(`${markdown}\nJSON: ${outputPath}\n`);
