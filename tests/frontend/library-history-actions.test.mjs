import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import ts from 'typescript';
import { parse, compileScript } from '@vue/compiler-sfc';
import * as Vue from 'vue';

const read = file => fs.readFileSync(new URL('../../resources/js/studio/' + file, import.meta.url), 'utf8');
const evaluate = (source, dependencies = {}) => {
    const exports = {};
    const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;
    vm.runInNewContext(compiled, { exports, structuredClone, URLSearchParams, TextEncoder, require: name => {
        assert.ok(name in dependencies, `Unexpected dependency: ${name}`);
        return dependencies[name];
    } });
    return exports;
};
const component = (file, dependencies = {}) => evaluate(compileScript(parse(read(file)).descriptor, { id: file, inlineTemplate: true }).content, { vue: Vue, ...dependencies }).default;
const vueModule = value => ({ __esModule: true, default: value });
const library = evaluate(read('library.ts'));
const interactive = evaluate(read('interactive.ts'));
const dialog = component('PreviewDialog.vue');
const metadata = component('MetadataFields.vue', { './library': library });
const messages = Object.fromEntries(['HistoryPage.vue', 'LibraryPage.vue', 'CommonLibrary.vue', 'MetadataFields.vue'].flatMap(file => [...read(file).matchAll(/messages\.([a-z_]+)/g)].map(match => [match[1], match[1]])));
const text = node => node.kind === '#comment' ? '' : node.text + node.children.map(text).join('');
const descendants = node => [node, ...node.children.flatMap(descendants)];
const flush = async () => { await new Promise(resolve => setImmediate(resolve)); await Vue.nextTick(); };
const plain = value => JSON.parse(JSON.stringify(value));

function fixture(t, renderedComponent, extra = {}) {
    const events = [];
    const node = (kind, value = '') => Vue.markRaw({ kind, tagName: kind.toUpperCase(), text: value, props: {}, style: {}, children: [], parent: null, value: '', listeners: {},
        get options() { return this.children.filter(child => child.kind === 'option'); },
        addEventListener(name, callback) { this.listeners[name] = callback; },
        showModal() { this.open = true; this.modalCalls = (this.modalCalls ?? 0) + 1; },
        close() { this.open = false; this.closeCalls = (this.closeCalls ?? 0) + 1; },
        getBoundingClientRect() { return { left: 10, right: 100, top: 10, bottom: 100 }; },
    });
    const remove = child => { if (child.parent) child.parent.children.splice(child.parent.children.indexOf(child), 1); child.parent = null; };
    const renderer = Vue.createRenderer({
        createElement: kind => node(kind), createText: value => node('#text', value), createComment: value => node('#comment', value),
        setText: (target, value) => { target.text = value; }, setElementText: (target, value) => { target.text = value; target.children = []; },
        patchProp: (target, key, previous, next) => { target.props[key] = next; if (['type', 'value', 'multiple'].includes(key)) target[key] = next; },
        insert: (child, parent, anchor = null) => { remove(child); const index = anchor ? parent.children.indexOf(anchor) : -1; parent.children.splice(index < 0 ? parent.children.length : index, 0, child); child.parent = parent; },
        remove, parentNode: target => target.parent, nextSibling: target => target.parent?.children[target.parent.children.indexOf(target) + 1] ?? null,
    });
    const props = Vue.reactive({ locale: 'ru', messages, ...extra });
    const root = node('root');
    const handlers = { onInsert: value => events.push(['insert', plain(value)]), 'onUpdate:modelValue': value => events.push(['metadata', plain(value)]), onClose: () => events.push(['close']) };
    const app = renderer.createApp({ setup: () => () => Vue.h(renderedComponent, { ...Object.fromEntries(Object.entries(props).filter(([key]) => key in renderedComponent.props)), ...Object.fromEntries(Object.entries(handlers).filter(([name]) => renderedComponent.emits?.includes(name.slice(2, 3).toLowerCase() + name.slice(3)))) }) });
    app.mount(root);
    t.after(() => app.unmount());
    const nodes = kind => descendants(root).filter(item => item.kind === kind);
    const button = label => { const found = nodes('button').filter(item => text(item) === label); assert.equal(found.length, 1, `Expected one button: ${label}`); return found[0]; };
    return { props, events, root, nodes, button, click: label => button(label).props.onClick({}) };
}

