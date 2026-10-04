#!/usr/bin/env node

import { readdirSync, readFileSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const packageJsonPath = join(root, 'package.json');
const packageLockPath = join(root, 'package-lock.json');
const allowedManifestNames = new Set(['package.json', 'package-lock.json']);
const skippedDirectories = new Set(['.git', 'node_modules', 'vendor', 'coverage']);
const semverPattern = /(?<![\w.])v?\d+\.\d+\.\d+(?![\w.])/g;

function packageLockPlaywrightVersion() {
    const lock = JSON.parse(readFileSync(packageLockPath, 'utf8'));
    const version = lock.packages?.['node_modules/playwright-core']?.version;
    if (typeof version !== 'string' || version === '') {
        throw new Error('package-lock.json does not contain node_modules/playwright-core.version');
    }
    return version;
}

function packagePlaywrightVersion() {
    const packageJson = JSON.parse(readFileSync(packageJsonPath, 'utf8'));
    const version = packageJson.dependencies?.playwright;
    if (typeof version !== 'string' || version === '') {
        throw new Error('package.json does not contain dependencies.playwright');
    }
    return version;
}

function isTextCandidate(filePath) {
    const name = filePath.toLowerCase();
    return name.endsWith('.json') || name.endsWith('.md') || name.endsWith('.mjs') ||
        name.endsWith('.js') || name.endsWith('.yml') || name.endsWith('.yaml') ||
        name.endsWith('.xml') || name.endsWith('.php') || name.endsWith('.sh') ||
        name.endsWith('.toml') || name.endsWith('.txt') || name.endsWith('dockerfile') ||
        name.includes('dockerfile');
}

function collectFiles(directory) {
    const files = [];
    for (const entry of readdirSync(directory, { withFileTypes: true })) {
        if (entry.isDirectory() && skippedDirectories.has(entry.name)) {
            continue;
        }

        const entryPath = join(directory, entry.name);
        if (entry.isDirectory()) {
            files.push(...collectFiles(entryPath));
        } else if (entry.isFile() && isTextCandidate(entryPath)) {
            files.push(entryPath);
        }
    }
    return files;
}

function findPlaywrightVersionLiterals() {
    const findings = [];
    for (const filePath of collectFiles(root)) {
        if (allowedManifestNames.has(filePath.split('/').pop())) {
            continue;
        }

        const content = readFileSync(filePath, 'utf8');
        for (const match of content.matchAll(semverPattern)) {
            const index = match.index ?? 0;
            const context = content.slice(Math.max(0, index - 96), Math.min(content.length, index + 96));
            if (!/playwright/i.test(context)) {
                continue;
            }

            const line = content.slice(0, index).split('\n').length;
            findings.push(`${relative(root, filePath)}:${line}: ${match[0]}`);
        }
    }
    return findings;
}

function suppliedUrl() {
    const explicit = process.argv.slice(2).find((argument) => argument.startsWith('--url='));
    if (explicit !== undefined) {
        return explicit.slice('--url='.length);
    }

    const positional = process.argv.slice(2).find((argument) => !argument.startsWith('--'));
    return positional ?? null;
}

async function checkRunningHealth(expectedVersion, url) {
    const healthUrl = new URL(url);
    if (healthUrl.pathname === '' || healthUrl.pathname === '/') {
        healthUrl.pathname = '/health';
    }

    const response = await fetch(healthUrl, { signal: AbortSignal.timeout(5000) });
    const payload = await response.json();
    if (!response.ok) {
        throw new Error(`sidecar health returned HTTP ${response.status}`);
    }
    if (payload.playwright_version !== expectedVersion) {
        throw new Error(
            `sidecar Playwright version ${payload.playwright_version ?? 'missing'} does not match lockfile ${expectedVersion}`,
        );
    }
}

async function main() {
    const lockVersion = packageLockPlaywrightVersion();
    const declaredVersion = packagePlaywrightVersion();
    if (declaredVersion !== lockVersion) {
        throw new Error(`package.json Playwright version ${declaredVersion} does not match lockfile ${lockVersion}`);
    }

    const findings = findPlaywrightVersionLiterals();
    if (findings.length > 0) {
        throw new Error(`Playwright version literals found outside package manifests:\n${findings.join('\n')}`);
    }

    const url = suppliedUrl();
    if (url !== null) {
        await checkRunningHealth(lockVersion, url);
    }

    process.stdout.write(`Playwright version guard passed: ${lockVersion}\n`);
}

main().catch((error) => {
    process.stderr.write(`${error instanceof Error ? error.message : String(error)}\n`);
    process.exitCode = 1;
});
