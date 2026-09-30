import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
import QRCode from 'qrcode';
const source = fs.readFileSync(new URL('../../resources/js/studio/runtime.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const runtime = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);
const state = (timer = {}) => ({ id: 'room-one', serverNow: '2026-09-30T10:00:00Z', timer: { status: 'running', endsAt: '2026-09-30T10:01:00Z', remainingSeconds: 60, ...timer }, wave: null });
test('timer uses elapsed monotonic time and the server anchor, clamps expiration', () => {
    assert.equal(runtime.remainingSeconds(state(), 20500), 40);
    assert.equal(runtime.remainingSeconds(state(), 70000), 0);
    assert.equal(runtime.remainingSeconds(state(), -100), 60);
    assert.equal(runtime.formatTimer(7200), '120:00');
});
test('paused timer remains fixed across elapsed time and receives corrected server state', () => {
    assert.equal(runtime.remainingSeconds(state({ status: 'paused', endsAt: null, remainingSeconds: 37 }), 90000), 37);
    assert.equal(runtime.remainingSeconds(state({ endsAt: '2026-09-30T10:00:10Z' }), 0), 10);
});
test('pending retry retains original UUID and revision body', () => {
    const original = runtime.command(7, 'timer.start', { seconds: 60 });
    assert.match(original.commandId, /^[0-9a-f-]{36}$/);
    assert.deepEqual(JSON.parse(JSON.stringify(original)), original);
    assert.deepEqual(runtime.recoverCommand(JSON.stringify(original)), original);
    assert.equal(runtime.recoverCommand('{broken'), undefined);
    assert.equal(runtime.recoverCommand(JSON.stringify({ ...original, action: 'delete' })), undefined);
    assert.notEqual(runtime.command(7, 'timer.start', { seconds: 60 }).commandId, original.commandId);
});
test('stale GET cannot overwrite a command, busy operation or higher revision', () => {
    assert.equal(runtime.acceptProjection(1, 2, false, 4, 5), false);
    assert.equal(runtime.acceptProjection(2, 2, true, 4, 5), false);
    assert.equal(runtime.acceptProjection(2, 2, false, 4, 3), false);
    assert.equal(runtime.acceptProjection(2, 2, false, 4, 4), true);
});
test('detachment needs expected instance and heartbeat; blocked or no handshake stays embedded', () => {
    assert.equal(runtime.connectedControl(null, 1000, 2000), false);
    assert.equal(runtime.connectedControl('instance', 0, 2000), false);
    assert.equal(runtime.matchesControlMessage('instance', { type: 'ready', instance: 'other' }), false);
    assert.equal(runtime.matchesControlMessage('instance', { type: 'ready', instance: 'instance' }), true);
    assert.equal(runtime.matchesControlMessage('instance', { type: 'unknown', instance: 'instance' }), false);
    assert.equal(runtime.matchesControlMessage('instance', { type: 'returnAck', instance: 'other' }), false);
    assert.equal(runtime.matchesControlMessage('instance', { type: 'returnAck', instance: 'instance' }), true);
    assert.equal(runtime.connectedControl('instance', 1000, 7499), true);
    assert.equal(runtime.connectedControl('instance', 1000, 7500), false);
});
test('wave expires against server time and does not replay the seen ID', () => {
    const projection = { ...state(), wave: { id: 'wave-one', expiresAt: '2026-09-30T10:00:06Z' } };
    assert.equal(runtime.waveIsFresh(projection, null), true);
    assert.equal(runtime.waveIsFresh(projection, 'wave-one'), false);
    assert.equal(runtime.waveIsFresh({ ...projection, serverNow: projection.wave.expiresAt }, null), false);
    assert.equal(runtime.waveIsActive({ ...projection, status: 'finished' }), false);
    assert.equal(runtime.waveIsActive({ ...projection, wave: null }), false);
});
test('return closes only on live parent restore or acknowledgement of a pending return', () => {
    assert.equal(runtime.controlReturnConfirmed('instance', { type: 'restore', instance: 'instance' }, false), true);
    assert.equal(runtime.controlReturnConfirmed('instance', { type: 'returnAck', instance: 'instance' }, false), false);
    assert.equal(runtime.controlReturnConfirmed('instance', { type: 'returnAck', instance: 'instance' }, true), true);
    assert.equal(runtime.controlReturnConfirmed('instance', { type: 'returnAck', instance: 'other' }, true), false);
    assert.equal(runtime.controlReturnConfirmed('instance', { type: 'heartbeat', instance: 'instance' }, true), false);
});
test('local QR encoder generates PNG for actual join URL', async () => {
    assert.match(await QRCode.toDataURL('https://lessons.atapin.de/ru/join?code=ABC123'), /^data:image\/png;base64,iVBOR/);
});