const apiModule = api => ({ api, errorMessage: () => 'Request failed', ApiError: class extends Error {} });
const blockRenderer = { props: ['block'], setup: props => () => Vue.h('div', { class: 'render-block' }, props.block.content.text ?? props.block.content.question ?? '') };
const common = api => component('CommonLibrary.vue', { './api': apiModule(api), './admin': { adminError: () => 'Request failed' }, './library': library, './interactive': interactive, './PreviewDialog.vue': vueModule(dialog), './BlockRenderer.vue': vueModule(blockRenderer), '../../css/admin.css': {} });
const attribution = { title: 'Template', tags: ['help'], author: 'Hidden Author', source: 'Hidden Source', rightsBasis: 'permission', usageRights: 'Hidden Rights' };
const template = { id: 'common-one', title: 'Help', description: 'Block', tags: ['help'], locales: ['ru'], versionId: 'v2', attribution, versions: [{ id: 'v2', versionNo: 2, locales: ['ru'], attribution }, { id: 'v1', versionNo: 1, locales: ['ru'], attribution }] };

test('shared native dialog opens modally and handles close, Escape and only backdrop clicks', async t => {
    const page = fixture(t, dialog, { title: 'Preview', closeLabel: 'Close' });
    const element = page.nodes('dialog')[0];
    assert.equal(element.open, true);
    assert.equal(element.modalCalls, 1);
    assert.equal(element.props['aria-modal'], 'true');
    assert.ok('autofocus' in page.button('Close').props);
    element.props.onClick({ target: element, clientX: 50, clientY: 50 });
    element.props.onClick({ target: page.button('Close'), clientX: 0, clientY: 0 });
    assert.deepEqual(page.events, []);
    element.props.onClick({ target: element, clientX: 0, clientY: 50 });
    let prevented = false;
    element.props.onCancel({ preventDefault() { prevented = true; } });
    page.click('Close');
    assert.equal(prevented, true);
    assert.deepEqual(page.events, [['close'], ['close'], ['close']]);
});

test('common preview opens while loading, cancels safely, and ignores the late response', async t => {
    let resolve;
    const page = fixture(t, common(path => path.includes('common-one') ? new Promise(done => { resolve = done; }) : Promise.resolve({ templates: [template] })));
    await flush();
    page.click(messages.admin_preview);
    await Vue.nextTick();
    assert.equal(page.nodes('dialog').length, 1);
    assert.match(text(page.nodes('dialog')[0]), /admin_loading/);
    page.click(messages.admin_close);
    await Vue.nextTick();
    assert.equal(page.nodes('dialog').length, 0);
    resolve({ template, preview: { content: { text: 'Late preview' } } });
    await flush();
    assert.equal(page.nodes('dialog').length, 0);
    assert.doesNotMatch(text(page.root), /Late preview/);
    assert.equal(page.button(messages.admin_preview).props.disabled, false);
});

