import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const source = fs.readFileSync(new URL('../../resources/js/studio/public-session.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { canAnswerPublicSession } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);

test('only a connected running student can send an answer, never a projector', () => {
    assert.equal(canAnswerPublicSession('student', 'running', true, false), true);
    for (const status of ['prepared', 'paused', 'finished', undefined, 'unknown']) {
        assert.equal(canAnswerPublicSession('student', status, true, false), false);
    }
    for (const status of ['prepared', 'running', 'paused', 'finished']) {
        assert.equal(canAnswerPublicSession('projector', status, true, false), false);
    }
    assert.equal(canAnswerPublicSession('student', 'running', false, false), false);
    assert.equal(canAnswerPublicSession('student', 'running', true, true), false);
});
