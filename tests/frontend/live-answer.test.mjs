import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const source = fs.readFileSync(new URL('../../resources/js/studio/live-answer.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { reviewAndPublish, liveAnswer } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);
const answer = () => ({ id: 7, revision: 2, value: { text: 'Original' }, moderation: { status: 'pending', displayText: null, published: false } });

test('one visible publish action approves then publishes the exact acknowledged original with fresh revision', async () => {
    const original = answer();
    let state = { currentStageId: 'priest', answers: [structuredClone(original)] };
    const calls = [];
    await reviewAndPublish(original, 'priest', () => state, async (action, payload) => {
        calls.push([action, payload]);
        if (action === 'answer.moderate') state.answers[0] = { ...state.answers[0], revision: 3, moderation: { status: 'approved', displayText: 'Original', published: false } };
        return true;
    });
    assert.deepEqual(calls.map(item => item[0]), ['answer.moderate', 'answer.publish']);
    assert.equal(calls[1][1].expectedAnswerRevision, 3);
    assert.equal(original.moderation.status, 'pending');
});

test('uncertain approval, revised text, another stage and student replacement never auto-publish', async () => {
    for (const fault of ['failed', 'uncertain', 'edited', 'revision', 'stage', 'removed']) {
        const original = answer();
        let state = { currentStageId: 'priest', answers: [structuredClone(original)] };
        const calls = [];
        await reviewAndPublish(original, 'priest', () => state, async (action) => {
            calls.push(action);
            state.answers[0] = { ...state.answers[0], revision: 3, moderation: { status: 'approved', displayText: 'Original', published: false } };
            if (fault === 'edited') state.answers[0].moderation.displayText = 'Changed by another teacher';
            if (fault === 'revision') state.answers[0].revision = 4;
            if (fault === 'stage') state.currentStageId = 'levite';
            if (fault === 'removed') state.answers = [];
            return fault === 'failed' ? false : fault === 'uncertain' ? undefined : true;
        });
        assert.deepEqual(calls, ['answer.moderate'], fault);
    }
});

test('a reviewed edited answer publishes its reviewed text and dismissed or handled answers do not reappear', async () => {
    const reviewed = { ...answer(), revision: 5, moderation: { status: 'approved', displayText: 'Reviewed wording', published: false } };
    const calls = [];
    await reviewAndPublish(reviewed, 'priest', () => ({ currentStageId: 'priest', answers: [reviewed] }), async (action, payload) => { calls.push([action, payload]); return true; });
    assert.equal(calls.length, 1);
    assert.equal(calls[0][0], 'answer.publish');
    assert.equal(calls[0][1].expectedAnswerRevision, 5);
    assert.equal(liveAnswer([reviewed], new Set()), undefined);
    const pending = answer();
    assert.equal(liveAnswer([pending], new Set()), pending);
    assert.equal(liveAnswer([pending], new Set(['7:2'])), undefined);
    assert.equal(liveAnswer([{ ...pending, revision: 3 }], new Set(['7:2'])).revision, 3);
});