test('common modal renders the preview without attribution and inserts the selected immutable version', async t => {
    const calls = [];
    const inserted = { id: 'independent', type: 'core.text', content: { ru: { text: 'Copied' } } };
    const page = fixture(t, common(async (...args) => { calls.push(args); return args[0].endsWith('/instantiate') ? { block: inserted } : args[0].includes('common-one') ? { template, preview: { content: { text: 'Actual preview' } } } : { templates: [template] }; }), { locales: ['ru'] });
    await flush(); page.click(messages.admin_preview); await flush();
    assert.match(text(page.nodes('dialog')[0]), /Actual preview/);
    assert.doesNotMatch(text(page.root), /Hidden Author|Hidden Source|Hidden Rights/);
    const select = descendants(page.nodes('dialog')[0]).find(node => node.kind === 'select');
    select.options[0].selected = false; select.options[1].selected = true;
    select.listeners.change({ target: select });
    await Vue.nextTick(); page.click(messages.admin_insert); await flush();
    assert.deepEqual(plain(calls.at(-1)), ['/api/catalog/templates/common-one/instantiate', 'POST', { versionId: 'v1', locales: ['ru'] }]);
    assert.deepEqual(page.events, [['insert', inserted]]);
});

test('metadata form exposes title and tags only and preserves stored attribution when editing', t => {
    const page = fixture(t, metadata, { modelValue: attribution, publicContext: true });
    assert.equal(page.nodes('input').length, 2);
    assert.equal(page.nodes('select').length, 0);
    assert.doesNotMatch(text(page.root), /Hidden Author|Hidden Source|Hidden Rights/);
    page.nodes('input')[0].props.onInput({ target: { value: 'Renamed' } });
    assert.deepEqual(page.events, [['metadata', { ...attribution, title: 'Renamed' }]]);
    page.nodes('input')[1].props.onChange({ target: { value: 'help, care, help' } });
    assert.deepEqual(page.events[1], ['metadata', { ...attribution, tags: ['help', 'care'] }]);
});

test('personal library preview is modal, strips private data and leaves unsaved form values intact', async t => {
    const block = { id: 'block', type: 'core.text', content: { ru: { text: 'Stored text' } }, config: {}, media: {}, solution: { private: true }, teacherNotes: 'Teacher secret', origin: { templateId: 'old' } };
    const detail = { ...attribution, id: 'mine', revision: 1, currentVersionId: 'version', type: 'core.text', locales: ['ru'], versions: [{ id: 'version', versionNo: 1, locales: ['ru'], defaultLocale: 'ru', block }], usages: [] };
    const projected = [];
    const renderer = { props: ['block'], setup: props => () => { projected.push(plain(props.block)); return Vue.h('div', { class: 'render-block' }, props.block.content.text); } };
    const page = fixture(t, component('LibraryPage.vue', { './interactive': interactive, './api': apiModule(async path => path.startsWith('/api/studio/media') ? { media: [] } : path === '/api/studio/templates/mine' ? { template: detail } : { templates: [detail] }), './library': library, './library-api': { libraryError: () => 'Request failed' }, './MetadataFields.vue': vueModule(metadata), './PreviewDialog.vue': vueModule(dialog), './CommonLibrary.vue': vueModule({ render: () => null }), './BlockEditor.vue': vueModule({ render: () => null }), './BlockRenderer.vue': vueModule(renderer) }));
    await flush();
    page.nodes('button').find(item => item.props.class === 'studio-card library-item').props.onClick({});
    await flush();
    const title = page.nodes('input').find(item => item.props.value === 'Template');
    title.props.onInput({ target: { value: 'Unsaved title' } });
    page.nodes('form').find(item => item.props.onInput).props.onInput({});
    await Vue.nextTick(); page.click(messages.preview); await Vue.nextTick();
    assert.equal(page.nodes('dialog')[0].open, true);
    assert.match(text(page.nodes('dialog')[0]), /Stored text/);
    assert.equal(projected.at(-1).solution, undefined);
    assert.equal(projected.at(-1).teacherNotes, undefined);
    assert.equal(projected.at(-1).origin, undefined);
    page.click(messages.close); await Vue.nextTick();
    assert.equal(page.nodes('dialog').length, 0);
    assert.ok(page.nodes('input').some(item => item.props.value === 'Unsaved title'));
    assert.ok(page.button(messages.discard_library_edits));
});

