import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, chmodSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

test('canary leaves absent service URLs for the PHP .env loader and preserves explicit overrides', () => {
    const root = resolve(import.meta.dirname, '../../..');
    const directory = mkdtempSync(join(tmpdir(), 'crawlerx-canary-test-'));
    const executable = join(directory, 'php');
    writeFileSync(executable, '#!/bin/sh\nprintf "BROWSER=%s FLARE=%s\\n" "${PLAYWRIGHT_URL-unset}" "${FLARESOLVERR_URL-unset}" >&2\n');
    chmodSync(executable, 0o700);
    const environment = { ...process.env, PHP_BIN: executable };
    delete environment.PLAYWRIGHT_URL;
    delete environment.FLARESOLVERR_URL;
    try {
        for (const override of [false, true]) {
            const env = override ? { ...environment, PLAYWRIGHT_URL: 'http://browser.test', FLARESOLVERR_URL: 'http://flare.test' } : environment;
            const result = spawnSync(process.execPath, ['tools/canary/run.mjs', '--site=test'], { cwd: root, env, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
            assert.equal(result.status, 0);
            assert.match(result.stdout, /Pass: 0\/0/);
            assert.equal(result.stderr.trim(), override ? 'BROWSER=http://browser.test FLARE=http://flare.test' : 'BROWSER=unset FLARE=unset');
        }
    } finally {
        rmSync(directory, { recursive: true });
    }
});
