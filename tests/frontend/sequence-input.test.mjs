import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const source = fs.readFileSync(new URL('../../resources/js/studio/sequence-input.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const input = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);
const answerSource = fs.readFileSync(new URL('../../resources/js/studio/interactive.ts', import.meta.url), 'utf8');
const answerCompiled = ts.transpileModule(answerSource, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { reconcileAnswer } = await import(`data:text/javascript;base64,${Buffer.from(answerCompiled).toString('base64')}`);

test('tile clicks assemble a unique answer in learner order without mutating the source', () => {
    const available = ['saw', 'approached', 'helped'];
    let selected = input.appendSequenceItem([], 'helped', available);
    selected = input.appendSequenceItem(selected, 'saw', available);
    assert.deepEqual(selected, ['helped', 'saw']);
    assert.equal(input.appendSequenceItem(selected, 'helped', available), selected);
    assert.equal(input.appendSequenceItem(selected, 'unknown', available), selected);
    assert.deepEqual(available, ['saw', 'approached', 'helped']);
});

test('shuffling preserves every stable item ID and leaves content order unchanged', () => {
    const ids = ['A', 'B', 'C', 'D'];
    const pool = input.shuffledItems(ids, () => 0);
    assert.notDeepEqual(pool, ids);
    assert.deepEqual([...pool].sort(), ids);
    assert.deepEqual(ids, ['A', 'B', 'C', 'D']);
});

test('per-position feedback appears only with an actually revealed server order', () => {
    assert.deepEqual(input.sequencePositions(['B', 'A']), ['ungraded', 'ungraded']);
    assert.deepEqual(input.sequencePositions(['B', 'A', 'C'], ['A', 'B', 'C']), ['incorrect', 'incorrect', 'correct']);
});

test('polling preserves a partially rebuilt road until the matching saved answer is confirmed', () => {
    const draft = { itemIds: ['helped', 'saw'] };
    const previousSaved = { value: { itemIds: ['saw', 'approached', 'helped'] } };
    const polled = reconcileAnswer(draft, true, previousSaved);
    assert.deepEqual(polled.value, draft);
    assert.equal(polled.dirty, true);
    const completed = { itemIds: ['helped', 'saw', 'approached'] };
    const confirmed = reconcileAnswer(completed, true, { value: structuredClone(completed) });
    assert.deepEqual(confirmed.value, completed);
    assert.equal(confirmed.dirty, false);
    assert.notEqual(confirmed.value, completed);
    assert.deepEqual(previousSaved.value.itemIds, ['saw', 'approached', 'helped']);
});