const snapshot = { stages: [{ id: 'stage', content: { ru: { title: 'Immutable stage' } }, blocks: [
    { id: 'first', type: 'core.single-choice', content: { ru: { question: 'First question', options: [{ optionId: 'yes', text: 'I help' }] } } },
    { id: 'second', type: 'core.free-response', content: { ru: { question: 'Second question' } } },
    { id: 'mode', type: 'core.presentation', content: { ru: { question: 'Choose direction', modes: [{ modeId: 'school', text: 'School' }] } } },
] }] };
const history = { id: 'session', lessonId: 'deleted-owner-copy', lessonVersionId: 'immutable-version', title: 'Session', locale: 'ru', mode: 'lesson', status: 'finished', revision: 1, teacherNotes: '', visitedStageIds: ['stage'], detailsAvailable: true, snapshotDocument: snapshot, participants: [{ id: 'a', name: 'Anna' }, { id: 'b', name: 'Boris' }], aggregates: [{ blockId: 'second', type: 'core.free-response', submittedCount: 1 }, { blockId: 'first', type: 'core.single-choice', submittedCount: 2 }], answers: [
    { id: 'answer-2', participantId: 'b', blockId: 'second', value: { text: '<b>Personal decision</b>' }, grade: null },
    { id: 'answer-1', participantId: 'a', blockId: 'first', value: { optionId: 'yes' }, grade: true },
    { id: 'answer-3', participantId: 'b', blockId: 'first', value: { optionId: 'yes' }, grade: false },
    { id: 'answer-4', participantId: 'a', blockId: 'mode', value: { modeId: 'school' }, grade: null },
] };
const historyComponent = api => component('HistoryPage.vue', { './api': apiModule(api), './interactive': interactive, './account-helpers': evaluate(read('account-helpers.ts')) });

test('owner history renders named answers beneath each immutable question in stage order', async t => {
    const calls = [];
    const page = fixture(t, historyComponent(async path => { calls.push(path); return { history }; }), { sessionId: 'session' });
    await flush();
    assert.deepEqual(calls, ['/api/studio/sessions/session/history'], 'The owner snapshot does not require access to an old authoring copy');
    const questions = page.nodes('article');
    assert.deepEqual(questions.map(item => item.props['data-block-id']), ['first', 'second', 'mode']);
    assert.match(text(questions[0]), /First question.*Anna.*I help.*answer_correct.*Boris.*I help.*answer_incorrect/);
    assert.doesNotMatch(text(questions[0]), /Personal decision/);
    assert.match(text(questions[1]), /Second question.*Boris.*<b>Personal decision<\/b>/);
    assert.doesNotMatch(text(questions[1]), /Anna/);
    assert.match(text(questions[2]), /Choose direction.*Anna.*School/);
    assert.equal(page.nodes('b').length, 0, 'Free text is escaped by the real Vue template');
    assert.equal(descendants(page.root).some(item => item.props.class === 'history-participants'), false);
});

test('expired history never renders stale participant names or answers and does not fetch private documents', async t => {
    const calls = [];
    const page = fixture(t, historyComponent(async path => { calls.push(path); return { history: { ...history, detailsAvailable: false } }; }), { sessionId: 'session' });
    await flush();
    assert.deepEqual(calls, ['/api/studio/sessions/session/history']);
    assert.match(text(page.root), /history_details_expired/);
    assert.doesNotMatch(text(page.root), /Anna|Boris|Personal decision|First question|Choose direction/);
    assert.equal(page.nodes('article').length, 2, 'Only retained aggregate questions remain');
    assert.equal(descendants(page.root).some(item => item.props.class === 'history-answer'), false);
});

