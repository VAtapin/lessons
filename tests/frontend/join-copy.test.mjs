import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import ts from 'typescript';
const source = fs.readFileSync(new URL('../../resources/js/studio/TeacherPanel.vue', import.meta.url), 'utf8');
const handler = source.slice(source.indexOf('async function copyJoinLink()'), source.indexOf('const returnUrl'));
const code = ts.transpileModule(handler, { compilerOptions: { target: ts.ScriptTarget.ES2022 } }).outputText;
function fixture(native, asynchronous, url = 'https://lessons.atapin.de/ru/join?code=TEST1234') {
    const calls = [];
    const context = vm.createContext({ copied: { value: false }, joinUrl: { value: url }, error: { value: '' }, props: { messages: { copy_link_manual: 'Copy the selected link manually' } },
        joinField: { value: { focus: () => calls.push('focus'), select: () => calls.push('select') } },
        document: { execCommand: command => { calls.push(command); return native(); } },
        navigator: { clipboard: { writeText: async text => { calls.push(text); await asynchronous(); } } } });
    vm.runInContext(code, context);
    return { context, calls, copy: () => vm.runInContext('copyJoinLink()', context) };
}
test('native copying selects the actual join field before reporting success', async () => {
    const { context, calls, copy } = fixture(() => true, () => assert.fail('unnecessary asynchronous copy'));
    await copy();
    assert.deepEqual(calls, ['focus', 'select', 'copy']);
    assert.equal(context.copied.value, true);
});
test('unavailable native copy falls back to the exact current join URL', async () => {
    for (const native of [() => false, () => { throw new Error('unsupported'); }]) {
        const { context, calls, copy } = fixture(native, async () => {});
        await copy();
        assert.equal(calls.at(-1), context.joinUrl.value);
        assert.equal(context.copied.value, true);
    }
});
test('denied clipboard keeps the selected link and gives manual instructions without false success', async () => {
    const { context, calls, copy } = fixture(() => false, async () => { throw new Error('denied'); });
    await copy();
    assert.deepEqual(calls.slice(0, 2), ['focus', 'select']);
    assert.equal(context.copied.value, false);
    assert.equal(context.error.value, context.props.messages.copy_link_manual);
});
test('an unavailable join URL never overwrites the clipboard', async () => {
    const { context, calls, copy } = fixture(() => true, async () => {}, '');
    await copy();
    assert.deepEqual(calls, []);
    assert.equal(context.copied.value, false);
});
