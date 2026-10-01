import test from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import { Client, guardedUrl, metrics, rememberCookies } from './client.mjs';

test('load destination rejects production, URL credentials and redirect-like base paths', () => {
    assert.equal(guardedUrl('http://127.0.0.1:8765/').origin, 'http://127.0.0.1:8765');
    for (const value of ['https://lessons.atapin.de/', 'http://localhost:8765/', 'http://127.0.0.1/', 'http://127.0.0.1:8765/other', 'http://user:pass@127.0.0.1:8765/', 'http://127.0.0.1:8765/?redirect=https://example.org']) {
        assert.throws(() => guardedUrl(value), /loopback_url_required/);
    }
});

test('cookie jars preserve Expires commas and equals in values without crossing sessions', () => {
    const first = new Map();
    const second = new Map();
    rememberCookies(first, ['session=opaque==; Expires=Wed, 21 Oct 2026 07:28:00 GMT; HttpOnly', 'XSRF-TOKEN=encoded%3Dvalue; Path=/']);
    rememberCookies(second, ['session=other; HttpOnly']);
    assert.equal(first.get('session'), 'opaque==');
    assert.equal(second.get('session'), 'other');
    assert.equal(first.get('XSRF-TOKEN'), 'encoded%3Dvalue');
    assert.throws(() => rememberCookies(first, ['session=bad\r\nInjected: header']), /invalid_cookie/);
});

test('public performance metrics use nearest-rank percentiles without changing samples', () => {
    const samples = [100, 5, 20, 10];
    assert.deepEqual(metrics(samples), { count: 4, p50Ms: 10, p95Ms: 100, maxMs: 100 });
    assert.deepEqual(samples, [100, 5, 20, 10]);
    assert.deepEqual(metrics([]), { count: 0, p50Ms: null, p95Ms: null, maxMs: null });
});

test('HTTP client refuses a redirect and a response without the test-server guard', async () => {
    const server = http.createServer((_request, response) => {
        response.writeHead(302, { Location: 'https://lessons.atapin.de/' });
        response.end();
    });
    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    const agent = new http.Agent();
    const client = new Client(guardedUrl(`http://127.0.0.1:${server.address().port}/`), agent, () => {});
    try {
        await assert.rejects(client.request('/ru'), /http_test_guard_required/);
    } finally {
        agent.destroy();
        await new Promise((resolve) => server.close(resolve));
    }
});
