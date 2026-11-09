export const DEFAULT_MAX_RESPONSE_BYTES = 10 * 1024 * 1024;
const MAX_CONFIGURED_RESPONSE_BYTES = 100 * 1024 * 1024;

export class ResponseBodyTooLargeError extends Error {
    constructor() {
        super('response_too_large');
        this.name = 'ResponseBodyTooLargeError';
        this.code = 'response_too_large';
    }
}

export function configuredMaxResponseBytes(value = process.env.CRAWLERX_MAX_RESPONSE_BYTES) {
    const parsed = typeof value === 'number'
        ? value
        : typeof value === 'string' && /^\d+$/.test(value.trim())
            ? Number(value.trim())
            : NaN;

    return Number.isSafeInteger(parsed) && parsed > 0 && parsed <= MAX_CONFIGURED_RESPONSE_BYTES
        ? parsed
        : DEFAULT_MAX_RESPONSE_BYTES;
}

export function assertResponseBodyWithinLimit(body, maximumBytes) {
    if (Buffer.byteLength(body, 'utf8') > maximumBytes) {
        throw new ResponseBodyTooLargeError();
    }
}
