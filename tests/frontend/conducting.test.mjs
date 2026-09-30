import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const source = fs.readFileSync(new URL('../../resources/js/studio/conducting.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { activeStageAnswers, countAnsweredParticipants } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);
test('preview counts people once for active stage and preserves all separate answers', () => {
    const answers = [{ participantId: 'one', blockId: 'first', optionId: 'a' }, { participantId: 'one', blockId: 'second', optionId: 'b' }, { participantId: 'two', blockId: 'first', optionId: 'a' }, { participantId: 'three', blockId: 'another-stage', optionId: 'a' }];
    const stage = { blocks: [{ id: 'first' }, { id: 'second' }] };
    const snapshot = JSON.stringify(answers);
    const visible = activeStageAnswers(answers, stage);
    assert.equal(visible.length, 3);
    assert.equal(countAnsweredParticipants(visible), 2);
    assert.equal(JSON.stringify(answers), snapshot);
    assert.deepEqual(activeStageAnswers(answers, undefined), []);
    assert.equal(countAnsweredParticipants(activeStageAnswers(answers, { blocks: [{ id: 'unknown' }] })), 0);
});
