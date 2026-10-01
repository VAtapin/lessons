import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const source = fs.readFileSync(new URL('../../resources/js/studio/collaboration.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { canCommand, captureTeacherCommand, controlChannel, invitationToken, localInterfaceUrl, recoverTeacherCommand, sameTeacherAuthority } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);
const owner = { kind: 'owner', isPresenter: true, capabilities: ['present', 'moderate', 'finish', 'manageCollaboration'] };
const grant = { kind: 'grant', expiresAt: '2026-10-01T20:00:00Z', isPresenter: false, capabilities: ['moderate'] };

test('a moderator can handle answers, roles and signals while presentation and owner governance stay separate', () => {
    for (const action of ['answer.moderate', 'answer.publish', 'answer.unpublish', 'role.assign', 'signal.ack']) assert.equal(canCommand(grant, action), true);
    for (const action of ['stage', 'timer.start', 'wave', 'block.open', 'finish', 'invite.create', 'grant.revoke', 'presenter.reclaim']) assert.equal(canCommand(grant, action), false);
    const presenter = { ...grant, isPresenter: true, capabilities: ['present', 'moderate'] };
    assert.equal(canCommand(presenter, 'stage'), true);
    assert.equal(canCommand(presenter, 'finish'), false);
    const ownerModerating = { ...owner, isPresenter: false, capabilities: ['moderate', 'finish', 'manageCollaboration'] };
    assert.equal(canCommand(ownerModerating, 'finish'), true);
    assert.equal(canCommand(ownerModerating, 'presenter.reclaim'), true);
    assert.equal(canCommand(ownerModerating, 'stage'), false);
    assert.equal(canCommand(undefined, 'wave'), false);
});

test('uncertain commands retain their UUID, actor, revision, epoch and nested payload across authority changes', () => {
    const payload = { nested: { text: 'original' } };
    const pending = captureTeacherCommand(owner, 8, 3, 'message.set', payload);
    payload.nested.text = 'changed';
    assert.equal(pending.command.payload.nested.text, 'original');
    const before = JSON.stringify(pending);
    assert.equal(sameTeacherAuthority(pending, owner, 3), true);
    assert.equal(sameTeacherAuthority(pending, owner, 5), false); // Transfer and reclaim do not revive an earlier epoch.
    assert.equal(sameTeacherAuthority(pending, grant, 3), false);
    assert.equal(sameTeacherAuthority(captureTeacherCommand(grant, 8, 3, 'answer.publish'), { ...grant, expiresAt: '2026-10-02T20:00:00Z' }, 3), false);
    assert.equal(JSON.stringify(pending), before);
});

test('recovery requires a freshly read matching owner epoch and never imports legacy or grant commands', () => {
    const pending = captureTeacherCommand(owner, 8, 3, 'stage', { stageId: 'real-stage' });
    assert.deepEqual(recoverTeacherCommand(JSON.stringify(pending), owner, 3), pending);
    assert.equal(recoverTeacherCommand(JSON.stringify(pending), owner, 4), undefined);
    assert.equal(recoverTeacherCommand(JSON.stringify(pending.command), owner, 3), undefined);
    assert.equal(recoverTeacherCommand(JSON.stringify(pending), grant, 3), undefined);
    assert.equal(recoverTeacherCommand('{invalid', owner, 3), undefined);
    assert.notEqual(controlChannel('session', 'owner'), controlChannel('session', 'grant'));
    assert.notEqual(controlChannel('first', 'grant'), controlChannel('second', 'grant'));
});

test('invitation tokens come only from valid fragment values', () => {
    const token = '01234567-89ab-4cde-8123-456789abcdef';
    assert.equal(invitationToken('#token=' + token), token);
    assert.equal(invitationToken('#token=bad'), undefined);
    assert.equal(invitationToken('#other=' + token), undefined);
    assert.equal(invitationToken(''), undefined);
});

test('interface locale changes only known same-origin URLs and preserves codes, fragments and session IDs', () => {
    const origin = 'https://lessons.example';
    assert.equal(localInterfaceUrl('/ru/join?code=123456', 'de', origin), origin + '/de/join?code=123456');
    assert.equal(localInterfaceUrl(origin + '/ru/project/token', 'de', origin), origin + '/de/project/token');
    assert.equal(localInterfaceUrl('/ru/conduct/session/projector', 'de', origin), origin + '/de/conduct/session/projector');
    assert.equal(localInterfaceUrl('/ru/teacher-invitations#token=abc', 'de', origin), origin + '/de/teacher-invitations#token=abc');
    assert.equal(localInterfaceUrl('https://foreign.example/ru/join', 'de', origin), 'https://foreign.example/ru/join');
    assert.equal(localInterfaceUrl('/ru/studio/private', 'de', origin), '/ru/studio/private');
    assert.equal(localInterfaceUrl('/ru/join', 'unknown', origin), '/ru/join');
    assert.equal(localInterfaceUrl(undefined, 'de', origin), undefined);
});
