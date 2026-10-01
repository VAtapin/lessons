import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const compiled = ts.transpileModule(fs.readFileSync(new URL('../../resources/js/catalog/start-session.ts', import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { catalogStartDecision } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);

test('active sessions offer their original identities without starting or changing a snapshot', async () => {
    const page = { sessions: [{ id: 'paused-other-material', lessonVersionId: 'immutable-version', status: 'paused', title: 'Own title', joinCode: 'ABC123' }], nextCursor: 'next30' };
    const before = structuredClone(page);
    let posts = 0;
    const result = await catalogStartDecision(async () => page, async () => { posts++; });
    assert.equal(result.kind, 'resume');
    assert.equal(result.page, page);
    assert.equal(posts, 0);
    assert.deepEqual(page, before);
});

test('starting a fresh session waits for a successful empty owner-scoped active query', async () => {
    const calls = [];
    const response = { session: { id: 'new-real-session' } };
    const result = await catalogStartDecision(async () => { calls.push('GET active'); return { sessions: [], nextCursor: null }; }, async () => { calls.push('POST start'); return response; });
    assert.deepEqual(calls, ['GET active', 'POST start']);
    assert.deepEqual(result, { kind: 'started', result: response });
});

test('preflight failure never silently starts a second session', async () => {
    let posts = 0;
    await assert.rejects(catalogStartDecision(async () => { throw new Error('network'); }, async () => { posts++; }), /network/);
    assert.equal(posts, 0);
});
