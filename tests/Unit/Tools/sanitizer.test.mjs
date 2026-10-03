import test from 'node:test';
import assert from 'node:assert/strict';
import { sanitizeHtml } from '../../../tools/fixtures/sanitizer.mjs';

test('TC-SN-02 sanitizer strips seeded secrets and account data', () => {
    const secret = 'synthetic-cookie-value';
    const input = `<meta name="csrf-token" content="${secret}"><div data-account-email="owner@example.test">${secret}</div><p>Set-Cookie: ${secret}</p>`;
    const output = sanitizeHtml(input, [secret]);

    assert.equal(output.includes(secret), false);
    assert.equal(output.includes('owner@example.test'), false);
    assert.match(output, /\[REDACTED\]/);
    assert.match(output, /\[REDACTED_EMAIL\]/);
});