test('history excludes empty scene, reveal and summary slides while retaining unanswered discussion questions', async t => {
    const staticBlocks = ['scene', 'reveal', 'summary'].map(kind => ({ id: kind, type: 'core.presentation', config: { kind }, content: { ru: { text: 'Static ' + kind } } }));
    const discussion = { id: 'discussion', type: 'core.presentation', config: { kind: 'discussion' }, content: { ru: { question: 'Unanswered discussion' } } };
    const page = fixture(t, historyComponent(async () => ({ history: { ...history,
        snapshotDocument: { stages: [{ ...snapshot.stages[0], blocks: [...snapshot.stages[0].blocks, ...staticBlocks, discussion] }] },
        aggregates: [...history.aggregates, ...staticBlocks.map(block => ({ blockId: block.id, type: block.type, submittedCount: 0 })), { blockId: 'discussion', type: 'core.presentation', submittedCount: 0 }],
    } })), { sessionId: 'session' });
    await flush();
    assert.deepEqual(page.nodes('article').map(item => item.props['data-block-id']), ['first', 'second', 'mode', 'discussion']);
    assert.doesNotMatch(text(page.root), /Static scene|Static reveal|Static summary/);
    assert.match(text(page.root), /Unanswered discussion.*no_answers/);
});

test('history remains readable when an older session has no snapshot and its lesson copy is unavailable', async t => {
    const page = fixture(t, historyComponent(async path => {
        if (path.includes('/versions/')) throw new Error('Archived authoring copy');
        return { history: { ...history, snapshotDocument: null, answers: [{ id: 'lost', participantId: 'removed', blockId: 'legacy', value: { text: 'Retained answer' }, grade: null }], aggregates: [] } };
    }), { sessionId: 'session' });
    await flush();
    assert.match(text(page.nodes('article')[0]), /unknown.*Retained answer/);
    assert.equal(page.nodes('article')[0].props['data-block-id'], 'legacy');
});

test('purged lesson history retains named immutable answers without offering restoration or editor actions', async t => {
    const calls = [];
    const page = fixture(t, historyComponent(async path => { calls.push(path); return { history: { ...history, lessonPurged: true, lessonArchived: true } }; }), { sessionId: 'session' });
    await flush();
    assert.match(text(page.root), /lesson_purged/);
    assert.match(text(page.nodes('article')[0]), /First question.*Anna.*I help/);
    assert.doesNotMatch(text(page.root), /error_lesson_in_trash|lesson_trash|run_again|open_editor/);
    assert.equal(page.nodes('a').some(item => item.props.href.includes('view=trash') || item.props.href.includes('/studio/lessons/')), false);
    assert.deepEqual(calls, ['/api/studio/sessions/session/history']);
});

test('purged legacy history never attempts to fetch the removed authoring copy', async t => {
    const calls = [];
    const page = fixture(t, historyComponent(async path => { calls.push(path); return { history: { ...history, lessonPurged: true, snapshotDocument: null } }; }), { sessionId: 'session' });
    await flush();
    assert.deepEqual(calls, ['/api/studio/sessions/session/history']);
    assert.match(text(page.root), /Anna/);
    assert.doesNotMatch(text(page.root), /open_editor|run_again|lesson_trash/);
});


test('common library requests bounded server pages, retains filters and resets page on search', async t => {
    const calls = [];
    const page = fixture(t, common(async path => {
        calls.push(path);
        const url = new URL(path, 'https://example.test');
        return { templates: [{ ...template, scope: 'universal' }], pagination: { page: Number(url.searchParams.get('page')), total: 25, lastPage: 3 } };
    }));
    await flush();
    assert.match(calls[0], /scope=universal/);
    page.click(messages.next); await flush();
    assert.match(calls.at(-1), /page=2/);
    assert.match(calls.at(-1), /scope=universal/);
    const input = page.nodes('input')[0];
    input.value = 'pair'; input.listeners.input({ target: input });
    await Vue.nextTick();
    page.nodes('form')[0].props.onSubmit({ preventDefault() {} }); await flush();
    assert.match(calls.at(-1), /page=1/);
    assert.match(calls.at(-1), /q=pair/);
    assert.equal(page.button(messages.previous).props.disabled, true);
});
