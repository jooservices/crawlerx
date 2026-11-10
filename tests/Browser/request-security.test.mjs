import { test } from 'node:test';
import assert from 'node:assert/strict';
import { headersForOrigin, splitBrowserHeaders } from '../../scripts/request-security.mjs';

test('browser credentials are injected only for the initial origin', () => {
    const { safe, sensitive } = splitBrowserHeaders({
        Accept: 'text/html',
        Authorization: 'Bearer private-token',
        Cookie: 'session=private-cookie',
        'X-Access-Key': 'private-access-key',
    });

    assert.deepEqual(safe, { Accept: 'text/html' });
    assert.deepEqual(
        headersForOrigin({ accept: 'text/html', cookie: 'domain-scoped-cookie' }, sensitive, true),
        {
            accept: 'text/html',
            Authorization: 'Bearer private-token',
            Cookie: 'session=private-cookie',
            'X-Access-Key': 'private-access-key',
        },
    );
    assert.deepEqual(
        headersForOrigin({ accept: 'text/html', cookie: 'domain-scoped-cookie' }, sensitive, false),
        { accept: 'text/html', cookie: 'domain-scoped-cookie' },
    );
});

test('browser header validation rejects injection and transport-owned headers', () => {
    assert.throws(() => splitBrowserHeaders({ 'X-Name': 'safe\r\nInjected: value' }), /invalid_http_header/);
    assert.throws(() => splitBrowserHeaders({ Host: 'other.example' }), /invalid_http_header/);
    assert.throws(() => splitBrowserHeaders({ 'Bad Name': 'value' }), /invalid_http_header/);
});
