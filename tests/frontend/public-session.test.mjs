import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const source = fs.readFileSync(new URL('../../resources/js/studio/public-session.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { canAnswerPublicSession } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);

test('ordinary tasks need a connected running student, never a projector', () => {
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

test('prepared student can signal readiness/help only, while offline, busy and closed sessions deny it', () => {
    assert.equal(canAnswerPublicSession('student', 'prepared', true, false, 'core.signals'), true);
    for (const type of ['core.free-response', 'core.roles', 'core.single-choice', undefined]) {
        assert.equal(canAnswerPublicSession('student', 'prepared', true, false, type), false);
    }
    for (const mode of ['student', 'projector']) {
        for (const status of ['paused', 'finished', undefined]) {
            assert.equal(canAnswerPublicSession(mode, status, true, false, 'core.signals'), false);
        }
    }
    assert.equal(canAnswerPublicSession('projector', 'prepared', true, false, 'core.signals'), false);
    assert.equal(canAnswerPublicSession('student', 'prepared', false, false, 'core.signals'), false);
    assert.equal(canAnswerPublicSession('student', 'prepared', true, true, 'core.signals'), false);
});
