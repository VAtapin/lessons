import http from 'node:http';
import { performance } from 'node:perf_hooks';

// SessionAnswer uses a database integer primary key; sessions/participants use UUIDs.
export function isStoredAnswerId(value) {
    return Number.isSafeInteger(value) && value > 0;
}

export function guardedUrl(value) {
    const url = new URL(value);
    if (url.protocol !== 'http:' || url.hostname !== '127.0.0.1' || !url.port
        || url.username || url.password || url.pathname !== '/' || url.search || url.hash) {
        throw new Error('loopback_url_required');
    }
    return url;
}

export function rememberCookies(jar, headers = []) {
    // Node exposes Set-Cookie as an array: never split on commas (Expires contains commas).
    for (const header of headers) {
        const pair = header.split(';', 1)[0];
        const separator = pair.indexOf('=');
        const name = pair.slice(0, separator);
        const value = pair.slice(separator + 1);
        if (separator < 1 || !/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/.test(name)
            || !/^[\x21-\x3A\x3C-\x7E]*$/.test(value)) {
            throw new Error('invalid_cookie');
        }
        jar.set(name, value);
    }
}

export function metrics(samples) {
    const sorted = [...samples].sort((a, b) => a - b);
    const percentile = (q) => sorted.length ? Number(sorted[Math.ceil(sorted.length * q) - 1].toFixed(2)) : null;
    return { count: sorted.length, p50Ms: percentile(0.5), p95Ms: percentile(0.95), maxMs: percentile(1) };
}

export class Client {
    constructor(url, agent, record, jar = new Map(), csrf = null) {
        this.url = url;
        this.agent = agent;
        this.record = record;
        this.jar = jar;
        this.csrf = csrf;
    }

    async request(path, { method = 'GET', body, expected = 200, category = 'setup', html = false } = {}) {
        if (!path.startsWith('/') || path.startsWith('//') || path.includes('\\')
            || new URL(path, this.url).origin !== this.url.origin) throw new Error('relative_path_required');
        const encoded = body === undefined ? null : JSON.stringify(body);
        const headers = { Accept: html ? 'text/html' : 'application/json', Cookie: [...this.jar].map(([k, v]) => `${k}=${v}`).join('; ') };
        if (encoded !== null) {
            if (!this.csrf) throw new Error('csrf_required');
            headers['Content-Type'] = 'application/json';
            headers['Content-Length'] = Buffer.byteLength(encoded);
            headers['X-CSRF-TOKEN'] = this.csrf;
        }
        const started = performance.now();
        let status = 0;
        let failed = false;
        try {
            const result = await new Promise((resolve, reject) => {
                const request = http.request(new URL(path, this.url), { method, headers, agent: this.agent }, (response) => {
                    status = response.statusCode;
                    if (response.headers['x-lessons-load-test'] !== 'testing/lessons_test/MariaDB10.6') {
                        response.resume();
                        reject(new Error('http_test_guard_required'));
                        return;
                    }
                    try {
                        rememberCookies(this.jar, response.headers['set-cookie']);
                    } catch {
                        response.resume();
                        reject(new Error('invalid_cookie'));
                        return;
                    }
                    const chunks = [];
                    let size = 0;
                    response.on('data', (chunk) => {
                        size += chunk.length;
                        if (size > 2_000_000) response.destroy(new Error('response_too_large'));
                        else chunks.push(chunk);
                    });
                    response.on('error', () => reject(new Error('response_failed')));
                    response.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
                });
                request.setTimeout(60_000, () => request.destroy(new Error('request_timeout')));
                request.on('error', () => reject(new Error('transport_failed')));
                if (encoded !== null) request.write(encoded);
                request.end();
            });
            if (status !== expected) throw new Error(`http_${status}`);
            return html ? result : JSON.parse(result);
        } catch (error) {
            failed = true;
            throw error;
        } finally {
            this.record(category, performance.now() - started, status, failed);
        }
    }

    async bootstrap() {
        const html = await this.request('/ru', { html: true });
        this.csrf = html.match(/<meta\s+name="csrf-token"\s+content="([A-Za-z0-9]+)"\s*\/?>/)?.[1];
        if (!this.csrf || this.jar.size < 2) throw new Error('cookie_session_or_csrf_missing');
    }
}
