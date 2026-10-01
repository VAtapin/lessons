import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const source = fs.readFileSync(new URL('../../resources/js/studio/conducting-focus.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { focusStageBlocks } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);
const stage = blocks => ({ id: 'stage', content: { title: 'Actual title' }, config: {}, blocks });
const image = id => ({ id, type: 'core.image', resources: { image: '/real/immutable/version' }, content: { alt: 'Actual illustration' } });

test('welcome uses the full image background while written discussion places the image beside the real input', () => {
    const welcome = focusStageBlocks(stage([image('welcome'), { type: 'core.text' }, { type: 'core.signals' }]));
    assert.equal(welcome.cover, true);
    assert.equal(welcome.illustration.resources.image, '/real/immutable/version');
    const discussion = focusStageBlocks(stage([image('priest'), { type: 'core.free-response' }]));
    assert.equal(discussion.cover, false);
    assert.equal(discussion.mediaFirst, true);
    assert.equal(discussion.copy[0].type, 'core.free-response');
});

test('focus composes one real illustration with all material and tasks in their original relative order', () => {
    const blocks = [image('scene'), { id: 'intro', type: 'core.text', content: { text: 'Real introduction' } }, { id: 'question', type: 'core.single-choice', runtime: { status: 'open' } }, { id: 'signal', type: 'core.signals' }];
    const before = JSON.stringify(blocks);
    const result = focusStageBlocks(stage(blocks));
    assert.equal(result.split, true);
    assert.equal(result.illustration, blocks[0]);
    assert.deepEqual(result.copy.map(block => block.id), ['intro', 'question', 'signal']);
    assert.equal(result.copy[1], blocks[2]);
    assert.equal(JSON.stringify(blocks), before);
});

test('text-only and multiple-image stages retain every block and order without synthesizing pictures or tasks', () => {
    for (const blocks of [[{ id: 'prompt', type: 'core.prompt' }, { id: 'roles', type: 'core.roles' }], [image('first'), { id: 'text', type: 'core.text' }, image('second')]]) {
        const result = focusStageBlocks(stage(blocks));
        assert.equal(result.split, false);
        assert.equal(result.illustration, undefined);
        assert.equal(result.copy, blocks);
    }
});

test('an illustration-only stage remains a full canvas with its original version', () => {
    const illustration = image('only');
    const result = focusStageBlocks(stage([illustration]));
    assert.equal(result.split, false);
    assert.equal(result.copy[0], illustration);
    assert.equal(result.copy[0].resources.image, '/real/immutable/version');
});

test('a same-stage response board displays published replies once without mutating the projected source or own input', () => {
    const response = { id: 'promise', type: 'core.free-response', config: {}, content: { question: 'This week I can help' }, runtime: { status: 'open', results: { published: [{ text: 'Real promise' }], totalAnswers: 1 } } };
    const board = { id: 'board', type: 'core.presentation', config: { kind: 'response-board', sourceBlockIds: ['promise'] }, runtime: { board: [{ answerId: 7, text: 'Real promise', discussed: true }] } };
    const source = stage([response, board]);
    const before = structuredClone(source);
    const display = focusStageBlocks(source).copy;
    assert.equal(display[0].runtime.results, undefined);
    assert.equal(display[0].runtime.status, 'open');
    assert.equal(display[0].content, response.content);
    assert.equal(display[1], board);
    assert.deepEqual(display[1].runtime.board, board.runtime.board);
    assert.deepEqual(source, before);
    assert.notEqual(display[0], response);
});

test('ordinary publication and boards for other sources keep the free-response results visible', () => {
    const response = { id: 'current', type: 'core.free-response', config: {}, runtime: { results: { published: [{ text: 'Ordinary reply' }] } } };
    const elsewhere = { id: 'other-board', type: 'core.presentation', config: { kind: 'response-board', sourceBlockIds: ['previous-stage-answer'] } };
    for (const blocks of [[response], [response, elsewhere]]) {
        const display = focusStageBlocks(stage(blocks)).copy;
        assert.equal(display[0], response);
        assert.equal(display[0].runtime.results.published[0].text, 'Ordinary reply');
    }
});
