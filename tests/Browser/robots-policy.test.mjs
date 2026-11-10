import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { allowsRobotsPath } from '../../scripts/robots-policy.mjs';

test('robots policy selects the most specific user agent group and path rule', () => {
    const policy = [
        'User-agent: *',
        'Disallow: /private',
        '',
        'User-agent: CrawlerX',
        'Disallow: /catalog',
        'Allow: /catalog/public',
    ].join('\n');

    assert.equal(allowsRobotsPath(policy, '/private/page', 'CrawlerX/1.7'), true);
    assert.equal(allowsRobotsPath(policy, '/catalog/private', 'CrawlerX/1.7'), false);
    assert.equal(allowsRobotsPath(policy, '/catalog/public/item', 'CrawlerX/1.7'), true);
    assert.equal(allowsRobotsPath(policy, '/private/page', 'OtherBot/1.0'), false);
});

test('robots wildcard rules preserve prefix, end anchor, and allow precedence', () => {
    const policy = [
        'User-agent: *',
        'Disallow: /private/*/edit',
        'Allow: /private/public/edit',
        'Disallow: /archive/$',
    ].join('\n');

    assert.equal(allowsRobotsPath(policy, '/private/user/edit', 'CrawlerX'), false);
    assert.equal(allowsRobotsPath(policy, '/private/user/edit/extra', 'CrawlerX'), false);
    assert.equal(allowsRobotsPath(policy, '/private/public/edit', 'CrawlerX'), true);
    assert.equal(allowsRobotsPath(policy, '/archive/', 'CrawlerX'), false);
    assert.equal(allowsRobotsPath(policy, '/archive/file', 'CrawlerX'), true);
});

test('robots wildcard matching stays bounded for many wildcard segments', () => {
    const policyModule = new URL('../../scripts/robots-policy.mjs', import.meta.url).href;
    const rule = `/a${'a*'.repeat(20)}b`;
    const policy = `User-agent: *\nDisallow: ${rule}`;
    const path = `/${'a'.repeat(2048)}c`;
    const script = `import { allowsRobotsPath } from ${JSON.stringify(policyModule)};
if (!allowsRobotsPath(${JSON.stringify(policy)}, ${JSON.stringify(path)}, 'CrawlerX')) process.exit(1);`;
    const result = spawnSync(process.execPath, ['--input-type=module', '--eval', script], {
        encoding: 'utf8',
        timeout: 3000,
    });

    assert.equal(result.error, undefined, result.error?.message);
    assert.equal(result.status, 0, result.stderr);
});
