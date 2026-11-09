const invalidHeaderName = /[^!#$%&'*+.^_`|~0-9A-Za-z-]/;
const invalidHeaderValue = /[\x00-\x08\x0A-\x1F\x7F]/;
const sensitiveHeaderName = /authorization|cookie|token|api[_-]?key|access[_-]?key|private[_-]?key|client[_-]?key|(?:^|[-_])key(?:$|[-_])|pass(?:word)?|secret|sig(?:nature)?|jwt|session|sid|credential|clearance|(?:^|[-_])auth(?:$|[-_])/i;
const forbiddenHeaderNames = new Set([
    'connection',
    'content-length',
    'host',
    'keep-alive',
    'proxy-authenticate',
    'proxy-authorization',
    'proxy-connection',
    'te',
    'trailer',
    'transfer-encoding',
    'upgrade',
]);

export function splitBrowserHeaders(headers) {
    if (headers === null || typeof headers !== 'object' || Array.isArray(headers)) {
        throw new TypeError('invalid_http_header');
    }

    const safe = {};
    const sensitive = {};
    for (const [name, value] of Object.entries(headers)) {
        if (
            name === ''
            || invalidHeaderName.test(name)
            || forbiddenHeaderNames.has(name.toLowerCase())
            || typeof value !== 'string'
            || invalidHeaderValue.test(value)
        ) {
            throw new TypeError('invalid_http_header');
        }

        if (sensitiveHeaderName.test(name)) {
            sensitive[name] = value;
        } else {
            safe[name] = value;
        }
    }

    return { safe, sensitive };
}

export function headersForOrigin(requestHeaders, sensitiveHeaders, sameOrigin) {
    const headers = { ...requestHeaders };
    if (!sameOrigin) {
        return headers;
    }

    for (const [name, value] of Object.entries(sensitiveHeaders)) {
        for (const existing of Object.keys(headers)) {
            if (existing.toLowerCase() === name.toLowerCase()) {
                delete headers[existing];
            }
        }
        headers[name] = value;
    }

    return headers;
}

export function browserCookiesFromHeader(value) {
    if (value === undefined || value === '') {
        return { cookies: [], names: [] };
    }

    const cookies = [];
    for (const part of value.split(';')) {
        const separator = part.indexOf('=');
        if (separator < 1) {
            throw new TypeError('invalid_http_cookie');
        }

        const name = part.slice(0, separator).trim();
        const cookieValue = part.slice(separator + 1).trim();
        if (name === '' || invalidHeaderName.test(name) || invalidHeaderValue.test(cookieValue)) {
            throw new TypeError('invalid_http_cookie');
        }
        cookies.push({ name, value: cookieValue });
    }

    return { cookies, names: cookies.map(({ name }) => name) };
}

export function withoutConfiguredCookies(requestHeaders, configuredNames) {
    const headers = { ...requestHeaders };
    if (configuredNames.length === 0) {
        return headers;
    }

    const cookieHeader = Object.keys(headers).find((name) => name.toLowerCase() === 'cookie');
    if (cookieHeader === undefined) {
        return headers;
    }

    const configured = new Set(configuredNames);
    const remaining = headers[cookieHeader]
        .split(';')
        .map((part) => part.trim())
        .filter((part) => {
            const separator = part.indexOf('=');
            return separator < 1 || !configured.has(part.slice(0, separator).trim());
        });

    if (remaining.length === 0) {
        delete headers[cookieHeader];
    } else {
        headers[cookieHeader] = remaining.join('; ');
    }

    return headers;
}
