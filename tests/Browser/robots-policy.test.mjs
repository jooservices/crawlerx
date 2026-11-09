import { test } from 'node:test';
import assert from 'node:assert/strict';
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
