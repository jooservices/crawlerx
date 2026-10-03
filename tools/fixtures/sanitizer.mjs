import { existsSync, readFileSync } from 'node:fs';

const TOKEN_NAME = '(?:remember_token|csrf(?:token)?|authenticity_token|xsrf-token|session(?:_id)?)';

/**
 * Remove owner/session data before a response is written as a fixture.
 * @param {string} html
 * @param {string[]} secrets
 * @returns {string}
 */
export function sanitizeHtml(html, secrets = []) {
    let sanitized = html;
    for (const secret of secrets) {
        if (secret !== '') {
            sanitized = sanitized.split(secret).join('[REDACTED]');
        }
    }

    return sanitized
        .replace(/set-cookie\s*[:=][^<\r\n]*/gi, 'set-cookie: [REDACTED]')
        .replace(new RegExp(`(${TOKEN_NAME}\\s*[=:]\\s*)[^&;\\s"'<]+`, 'gi'), '$1[REDACTED]')
        .replace(/(data-(?:account|user|username|email|owner)(?:-name)?\s*=\s*["'])[^"']+(["'])/gi, '$1[REDACTED]$2')
        .replace(/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/g, '[REDACTED_EMAIL]');
}

/** @returns {Record<string, string>} */
export function loadDotEnv(path) {
    if (!existsSync(path)) {
        return {};
    }

    const values = {};
    for (const rawLine of readFileSync(path, 'utf8').split(/\r?\n/)) {
        const line = rawLine.trim();
        if (line === '' || line.startsWith('#')) {
            continue;
        }
        const source = line.startsWith('export ') ? line.slice(7) : line;
        const separator = source.indexOf('=');
        if (separator < 1) {
            continue;
        }
        const name = source.slice(0, separator).trim();
        let value = source.slice(separator + 1).trim();
        if (value.length >= 2 && ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'")))) {
            value = value.slice(1, -1);
        }
        values[name] = value;
    }
    return values;
}

export function cookieForSite(site, values) {
    const name = `CRAWLERX_COOKIE_${site.replaceAll('-', '_').toUpperCase()}`;
    const value = (values[name] ?? '').trim();
    return value === '' ? null : value;
}

