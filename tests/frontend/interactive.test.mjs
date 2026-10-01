import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const compile = file => ts.transpileModule(fs.readFileSync(new URL('../../resources/js/studio/' + file, import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const url = source => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const interactive = await import(url(compile('interactive.ts')));
const moderation = await import(url(compile('moderation.ts')));
const libraryUrl = url(compile('library.ts'));
const document = await import(url(compile('document.ts').replace("'./library'", JSON.stringify(libraryUrl))));
const runtime = await import(url(compile('runtime.ts')));
const messages = { template_text: 'Text', template_question: 'Question', template_option_first: 'First', template_option_second: 'Second' };

test('every editor block has its schema defaults and independent translated collections', () => {
    for (const type of interactive.blockTypes) {
        const block = document.newBlock(type, ['ru', 'de'], messages);
        assert.equal(block.schemaVersion, type === 'core.text' ? 2 : 1);
        assert.deepEqual(block.content.ru, block.content.de);
        assert.equal('resources' in block, false);
        assert.equal('teacherNotes' in block, false);
        const group = ['options', 'items', 'left', 'right', 'roles'].find(key => block.content.ru[key]);
        if (group) {
            assert.notEqual(block.content.ru[group], block.content.de[group]);
            block.content.ru[group][0].text = 'Changed';
            assert.equal(block.content.de[group][0].text, 'First');
        }
        assert.notEqual(document.newBlock(type, ['ru'], messages).id, block.id);
    }
    assert.deepEqual(document.newBlock('core.text', ['ru'], messages).config, { presentation: 'paragraphs' });
    assert.deepEqual(document.newBlock('core.signals', ['ru'], messages).config, {});
    const roles = document.newBlock('core.roles', ['ru'], messages);
    assert.deepEqual(Object.keys(roles.config.capacities), roles.content.ru.roles.map(role => role.roleId));
});

test('answer validation uses stable IDs, complete bijections and Unicode length', () => {
    const sequence = { type: 'core.sequence', config: {}, content: { items: [{ itemId: 'A' }, { itemId: 'b' }] } };
    assert.equal(interactive.answerComplete(sequence, { itemIds: ['b', 'A'] }), true);
    assert.equal(interactive.answerComplete(sequence, { itemIds: ['A', 'A'] }), false);
    assert.equal(interactive.answerComplete(sequence, { itemIds: ['A', 'B'] }), false);
    const matching = { type: 'core.matching', config: {}, content: { left: [{ itemId: 'a' }, { itemId: 'b' }], right: [{ itemId: 'x' }, { itemId: 'y' }] } };
    assert.equal(interactive.answerComplete(matching, { pairs: [{ leftId: 'a', rightId: 'y' }, { leftId: 'b', rightId: 'x' }] }), true);
    assert.equal(interactive.answerComplete(matching, { pairs: [{ leftId: 'a', rightId: 'y' }, { leftId: 'b', rightId: 'y' }] }), false);
    assert.equal(interactive.answerComplete(matching, { pairs: [{ leftId: 'a', rightId: 'y' }, { leftId: 'a', rightId: 'x' }] }), false);
    const free = { type: 'core.free-response', config: { maxLength: 2 }, content: {} };
    assert.equal(interactive.responseTextValid('😀😀', 2), true);
    assert.equal(interactive.responseTextValid('😀😀😀', 2), false);
    assert.equal(interactive.answerComplete(free, { text: '😀😀' }), true);
    assert.equal(interactive.answerComplete(free, { text: '😀😀😀' }), false);
    assert.equal(interactive.answerComplete(free, { text: ' \n ' }), false);
});

test('student polling preserves unsent input; normalized confirmation ends dirty state', () => {
    const value = { optionIds: ['b', 'a'] };
    assert.deepEqual(interactive.reconcileAnswer(value, true, { value: { optionIds: ['a'] } }), { value, dirty: true });
    assert.deepEqual(interactive.reconcileAnswer(value, true, undefined), { value, dirty: true });
    const confirmed = interactive.reconcileAnswer(value, true, { value: { optionIds: ['a', 'b'] } });
    assert.equal(confirmed.dirty, false);
    assert.deepEqual(confirmed.value, { optionIds: ['a', 'b'] });
    assert.equal(interactive.sameValue({ itemIds: ['a', 'b'] }, { itemIds: ['b', 'a'] }), false);
    assert.equal(interactive.sameValue({ pairs: [{ leftId: 'b', rightId: 'x' }, { leftId: 'a', rightId: 'y' }] }, { pairs: [{ leftId: 'a', rightId: 'y' }, { leftId: 'b', rightId: 'x' }] }), true);
});

test('moderation conflict retains edited display and captured revision without changing original', () => {
    const answer = { id: 4, revision: 1, value: { text: 'Original <b>text</b>' }, moderation: { status: 'pending', displayText: null, published: false } };
    const before = JSON.stringify(answer);
    const draft = { ...moderation.initialModeration(answer), text: 'Display', dirty: true };
    const changed = { ...answer, revision: 2, value: { text: 'New original' } };
    assert.equal(moderation.reconcileModeration(draft, changed), draft);
    assert.deepEqual(moderation.moderationPayload(4, draft, 'approved'), { answerId: 4, expectedAnswerRevision: 1, status: 'approved', displayText: 'Display' });
    assert.deepEqual(moderation.moderationPayload(4, draft, 'rejected'), { answerId: 4, expectedAnswerRevision: 1, status: 'rejected' });
    assert.equal(JSON.stringify(answer), before);
    const approved = { ...answer, revision: 2, moderation: { status: 'approved', displayText: 'Display', published: false } };
    assert.deepEqual(moderation.reconcileModeration(draft, approved), { text: 'Display', revision: 2, dirty: false });
});

test('preview strips private notes and solutions without rewriting legacy text blocks', () => {
    const block = { id: 'legacy', type: 'core.text', schemaVersion: 1, content: { ru: { text: 'Public' } }, config: { format: 'plain' }, media: {}, solution: null, teacherNotes: { ru: 'Secret' } };
    const snapshot = JSON.stringify(block);
    const projected = document.projectStage({ id: 'stage', content: { ru: { title: 'Stage', notes: 'Private' } }, config: {}, blocks: [block] }, 'ru');
    assert.equal(projected.blocks[0].teacherNotes, undefined);
    assert.equal(projected.blocks[0].solution, undefined);
    assert.equal(projected.content.notes, undefined);
    assert.equal(JSON.stringify(block), snapshot);
    assert.equal(projected.blocks[0].schemaVersion, 1);
});

test('new commands retain pending UUID and revision through reload for safe retry', () => {
    for (const action of ['block.open', 'block.close', 'block.reveal', 'answer.moderate', 'answer.publish', 'answer.unpublish', 'role.assign', 'signal.ack']) {
        const command = runtime.command(8, action, { answerId: 4, expectedAnswerRevision: 2 });
        assert.deepEqual(runtime.recoverCommand(JSON.stringify(command)), command);
    }
});

test('both interface dictionaries contain renderer and editor labels, including optional text heading', () => {
    const folder = new URL('../../resources/js/studio/', import.meta.url);
    for (const locale of ['ru', 'de']) {
        const dictionary = ['studio', 'deletion', 'admin', 'collaboration', 'wave']
            .map(group => new URL(`../../lang/${locale}/${group}.php`, import.meta.url))
            .filter(file => fs.existsSync(file)).map(file => fs.readFileSync(file, 'utf8')).join('\n');
        const keys = new Set([...dictionary.matchAll(/'([^']+)'\s*=>/g)].map(match => match[1]));
        for (const file of fs.readdirSync(folder).filter(file => /\.(vue|ts)$/.test(file))) {
            for (const match of fs.readFileSync(new URL(file, folder), 'utf8').matchAll(/messages\.([A-Za-z_]+)/g)) assert.equal(keys.has(match[1]), true, `${locale}: ${file} label ${match[1]}`);
        }
        for (const key of ['block_title', 'paragraphs', 'list', 'quote', 'discussion', 'instruction', 'reflection', 'target_class', 'target_pair', 'target_group', 'options', 'items', 'left', 'right', 'roles', 'block_prepared', 'block_open', 'block_closed', 'block_revealed']) assert.equal(keys.has(key), true, `${locale}: ${key}`);
        for (const type of interactive.blockTypes) assert.equal(keys.has(type.replace('core.', '').replaceAll('-', '_')), true, `${locale}: ${type}`);
    }
});

test('revealed public results use a separate label from private teacher solutions', () => {
    const publicRenderer = fs.readFileSync(new URL('../../resources/js/studio/InteractiveBlock.vue', import.meta.url), 'utf8');
    const teacherPanel = fs.readFileSync(new URL('../../resources/js/studio/TeacherPanel.vue', import.meta.url), 'utf8');
    assert.match(publicRenderer, /messages\.revealed_answers/);
    assert.doesNotMatch(publicRenderer, /messages\.correct_answers/);
    assert.match(teacherPanel, /messages\.correct_answers/);
    for (const [locale, text] of [['ru', 'Правильные ответы'], ['de', 'Richtige Antworten']]) {
        const dictionary = fs.readFileSync(new URL(`../../lang/${locale}/studio.php`, import.meta.url), 'utf8');
        assert.ok(dictionary.includes(`'revealed_answers' => '${text}'`));
    }
});
