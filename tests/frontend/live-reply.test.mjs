import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import ts from 'typescript';
const source = fs.readFileSync(new URL('../../resources/js/studio/LiveAnswerCard.vue', import.meta.url), 'utf8');
const actions = source.slice(source.indexOf('function toggleReply()'), source.indexOf('</script>'));
const code = ts.transpileModule(actions, { compilerOptions: { target: ts.ScriptTarget.ES2022 } }).outputText;
function fixture(submitCommand) {
    const calls = [];
    const context = vm.createContext({ props: { disabled: false, answer: { id: 7, revision: 2 }, submitCommand: async (...args) => { calls.push(args); return await submitCommand(); } }, reply: { value: '' }, replyRevision: { value: undefined }, replying: { value: false }, responseTextValid: text => !!text.trim() });
    vm.runInContext(code + '\ntoggleReply();', context);
    context.reply.value = 'Private draft for revision 2';
    return { context, calls };
}

test('actual reply handler retains text after rejected or uncertain delivery and clears only acknowledged text', async () => {
    for (const outcome of [false, undefined, true]) {
        const { context, calls } = fixture(async () => outcome);
        await vm.runInContext('sendReply()', context);
        assert.equal(calls.length, 1);
        assert.equal(calls[0][1].expectedAnswerRevision, 2);
        assert.equal(context.reply.value, outcome === true ? '' : 'Private draft for revision 2');
        assert.equal(context.replying.value, outcome !== true);
    }
});

test('a revised student answer blocks a stale private reply until explicit teacher rebase', async () => {
    const { context, calls } = fixture(async () => true);
    context.props.answer.revision = 3;
    await vm.runInContext('sendReply()', context);
    assert.equal(calls.length, 0);
    assert.equal(context.reply.value, 'Private draft for revision 2');
    context.replyRevision.value = 3; // The visible "use current revision" action.
    await vm.runInContext('sendReply()', context);
    assert.equal(calls[0][1].expectedAnswerRevision, 3);
});

test('a newly typed draft is not erased by acknowledgement of an earlier request', async () => {
    let ack;
    const { context } = fixture(() => new Promise(resolve => { ack = resolve; }));
    const pending = vm.runInContext('sendReply()', context);
    context.reply.value = 'Another draft';
    ack(true);
    await pending;
    assert.equal(context.reply.value, 'Another draft');
    assert.equal(context.replying.value, true);
});
