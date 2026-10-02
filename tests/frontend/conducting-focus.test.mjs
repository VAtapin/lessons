import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const source = fs.readFileSync(new URL('../../resources/js/studio/conducting-focus.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { focusStageBlocks } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);
const stage = blocks => ({ id: 'stage', content: { title: 'Actual title' }, config: {}, blocks });
const image = id => ({ id, type: 'core.image', resources: { image: '/real/immutable/version' }, content: { alt: 'Actual illustration' } });

test('three classroom frames progress in source order and return without duplicating illustrations', () => {
    const scene = { id: 'scene', type: 'core.presentation', config: { kind: 'scene' }, content: { title: 'Roof', text: 'Four friends' } };
    const frame = (id, title) => ({ id, type: 'core.presentation', config: { kind: 'reveal' }, content: { title, text: title, label: title }, resources: { image: `/${id}.png` }, runtime: { presentation: { visible: true } } });
    const forgiveness = frame('forgiveness', 'Forgiveness');
    const healing = frame('healing', 'Healing');
    const original = stage([scene, image('original'), forgiveness, healing]);
    const focused = focusStageBlocks(original);
    assert.equal(focused.sceneLabels.title, 'Healing');
    assert.equal(focused.illustration.resources.image, '/healing.png');
    for (const id of ['forgiveness', 'healing']) {
        const toggle = focused.copy.find(block => block.id === id);
        assert.equal(toggle.resources, undefined);
        assert.equal(toggle.content.text, '');
        assert.equal(toggle.content.label, id === 'healing' ? 'Healing' : 'Forgiveness');
    }
    healing.runtime.presentation.visible = false;
    assert.equal(focusStageBlocks(original).sceneLabels.title, 'Forgiveness');
    forgiveness.runtime.presentation.visible = false;
    assert.equal(focusStageBlocks(original).sceneLabels.title, 'Roof');
});

test('illustrated reveal switches the scene and image together while preserving the command and tasks', () => {
    const scene = { id: 'intro', type: 'core.presentation', config: { kind: 'scene', scene: 'story' }, content: { title: 'First frame', text: 'Before discussion', modes: [] } };
    const reveal = { id: 'next', type: 'core.presentation', config: { kind: 'reveal' }, content: { title: 'Second frame', text: 'After discussion', source: 'Original source', label: 'Open' }, media: { image: { versionId: 'second' } }, resources: { image: '/second.png' }, runtime: { presentation: { visible: false } } };
    const task = { id: 'task', type: 'core.prompt', content: { text: 'In pairs' } };
    const original = stage([scene, image('first'), reveal, task]);
    assert.equal(focusStageBlocks(original).illustration.resources.image, '/real/immutable/version');
    reveal.runtime.presentation.visible = true;
    const before = structuredClone(original);
    const focused = focusStageBlocks(original);
    assert.equal(focused.illustration.resources.image, '/second.png');
    assert.equal(focused.sceneLabels.title, 'Second frame');
    assert.equal(focused.sceneLabels.text, 'After discussion');
    assert.equal(focused.sceneLabels.source, 'Original source');
    const toggle = focused.copy.find(block => block.id === 'next');
    assert.equal(toggle.content.text, '');
    assert.equal(toggle.resources, undefined);
    assert.equal(toggle.content.label, 'Open');
    assert.equal(toggle.runtime.presentation.visible, true);
    assert.equal(focused.copy.at(-1), task);
    assert.deepEqual(original, before);
    reveal.runtime.presentation.visible = false;
    assert.equal(focusStageBlocks(original).sceneLabels.title, 'First frame');
    assert.equal(focusStageBlocks(original).illustration.resources.image, '/real/immutable/version');
    const multiple = stage([...original.blocks, image('another')]);
    reveal.runtime.presentation.visible = true;
    assert.equal(focusStageBlocks(multiple).copy, multiple.blocks);
});

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

test('a road takes the whole scene and keeps its actual illustration as a backdrop', () => {
    const illustration = image('road');
    const sequence = { id: 'order', type: 'core.sequence', content: { text: 'Original instructions' }, config: {} };
    const original = stage([illustration, sequence]);
    const focused = focusStageBlocks(original);
    assert.equal(focused.journey, true);
    assert.equal(focused.split, false);
    assert.equal(focused.illustration, illustration);
    assert.deepEqual(focused.copy, [sequence]);
    assert.deepEqual(original.blocks, [illustration, sequence]);
});

test('public school scenes retain the complete source material and discussion directions', () => {
    const scene = { id: 'scene', type: 'core.presentation', config: { kind: 'scene' }, content: { title: 'Original school title', text: 'Original situation' } };
    const discussion = { id: 'discussion', type: 'core.presentation', config: { kind: 'discussion' }, content: { text: 'Exact body', modes: [{ modeId: 'help', label: 'Original label', text: 'Original question' }] } };
    const answer = { id: 'words', type: 'core.free-response', config: {}, content: { question: 'Original input question' } };
    const original = stage([image('school'), scene, discussion, answer]);
    const focused = focusStageBlocks(original);
    assert.equal(focused.scenario, true);
    assert.equal(focused.sceneHeading, true);
    assert.deepEqual(focused.copy, [scene, discussion, answer]);
    assert.equal(focused.copy[1], discussion);
});

test('summary uses the whole scene and reserves closing content for the finished screen', () => {
    const summary = { id: 'summary', type: 'core.presentation', config: { kind: 'summary' }, content: { title: 'Original summary', text: 'Original lead' } };
    const closing = { id: 'closing', type: 'core.presentation', config: { kind: 'closing' }, content: { title: 'Original closing', text: 'Original thanks' } };
    const original = stage([image('summary'), summary, closing]);
    const focused = focusStageBlocks(original);
    assert.equal(focused.summary, true);
    assert.equal(focused.split, false);
    assert.equal(focused.sceneHeading, true);
    assert.deepEqual(focused.copy, [summary]);
    assert.equal(original.blocks[2], closing);
});

test('the released scene controls illustration side without removing content or changing stored blocks', () => {
    for (const imageSide of ['left', 'right']) {
        const scene = { id: 'scene', type: 'core.presentation', config: { kind: 'scene', scene: 'scenario', imageSide }, content: { title: 'Original title' } };
        const answer = { id: 'answer', type: 'core.free-response', config: {}, content: { question: 'Original question' } };
        const original = stage([image('scene-image'), scene, answer]);
        const focused = focusStageBlocks(original);
        assert.equal(focused.mediaFirst, imageSide === 'left');
        assert.equal(focused.sceneVariant, 'scenario');
        assert.deepEqual(focused.copy, [scene, answer]);
        assert.equal(original.blocks[1], scene);
    }
});

test('only verbatim repeated scene copy is printed once while distinct pedagogical wording and answer widgets survive', () => {
    const scene = { id: 'scene', type: 'core.presentation', config: { kind: 'scene' }, content: { title: 'Original title', text: 'Exact question' } };
    const question = { id: 'answer', type: 'core.free-response', config: {}, content: { question: 'Exact question', label: 'Answer label' } };
    const discussion = { id: 'discussion', type: 'core.presentation', config: { kind: 'discussion' }, content: { text: 'Exact question', modes: [{ modeId: 'help', text: 'A different prompt' }] } };
    const distinct = { id: 'different', type: 'core.free-response', config: {}, content: { question: 'A different question' } };
    const original = stage([scene, question, discussion, distinct]);
    const before = structuredClone(original);
    const focused = focusStageBlocks(original);
    assert.equal(focused.copy[0], scene);
    assert.equal(focused.copy[1].id, question.id);
    assert.equal(focused.copy[1].content.question, '');
    assert.equal(focused.copy[1].content.label, 'Answer label');
    assert.equal(focused.copy[2].content.text, '');
    assert.equal(focused.copy[2].content.modes[0].text, 'A different prompt');
    assert.equal(focused.copy[3], distinct);
    assert.deepEqual(original, before);
});

test('role actions receive the exact scene labels without synthesizing or modifying source content', () => {
    const content = { title: 'Original role scene', label: 'Original roles heading', subtitle: 'Original invitation', actionLabel: 'Original next action', resetLabel: 'Original reset', restartLabel: 'Original restart', resetText: 'Original reset prompt' };
    const scene = { id: 'scene', type: 'core.presentation', config: { kind: 'scene', scene: 'story' }, content };
    const roles = { id: 'roles', type: 'core.roles', config: {}, content: { roles: [{ roleId: 'first', text: 'Original role' }] } };
    const original = stage([scene, roles]);
    const focused = focusStageBlocks(original);
    assert.equal(focused.sceneLabels, content);
    assert.equal(focused.sceneActionLabel, content.actionLabel);
    assert.equal(focused.copy[1], roles);
    assert.equal(original.blocks[0].content, content);
    assert.equal(focusStageBlocks(stage([roles])).sceneLabels, undefined);
});
