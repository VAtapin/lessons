import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import ts from 'typescript';
import { computed, ref } from 'vue';
const source = fs.readFileSync(new URL('../../resources/js/studio/TeacherPanel.vue', import.meta.url), 'utf8').match(/<script setup lang="ts">([\s\S]*?)<\/script>/)[1];
const syntax = ts.createSourceFile('TeacherPanel.ts', source, ts.ScriptTarget.Latest, true);
const declaration = syntax.statements.find(node => ts.isFunctionDeclaration(node) && node.name?.text === 'detach');
const guard = syntax.statements.find(node => ts.isVariableStatement(node) && node.declarationList.declarations.some(item => ts.isIdentifier(item.name) && item.name.text === 'detachDisabled'));
const code = ts.transpileModule(guard.getText(syntax) + '\n' + declaration.getText(syntax), { compilerOptions: { target: ts.ScriptTarget.ES2022 } }).outputText;
function run({ busy = false, pending, asTab = false } = {}) {
    const opened = [];
    const context = vm.createContext({ computed, busy: ref(busy), pending: ref(pending), detachError: { value: false }, detached: { value: false }, channel: {}, scope: 'owner', props: { locale: 'de', sessionId: 'actual-session' }, crypto: { randomUUID: () => 'fresh-instance' }, activeInstance: null, heartbeatAt: 0, window: { open: (...args) => { opened.push(args); return {}; } }, setTimeout: () => {} });
    vm.runInContext(code + `\ndetach(${asTab});`, context);
    return { opened, context };
}

test('actual detach handler opens neither window nor tab during flight or an unconfirmed command', () => {
    for (const asTab of [false, true]) {
        for (const state of [{ busy: true }, { pending: { commandId: 'captured-stage' } }]) {
            const { opened, context } = run({ ...state, asTab });
            assert.equal(opened.length, 0);
            assert.equal(context.activeInstance, null);
            assert.equal(context.detachError.value, false);
        }
    }
});

test('actual computed guard reevaluates refs and waits for acknowledgement before unblocking', () => {
    const { opened, context } = run({ busy: true, pending: { commandId: 'unconfirmed-stage' } });
    assert.equal(opened.length, 0);
    context.busy.value = false;
    vm.runInContext('detach(false);', context);
    assert.equal(opened.length, 0); // A timed-out request still needs confirmation.
    context.pending.value = undefined;
    vm.runInContext('detach(true);', context);
    assert.equal(opened.length, 1);
    assert.equal(opened[0][0], '/de/control/actual-session?instance=fresh-instance');
});

test('after acknowledgement normal detachment retains its scoped URL and popup versus tab behavior', () => {
    for (const asTab of [false, true]) {
        const { opened, context } = run({ asTab });
        assert.equal(opened.length, 1);
        assert.equal(opened[0][0], '/de/control/actual-session?instance=fresh-instance');
        assert.equal(opened[0][1], '_blank');
        assert.equal(opened[0][2], asTab ? undefined : 'popup,width=520,height=820');
        assert.equal(context.activeInstance, 'fresh-instance');
    }
});
