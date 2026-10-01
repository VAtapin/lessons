import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const compile = file => ts.transpileModule(fs.readFileSync(new URL('../../resources/js/studio/' + file, import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const url = source => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const saveUrl = url(compile('editor-save.ts'));
const historyUrl = url(compile('editor-history.ts').replace("'./editor-save'", JSON.stringify(saveUrl)));
const { EditorSave } = await import(saveUrl);
const { EditorHistory } = await import(url(compile('editor-history.ts').replace("'./editor-save'", JSON.stringify(saveUrl))));
const libraryUrl = url(compile('library.ts'));
const documentUrl = url(compile('document.ts').replace("'./library'", JSON.stringify(libraryUrl)));
const operations = await import(url(compile('editor-document.ts').replace("'./editor-save'", JSON.stringify(saveUrl)).replace("'./document'", JSON.stringify(documentUrl))));
const document = () => ({ id: 'version-one', schemaVersion: 1, locales: ['ru'], defaultLocale: 'ru', content: { ru: { title: 'Title' } }, stages: [{ id: 'stage-one', config: { layout: 'vertical' }, content: { ru: { title: 'Stage', notes: 'Private' } }, blocks: [{ id: 'block-one', type: 'core.single-choice', schemaVersion: 1, content: { ru: { question: 'Q', options: [{ optionId: 'A', text: 'One' }, { optionId: 'b', text: 'Two' }] } }, config: { allowRepeat: false }, media: {}, solution: { optionId: 'A' }, teacherNotes: { ru: 'Secret' }, origin: { templateId: 't', versionId: 'tv' } }] }] });
const lesson = (revision = 1, doc = document()) => ({ id: 'lesson', revision, versionId: doc.id, status: 'draft', document: doc, readiness: { defaultLocale: 'ru', readyLocales: ['ru'], locales: [{ locale: 'ru', status: 'ready', issues: [] }] } });
function state() { const state = new EditorSave(); state.load(lesson()); return state; }
const response = (body, revision = 2, currentRevision = revision, id = 'version-one') => ({ acknowledgedSaveId: body.saveId, appliedRevision: revision, appliedVersionId: id, lesson: lesson(currentRevision, { ...document(), id }) });

test('one flight freezes the complete body; uncertain retry retains UUID, revision and document', () => {
    const save = state(), doc = document(); save.edit(); const body = save.begin(doc, 'original');
    assert.equal(save.begin(doc, 'other'), undefined);
    doc.content.ru.title = 'Later'; save.edit();
    assert.equal(body.document.content.ru.title, 'Title');
    assert.throws(() => { body.document.content.ru.title = 'Mutation'; }, TypeError);
    save.fail(0); assert.equal(save.status, 'retry-required');
    assert.equal(save.begin(doc, 'new-uuid'), undefined);
    assert.equal(save.begin(doc, 'ignored', true), body);
    assert.equal(body.expectedRevision, 1);
    assert.equal(save.acknowledge(response(body)), 'queued');
    assert.equal(save.dirty, true); assert.equal(save.revision, 2);
    const queued = save.begin(doc, 'latest'); assert.equal(queued.expectedRevision, 2); assert.equal(queued.document.content.ru.title, 'Later');
    assert.equal(save.acknowledge(response(queued, 3)), 'clean'); assert.equal(save.dirty, false);
});

test('replayed acknowledgement of an older revision stops the queue without replacing local edits', () => {
    const save = state(), doc = document(); save.edit(); const body = save.begin(doc, 'one'); doc.content.ru.title = 'Local'; save.edit();
    assert.equal(save.acknowledge(response(body, 2, 5)), 'conflict'); assert.equal(save.status, 'conflict');
    assert.equal(doc.content.ru.title, 'Local'); assert.equal(save.remote.revision, 5);
    assert.equal(save.begin(doc, 'new'), undefined);
});

test('422 allows a corrected generation but 409 and identity blocking never auto-resume', () => {
    const save = state(), doc = document(); save.edit(); save.begin(doc, 'invalid'); save.fail(422, undefined, [{ code: 'bad', path: '/x' }]); assert.equal(save.status, 'invalid'); assert.equal(save.pending, undefined); save.edit(); assert.equal(save.begin(doc, 'corrected').expectedRevision, 1);
    save.edit(); save.fail(422); assert.equal(save.status, 'dirty'); assert.deepEqual(save.issues, []);
    save.begin(doc, 'again'); save.fail(409, lesson(5)); save.edit(); assert.equal(save.status, 'conflict'); save.block(); assert.equal(save.begin(doc, 'retry', true), undefined);
});

test('undo in flight is another local generation and never rolls back server identity or pending body', () => {
    const save = state(), history = new EditorHistory(), doc = document(); history.reset({ document: doc, locale: 'ru', stageId: 'stage-one' }); doc.content.ru.title = 'Edit'; history.commit({ document: doc, locale: 'ru', stageId: 'stage-one' }, '', 1); save.edit(); const body = save.begin(doc, 'one'); const undo = history.undo(); save.edit();
    assert.equal(undo.document.content.ru.title, 'Title'); assert.equal(body.document.content.ru.title, 'Edit');
    assert.equal(save.acknowledge(response(body, 2, 2, 'forked-version')), 'queued'); assert.equal(save.versionId, 'forked-version'); assert.equal(save.revision, 2);
});

test('history bounds finished operations, coalesces typing and clears redo on new edits', () => {
    const history = new EditorHistory(), doc = document(); history.reset({ document: doc, locale: 'ru', stageId: 'stage-one' });
    for (let index = 0; index < 5; index++) { doc.content.ru.title += 'x'; history.commit({ document: doc, locale: 'ru', stageId: 'stage-one' }, 'field', index * 100); }
    assert.equal(history.past.length, 1); assert.equal(history.undo().document.content.ru.title, 'Title'); assert.equal(history.future.length, 1);
    for (let index = 0; index < 120; index++) { doc.content.ru.title = String(index); history.commit({ document: doc, locale: 'de', stageId: 'stage-one' }, '', 1000 + index * 600); }
    assert.equal(history.past.length, 100); assert.equal(history.future.length, 0);
});

test('copy instances are independent while answer IDs/solution/origin stay exact and runtime fields are excluded', () => {
    const doc = document(), source = doc.stages[0]; source.blocks[0].runtime = { status: 'revealed' }; source.blocks[0].answers = ['private'];
    const copy = operations.copyStage(source); assert.notEqual(copy.id, source.id); assert.notEqual(copy.blocks[0].id, source.blocks[0].id);
    assert.deepEqual(copy.blocks[0].solution, source.blocks[0].solution); assert.deepEqual(copy.blocks[0].origin, source.blocks[0].origin);
    assert.deepEqual(copy.blocks[0].content.ru.options.map(item => item.optionId), ['A', 'b']); assert.equal(copy.blocks[0].runtime, undefined); assert.equal(copy.blocks[0].answers, undefined);
    copy.blocks[0].content.ru.question = 'New'; assert.equal(source.blocks[0].content.ru.question, 'Q');
});

test('new language persists blank actual strings with all structural IDs and cannot remove default/last locale', () => {
    const doc = document(); assert.equal(operations.addContentLocale(doc, 'de', 'ru'), true);
    assert.deepEqual(doc.content.de, { title: '' }); assert.deepEqual(doc.stages[0].content.de, { title: '', notes: '' });
    assert.deepEqual(doc.stages[0].blocks[0].content.de.options, [{ optionId: 'A', text: '' }, { optionId: 'b', text: '' }]); assert.equal(doc.stages[0].blocks[0].teacherNotes.de, ''); assert.equal(doc.stages[0].blocks[0].solution.optionId, 'A');
    assert.equal(operations.removeContentLocale(doc, 'ru'), false); assert.equal(operations.removeContentLocale(doc, 'de'), true); assert.equal(doc.stages[0].blocks[0].content.de, undefined);
    assert.equal(operations.removeContentLocale(doc, 'ru'), false); assert.equal(operations.addContentLocale(doc, '../x', 'ru'), false);
    assert.equal(operations.pointerSegment('x~/y'), 'x~0~1y');
});

test('copied stage rewires internal review and board sources while retaining intentional external links', () => {
    const source = document().stages[0];
    source.blocks.push({ id: 'reveal', type: 'core.presentation', schemaVersion: 1, media: {}, content: { ru: { text: 'Explanation', modes: [] } }, config: { kind: 'reveal', reviewBlockId: 'block-one', sourceBlockIds: [], maxItems: 8 } });
    source.blocks.push({ id: 'board', type: 'core.presentation', schemaVersion: 1, media: {}, content: { ru: { text: 'Discussion', modes: [] } }, config: { kind: 'response-board', reviewBlockId: null, sourceBlockIds: ['block-one', 'external-stage-response'], maxItems: 8 } });
    const before = structuredClone(source);
    const copy = operations.copyStage(source);
    assert.equal(copy.blocks[1].config.reviewBlockId, copy.blocks[0].id);
    assert.deepEqual(copy.blocks[2].config.sourceBlockIds, [copy.blocks[0].id, 'external-stage-response']);
    copy.blocks[2].config.sourceBlockIds.push('new-source');
    copy.blocks[1].content.ru.text = 'New explanation';
    assert.deepEqual(source, before);
});

test('adding and blanking translations preserves discussion identities while clearing labels and questions', () => {
    const doc = document();
    const block = { id: 'discussion', type: 'core.presentation', schemaVersion: 1, media: {}, content: { ru: { text: 'Introduction', modes: [{ modeId: 'priest-mode', label: 'Priest', text: 'What could he do?' }] } }, config: { kind: 'discussion', reviewBlockId: null, sourceBlockIds: [], maxItems: 8 } };
    doc.stages[0].blocks.push(block);
    assert.equal(operations.addContentLocale(doc, 'de', 'ru'), true);
    assert.deepEqual(block.content.de.modes, [{ modeId: 'priest-mode', label: '', text: '' }]);
    block.content.de.modes[0].label = 'Priester';
    operations.blankOtherTranslations(block, 'ru');
    assert.deepEqual(block.content.de.modes, [{ modeId: 'priest-mode', label: '', text: '' }]);
    assert.equal(block.content.ru.modes[0].label, 'Priest');
});

test('actual draft composable waits for IME and 800ms, queues current input after ack, and stops on identity change', async t => {
    const vueUrl = import.meta.resolve('vue');
    const vue = await import(vueUrl);
    const cleanups = [];
    const previous = { window: globalThis.window, navigator: Object.getOwnPropertyDescriptor(globalThis, 'navigator'), api: globalThis.__draftApi, cleanup: globalThis.__draftCleanup };
    globalThis.window = new EventTarget();
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { onLine: true } });
    globalThis.__draftCleanup = fn => cleanups.push(fn);
    const vueAdapter = url(`export * from ${JSON.stringify(vueUrl)}; export const onBeforeUnmount = fn => globalThis.__draftCleanup(fn);`);
    const identityUrl = url(`import {ref} from ${JSON.stringify(vueUrl)}; export const identityBlocked = ref(false); export const blockIdentity = () => identityBlocked.value = true;`);
    const apiUrl = url('export class ApiError extends Error {} export const api = (...args) => globalThis.__draftApi(...args); export const errorMessage = () => "Error";');
    const composable = await import(url(compile('useEditorDraft.ts').replace("'vue'", JSON.stringify(vueAdapter)).replace("'./api'", JSON.stringify(apiUrl)).replace("'./identity'", JSON.stringify(identityUrl)).replace("'./editor-save'", JSON.stringify(saveUrl)).replace("'./editor-history'", JSON.stringify(historyUrl))));
    const identity = await import(identityUrl);
    const requests = [];
    globalThis.__draftApi = (path, method, body) => new Promise(resolve => requests.push({ path, method, body, resolve }));
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const settle = async () => { for (let index = 0; index < 8; index++) await vue.nextTick(); };
    try {
        const editor = composable.useEditorDraft('lesson', vue.ref('stage-one'), vue.ref('ru'), {});
        editor.adopt(lesson()); await settle();
        editor.composing.value = true; editor.document.value.content.ru.title = 'IME'; await settle();
        t.mock.timers.tick(1000); await settle(); assert.equal(requests.length, 0);
        editor.endComposition(); await settle(); t.mock.timers.tick(799); await settle(); assert.equal(requests.length, 0);
        t.mock.timers.tick(1); await settle(); assert.equal(requests.length, 1); assert.equal(requests[0].body.document.content.ru.title, 'IME');
        editor.document.value.content.ru.title = 'Later'; await settle(); t.mock.timers.tick(1000); await settle(); assert.equal(requests.length, 1);
        requests[0].resolve({ ...response(requests[0].body, 2, 2, 'fork'), lesson: lesson(2, { ...requests[0].body.document, id: 'fork' }) }); await settle();
        assert.equal(editor.document.value.content.ru.title, 'Later'); assert.equal(editor.document.value.id, 'fork'); assert.equal(editor.save.status, 'dirty');
        t.mock.timers.tick(800); await settle(); assert.equal(requests.length, 2); assert.equal(requests[1].body.expectedRevision, 2); assert.notEqual(requests[1].body.saveId, requests[0].body.saveId);
        requests[1].resolve({ ...response(requests[1].body, 3, 3, 'fork'), lesson: lesson(3, requests[1].body.document) }); await settle(); assert.equal(editor.save.status, 'clean');
        const target = { tagName: 'INPUT', type: 'text', dataset: { editorPath: '/content/ru/title' } };
        editor.markText({ target }); editor.document.value.content.ru.title = 'Typing'; await settle();
        editor.markText({ target: { tagName: 'INPUT', type: 'checkbox', dataset: {} } }); editor.document.value.stages[0].blocks[0].config.allowRepeat = true; await settle();
        editor.restore('undo'); await settle(); assert.equal(editor.document.value.stages[0].blocks[0].config.allowRepeat, false); assert.equal(editor.document.value.content.ru.title, 'Typing');
        editor.markText({ target: { tagName: 'SELECT', dataset: {} } }); editor.document.value.stages[0].config.layout = 'two-columns'; await settle();
        editor.restore('undo'); await settle(); assert.equal(editor.document.value.stages[0].config.layout, 'vertical'); assert.equal(editor.document.value.content.ru.title, 'Typing');
        editor.markText({ target }); editor.document.value.content.ru.title = 'Before insert'; await settle();
        editor.markOperation(); editor.document.value.stages[0].blocks.push(operations.copyBlock(editor.document.value.stages[0].blocks[0])); await settle();
        editor.restore('undo'); await settle(); assert.equal(editor.document.value.stages[0].blocks.length, 1); assert.equal(editor.document.value.content.ru.title, 'Before insert');
        navigator.onLine = false; window.dispatchEvent(new Event('offline')); editor.document.value.content.ru.title = 'Offline'; await settle(); t.mock.timers.tick(2000); await settle(); assert.equal(requests.length, 2); assert.equal(editor.save.status, 'offline');
        navigator.onLine = true; window.dispatchEvent(new Event('online')); await settle(); identity.blockIdentity(); await settle(); t.mock.timers.tick(1000); await settle(); assert.equal(requests.length, 2); assert.equal(editor.save.status, 'blocked'); assert.equal(editor.document.value.content.ru.title, 'Offline');
    } finally { cleanups.forEach(fn => fn()); t.mock.timers.reset(); globalThis.window = previous.window; if (previous.navigator) Object.defineProperty(globalThis, 'navigator', previous.navigator); else delete globalThis.navigator; globalThis.__draftApi = previous.api; globalThis.__draftCleanup = previous.cleanup; }
});

test('dynamic readiness/save/layout labels are localized and layout renderer preserves array order', () => {
    for (const locale of ['ru', 'de']) {
        const dictionary = fs.readFileSync(new URL(`../../lang/${locale}/studio.php`, import.meta.url), 'utf8');
        for (const key of ['editor_loading','editor_clean','editor_dirty','editor_saving','editor_invalid','editor_offline','editor_retry_required','editor_conflict','editor_blocked','translation_draft','translation_partial','translation_ready','layout_vertical','layout_two_columns','layout_material_above_task']) assert.ok(dictionary.includes(`'${key}' =>`), `${locale}: ${key}`);
    }
    const renderer = fs.readFileSync(new URL('../../resources/js/studio/StageRenderer.vue', import.meta.url), 'utf8');
    assert.match(renderer, /v-for="block in stage.blocks"/); assert.doesNotMatch(renderer, /\.sort\(|\.reverse\(/);
    const editor = fs.readFileSync(new URL('../../resources/js/studio/LessonEditor.vue', import.meta.url), 'utf8');
    assert.match(editor, /function insertTemplate\(block: Block\)[\s\S]*?markOperation\(\);[\s\S]*?blocks\.push\(block\)/);
});
