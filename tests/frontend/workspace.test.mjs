import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const source = fs.readFileSync(new URL('../../resources/js/studio/workspace.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { openWorkspaceSessions } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);

test('workspace continues real open sessions in history order without changing their snapshots', () => {
    const sessions = [{ id: 'ended', status: 'finished', mode: 'lesson' }, { id: 'paused', status: 'paused', mode: 'lesson' }, { id: 'ready', status: 'prepared', mode: 'lesson' }, { id: 'probe', status: 'running', mode: 'rehearsal' }, { id: 'older', status: 'running', mode: 'lesson' }, { id: 'oldest', status: 'running', mode: 'lesson' }];
    const before = JSON.stringify(sessions);
    assert.deepEqual(openWorkspaceSessions(sessions).map(session => session.id), ['paused', 'ready', 'older', 'oldest']);
    assert.equal(openWorkspaceSessions(sessions)[2], sessions[4]);
    assert.equal(JSON.stringify(sessions), before);
    assert.deepEqual(openWorkspaceSessions([]), []);
    assert.deepEqual(openWorkspaceSessions([{ id: 'ended', status: 'finished' }]), []);
});
