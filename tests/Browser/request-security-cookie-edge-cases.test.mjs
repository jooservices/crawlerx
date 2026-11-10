import { test } from 'node:test';
import assert from 'node:assert/strict';
import { browserCookiesFromHeader } from '../../scripts/request-security.mjs';

test('configured cookies ignore empty separator segments and validate the rest', () => {
    assert.deepEqual(browserCookiesFromHeader('session=alice; ; locale=en;'), {
        cookies: [
            { name: 'session', value: 'alice' },
            { name: 'locale', value: 'en' },
        ],
        names: ['session', 'locale'],
    });
    assert.throws(() => browserCookiesFromHeader('session=alice; invalid-cookie;'), /invalid_http_cookie/);
});
