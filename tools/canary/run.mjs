#!/usr/bin/env node

import { execFileSync, spawn } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const siteArgument = process.argv.find((argument) => argument.startsWith('--site='));
const phpArguments = ['tools/live-check.php', '--canary'];
if (siteArgument !== undefined) {
    phpArguments.push(siteArgument);
}

const gitSha = execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim();
const timestamp = new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d{3}Z$/, 'Z');
const outputDirectory = resolve(root, 'build/canary');
mkdirSync(outputDirectory, { recursive: true });

const current = new Map();
const records = [];

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

function memoryBytes(value) {
    const match = /^([\d.]+)\s*(B|KiB|MiB|GiB|kB|MB|GB)$/i.exec(value.trim());
    if (!match) {
        return 0;
    }

    const multipliers = { b: 1, kib: 1024, mib: 1024 ** 2, gib: 1024 ** 3, kb: 1000, mb: 1000 ** 2, gb: 1000 ** 3 };
    return Math.round(Number.parseFloat(match[1]) * (multipliers[match[2].toLowerCase()] ?? 1));
}

function sampleStats() {
    const ids = containerIds();
    if (ids.length === 0) {
        return [];
    }

    try {
        const output = execFileSync('docker', [
            'stats', '--no-stream', '--format', '{{json .}}', ...ids,
        ], { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
        return output.trim().split('\n').filter(Boolean).map((line) => {
            try {
                const item = JSON.parse(line);
                const memory = String(item.MemUsage ?? '').split('/')[0] ?? '';
                return {
                    name: String(item.Name ?? ''),
                    cpuPercent: Number.parseFloat(String(item.CPUPerc ?? '').replace('%', '')) || 0,
                    memoryBytes: memoryBytes(memory),
                };
            } catch {
                return null;
            }
        }).filter(Boolean);
    } catch {
        return [];
    }
}

function startSample(id) {
    current.set(id, {
        lastAt: Date.now(),
        byContainer: new Map(),
    });
}

function pollStats() {
    const now = Date.now();
    const stats = sampleStats();
    for (const sample of current.values()) {
        const elapsedSeconds = Math.max(0, now - sample.lastAt) / 1000;
        sample.lastAt = now;
        for (const stat of stats) {
            const aggregate = sample.byContainer.get(stat.name) ?? {
                cpuSeconds: 0,
                peakRssBytes: 0,
            };
            aggregate.cpuSeconds += (stat.cpuPercent / 100) * elapsedSeconds;
            aggregate.peakRssBytes = Math.max(aggregate.peakRssBytes, stat.memoryBytes);
            sample.byContainer.set(stat.name, aggregate);
        }
    }
}

function finishSample(record) {
    const sample = current.get(record.id);
    const resources = {
        node: { cpu_seconds: 0, peak_rss_bytes: 0 },
        flaresolverr: { cpu_seconds: 0, peak_rss_bytes: 0 },
    };
    if (sample !== undefined) {
        for (const [name, aggregate] of sample.byContainer.entries()) {
            const key = name.includes('flare') ? 'flaresolverr' : (name.includes('node') ? 'node' : null);
            if (key === null) {
                continue;
            }
            resources[key].cpu_seconds += aggregate.cpuSeconds;
            resources[key].peak_rss_bytes = Math.max(resources[key].peak_rss_bytes, aggregate.peakRssBytes);
        }
        current.delete(record.id);
    }
    record.resources = resources;
    records.push(record);
}

function percentile(values, percentage) {
    if (values.length === 0) {
        return 0;
    }
    const sorted = [...values].sort((left, right) => left - right);
    const index = Math.min(sorted.length - 1, Math.ceil((percentage / 100) * sorted.length) - 1);
    return sorted[Math.max(0, index)];
}

function summary() {
    const statuses = {};
    const methods = {};
    for (const record of records) {
        statuses[record.status] = (statuses[record.status] ?? 0) + 1;
        const method = record.winning_method ?? 'none';
        const aggregate = methods[method] ?? { samples: 0, cpu_seconds: 0, peak_rss_bytes: 0 };
        aggregate.samples += 1;
        aggregate.cpu_seconds += record.resources.node.cpu_seconds + record.resources.flaresolverr.cpu_seconds;
        aggregate.peak_rss_bytes = Math.max(
            aggregate.peak_rss_bytes,
            record.resources.node.peak_rss_bytes,
            record.resources.flaresolverr.peak_rss_bytes,
        );
        methods[method] = aggregate;
    }

    const wallTimes = records.map((record) => record.wall_ms);
    return {
        total: records.length,
        pass: records.filter((record) => record.status === 'ok').length,
        statuses,
        p50_ms: percentile(wallTimes, 50),
        p95_ms: percentile(wallTimes, 95),
        methods,
    };
}

function markdown(report) {
    const lines = [
        `# CrawlerX canary (${report.git_sha})`,
        '',
        `Generated: ${report.generated_at}`,
        '',
        `Pass: ${report.summary.pass}/${report.summary.total}  `,
        `Wall time p50/p95: ${report.summary.p50_ms}/${report.summary.p95_ms} ms`,
        '',
        '| Status | Count |',
        '| --- | ---: |',
    ];
    for (const [status, count] of Object.entries(report.summary.statuses)) {
        lines.push(`| ${status} | ${count} |`);
    }
    lines.push('', '| Method | Samples | CPU-s | Peak RSS |', '| --- | ---: | ---: | ---: |');
    for (const [method, data] of Object.entries(report.summary.methods)) {
        lines.push(`| ${method} | ${data.samples} | ${data.cpu_seconds.toFixed(3)} | ${data.peak_rss_bytes} |`);
    }
    lines.push('', '## Samples', '', '| Site | Page type | Status | Method | Wall ms | Missing fields |', '| --- | --- | --- | --- | ---: | --- |');
    for (const record of report.samples) {
        lines.push(`| ${record.site} | ${record.page_type} | ${record.status} | ${record.winning_method ?? ''} | ${record.wall_ms} | ${(record.missing_fields ?? []).join(', ')} |`);
    }
    return `${lines.join('\n')}\n`;
}

const child = spawn(process.env.PHP_BIN ?? 'php', phpArguments, {
    cwd: root,
    env: {
        ...process.env,
        PLAYWRIGHT_URL: process.env.PLAYWRIGHT_URL ?? 'http://127.0.0.1:3000',
        FLARESOLVERR_URL: process.env.FLARESOLVERR_URL ?? 'http://127.0.0.1:8191/v1',
    },
    stdio: ['ignore', 'pipe', 'inherit'],
});

let buffer = '';
child.stdout.on('data', (chunk) => {
    buffer += chunk.toString();
    const lines = buffer.split('\n');
    buffer = lines.pop() ?? '';
    for (const line of lines) {
        if (line.trim() === '') {
            continue;
        }
        try {
            const payload = JSON.parse(line);
            if (payload.event === 'sample_start') {
                startSample(payload.id);
            } else if (payload.event === 'sample_end') {
                finishSample(payload.record);
            }
        } catch {
            // The canary stream is machine-readable; ignore non-JSON diagnostics.
        }
    }
});

const poller = setInterval(pollStats, 500);
child.on('close', (exitCode) => {
    clearInterval(poller);
    pollStats();
    const report = {
        schema: 'crawlerx.canary.v1',
        generated_at: new Date().toISOString(),
        git_sha: gitSha,
        samples: records,
        summary: summary(),
    };
    const jsonPath = resolve(outputDirectory, `${gitSha}-${timestamp}.json`);
    const markdownPath = resolve(outputDirectory, `${gitSha}-${timestamp}.md`);
    writeFileSync(jsonPath, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
    writeFileSync(markdownPath, markdown(report), 'utf8');
    process.stdout.write(`${markdown(report)}\nJSON: ${jsonPath}\nMarkdown: ${markdownPath}\n`);
    process.exitCode = exitCode === 0 && report.summary.pass === report.summary.total ? 0 : 1;
});
