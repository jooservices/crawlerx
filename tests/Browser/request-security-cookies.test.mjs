import { test } from 'node:test';
import assert from 'node:assert/strict';
import { browserCookiesFromHeader, withoutConfiguredCookies } from '../../scripts/request-security.mjs';

test('configured cookies are parsed for the browser cookie store', () => {
    assert.deepEqual(browserCookiesFromHeader('session=alice; locale=en'), {
        cookies: [
            { name: 'session', value: 'alice' },
            { name: 'locale', value: 'en' },
        ],
        names: ['session', 'locale'],
    });
    assert.deepEqual(browserCookiesFromHeader(undefined), { cookies: [], names: [] });
    assert.throws(() => browserCookiesFromHeader('invalid-cookie'), /invalid_http_cookie/);
});

test('configured cookie names can be removed from cross-origin requests', () => {
    assert.deepEqual(withoutConfiguredCookies({ cookie: 'session=alice; theme=dark' }, ['session']), {
        cookie: 'theme=dark',
    });
    assert.deepEqual(withoutConfiguredCookies({ cookie: 'session=alice' }, ['session']), {});
});
