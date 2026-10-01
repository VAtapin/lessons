import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import ts from 'typescript';
import { parse, compileScript } from '@vue/compiler-sfc';
import * as Vue from 'vue';

const compile = source => ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;
const evaluate = (code, dependencies, globals = {}) => {
    const exports = {};
    vm.runInNewContext(code, { exports, require: name => {
        assert.ok(name in dependencies, `Unexpected component dependency: ${name}`);
        return dependencies[name];
    }, ...globals });
    return exports;
};
const apiModule = evaluate(compile(fs.readFileSync(new URL('../../resources/js/studio/api.ts', import.meta.url), 'utf8')), { vue: Vue, './identity': {} });
const { descriptor } = parse(fs.readFileSync(new URL('../../resources/js/studio/StudioList.vue', import.meta.url), 'utf8'));
const compiled = compile(compileScript(descriptor, { id: 'lesson-trash-test', inlineTemplate: true }).content);
const messages = {
    workspace_caption: 'Workspace', workspace_lessons: 'My lessons', workspace_overview: 'Overview', constructor: 'Constructor',
    lesson_trash: 'Trash', lesson_deleted: 'Deleted', lesson_trash_empty: 'Trash is empty', lesson_trash_hint: 'Restore saved lessons here',
    new_material: 'New lesson', loading: 'Loading', guest_notice: 'Guest workspace', draft: 'Draft', revision: 'Revision',
    favorite: 'Favorite', saved_versions: 'Saved versions', open_editor: 'Open editor', delete_lesson: 'Delete lesson',
    lesson_delete_confirm: 'Move this lesson to trash?', lesson_move_to_trash: 'Move to trash', cancel: 'Cancel', restore: 'Restore',
    lesson_removed: 'Lesson moved to trash', lesson_restored: 'Lesson restored',
    error_network: 'Network failure', error_invalid_action: 'Action rejected', error_revision_conflict: 'Lesson changed in another tab',
};
const lesson = (id = 'lesson-original', revision = 7) => ({ id, revision, title: `Title ${id}`, status: 'draft', favorite: false });
const deferred = () => {
    let resolve, reject;
    const promise = new Promise((success, failure) => { resolve = success; reject = failure; });
    return { promise, resolve, reject };
};
const content = node => node.text + node.children.map(content).join('');
const descendants = node => [node, ...node.children.flatMap(descendants)];

// Vue renders the real template and invokes its actual click handlers. Only the HTTP
// boundary and unrelated child components are substituted; no browser DOM is needed.
async function fixture(t, { view = '', initial = [lesson()], request = async () => ({ lesson: lesson() }) } = {}) {
    const calls = [];
    const node = (type, text = '') => ({ type, text, props: {}, children: [], parent: null });
    const remove = child => {
        if (child.parent) child.parent.children.splice(child.parent.children.indexOf(child), 1);
        child.parent = null;
    };
    const renderer = Vue.createRenderer({
        createElement: type => node(type), createText: text => node('#text', text), createComment: text => node('#comment', text),
        setText: (target, text) => { target.text = text; },
        setElementText: (target, text) => { target.text = text; target.children = []; },
        patchProp: (target, key, previous, next) => { target.props[key] = next; },
        insert: (child, parent, anchor = null) => {
            remove(child);
            const index = anchor ? parent.children.indexOf(anchor) : -1;
            parent.children.splice(index < 0 ? parent.children.length : index, 0, child);
            child.parent = parent;
        },
        remove, parentNode: target => target.parent,
        nextSibling: target => target.parent?.children[target.parent.children.indexOf(target) + 1] ?? null,
    });
    const stub = { __esModule: true, default: { render: () => null } };
    const component = evaluate(compiled, {
        vue: Vue, './api': { ...apiModule, api: async (...args) => {
            calls.push(structuredClone(args));
            if (calls.length === 1) return { lessons: structuredClone(initial) };
            return await request(...args);
        } },
        './identity': { accountState: Vue.ref(null) }, './document': { newDocument: () => assert.fail('Unexpected new lesson') },
        './VersionBrowser.vue': { __esModule: true, default: { props: ['lessonId'], render() { return Vue.h('aside', {}, `Versions ${this.lessonId}`); } } },
        './WorkspaceOverview.vue': stub, './StudioIcon.vue': stub,
    }, { location: { search: view ? `?view=${view}` : '' }, URLSearchParams }).default;
    const root = node('root');
    const app = renderer.createApp(component, { locale: 'ru', messages });
    app.mount(root);
    t.after(() => app.unmount());
    await new Promise(resolve => setImmediate(resolve));
    await Vue.nextTick();
    const nodes = (type, text) => descendants(root).filter(item => item.type === type && (text === undefined || content(item) === text));
    const button = text => {
        const matches = nodes('button', text);
        assert.equal(matches.length, 1, `Expected one button: ${text}`);
        return matches[0];
    };
    return { calls, root, nodes, button, click: text => button(text).props.onClick({}), cards: () => nodes('article').map(content) };
}

test('canceling the actual delete confirmation preserves the lesson without sending a request', async t => {
    const page = await fixture(t);
    assert.deepEqual(page.calls, [['/api/studio/lessons?archived=0']]);
    const before = page.cards();
    page.click(messages.delete_lesson);
    await Vue.nextTick();
    assert.equal(page.nodes('div').filter(item => item.props.role === 'alert').length, 1);
    page.click(messages.cancel);
    await Vue.nextTick();
    assert.deepEqual(page.cards(), before);
    assert.equal(page.nodes('button', messages.lesson_move_to_trash).length, 0);
    assert.equal(page.calls.length, 1);
});

test('moving a lesson waits for POST acknowledgement, uses its revision and removes only that ID', async t => {
    const ack = deferred();
    const page = await fixture(t, { request: () => ack.promise });
    page.click(messages.saved_versions);
    page.click(messages.delete_lesson);
    await Vue.nextTick();
    const pending = page.click(messages.lesson_move_to_trash);
    await Vue.nextTick();
    assert.deepEqual(page.calls[1], ['/api/studio/lessons/lesson-original/archive', 'POST', { expectedRevision: 7, archived: true }]);
    assert.equal(page.cards().length, 1);
    assert.equal(page.button(messages.lesson_move_to_trash).props.disabled, true);
    assert.equal(page.nodes('aside', 'Versions lesson-original').length, 1);
    assert.equal(page.nodes('p').filter(item => item.props.role === 'status' && content(item).includes(messages.lesson_removed)).length, 0);
    await page.click(messages.lesson_move_to_trash);
    assert.equal(page.calls.length, 2, 'A pending archive must not produce another POST');
    ack.resolve({ lesson: { ...lesson(), archived: true } });
    await pending;
    await Vue.nextTick();
    assert.deepEqual(page.cards(), []);
    assert.equal(page.nodes('aside').length, 0);
    assert.equal(page.nodes('a').filter(item => item.props.href === '/ru/studio?view=trash').length, 2);
    assert.match(content(page.root), /Lesson moved to trash/);
});

test('archiving one of two lessons leaves the other lesson and its identity available', async t => {
    const page = await fixture(t, { initial: [lesson('lesson-original'), lesson('lesson-other', 12)] });
    const original = page.nodes('article').find(item => content(item).includes('Title lesson-original'));
    descendants(original).find(item => item.type === 'button' && content(item) === messages.delete_lesson).props.onClick({});
    await Vue.nextTick();
    await page.click(messages.lesson_move_to_trash);
    await Vue.nextTick();
    assert.equal(page.cards().length, 1);
    assert.match(page.cards()[0], /Title lesson-other/);
    assert.equal(page.nodes('a').filter(item => item.props.href === '/ru/studio/lessons/lesson-other').length, 2);
});

test('failed archive preserves the lesson and confirmation for a deliberate retry', async t => {
    for (const code of ['network', 'invalid_action']) {
        const page = await fixture(t, { request: async () => { throw new apiModule.ApiError(code, code === 'network' ? 0 : 422); } });
        page.click(messages.delete_lesson);
        await Vue.nextTick();
        const before = page.cards();
        await page.click(messages.lesson_move_to_trash);
        await Vue.nextTick();
        assert.deepEqual(page.cards(), before);
        assert.equal(page.button(messages.lesson_move_to_trash).props.disabled, false);
        assert.equal(page.button(messages.cancel).props.disabled, false);
        assert.equal(page.calls.length, 2);
        assert.equal(page.nodes('p', messages[`error_${code}`]).length, 1);
        assert.doesNotMatch(content(page.root), /Lesson moved to trash/);
    }
});

test('trash renders restore for the saved lesson ID and waits for its acknowledged restoration', async t => {
    const ack = deferred();
    const page = await fixture(t, { view: 'trash', initial: [{ ...lesson('lesson-original', 19), archived: true }], request: () => ack.promise });
    assert.deepEqual(page.calls, [['/api/studio/lessons?archived=1']]);
    assert.equal(page.nodes('button', messages.delete_lesson).length, 0);
    assert.equal(page.nodes('button').filter(item => content(item).includes(messages.favorite)).length, 0);
    assert.equal(page.nodes('button', messages.saved_versions).length, 0);
    assert.equal(page.nodes('a').filter(item => item.props.href === '/ru/studio/lessons/lesson-original').length, 0);
    const pending = page.click(messages.restore);
    await Vue.nextTick();
    assert.deepEqual(page.calls[1], ['/api/studio/lessons/lesson-original/archive', 'POST', { expectedRevision: 19, archived: false }]);
    assert.equal(page.cards().length, 1);
    assert.equal(page.button(messages.restore).props.disabled, true);
    assert.doesNotMatch(content(page.root), /Lesson restored/);
    ack.resolve({ lesson: { ...lesson('lesson-original', 20), archived: false } });
    await pending;
    await Vue.nextTick();
    assert.equal(page.cards().length, 0);
    assert.match(content(page.root), /Lesson restored/);
    assert.equal(page.nodes('a').filter(item => item.props.href === '/ru/studio').length, 2);
    assert.equal(page.calls.length, 2, 'Restoration must not create a new lesson');
});

test('failed restoration leaves the archived lesson available without claiming success', async t => {
    const page = await fixture(t, { view: 'trash', request: async () => { throw new apiModule.ApiError('network', 0); } });
    const before = page.cards();
    await page.click(messages.restore);
    await Vue.nextTick();
    assert.deepEqual(page.cards(), before);
    assert.equal(page.button(messages.restore).props.disabled, false);
    assert.equal(page.nodes('p', messages.error_network).length, 1);
    assert.doesNotMatch(content(page.root), /Lesson restored/);
});

test('revision conflict reloads the current list and reports the conflict without retrying the mutation', async t => {
    for (const view of ['', 'trash']) {
        const page = await fixture(t, { view, request: async (path, method) => {
            if (method === 'POST') throw new apiModule.ApiError('revision_conflict', 409);
            return { lessons: [{ ...lesson('lesson-original', 8), title: 'Fresh server title' }] };
        } });
        if (!view) { page.click(messages.delete_lesson); await Vue.nextTick(); }
        await page.click(view ? messages.restore : messages.lesson_move_to_trash);
        await Vue.nextTick();
        assert.deepEqual(page.calls, [
            [`/api/studio/lessons?archived=${view ? '1' : '0'}`],
            ['/api/studio/lessons/lesson-original/archive', 'POST', { expectedRevision: 7, archived: !view }],
            [`/api/studio/lessons?archived=${view ? '1' : '0'}`],
        ]);
        assert.match(page.cards()[0], /Fresh server title/);
        assert.match(page.cards()[0], /Revision 8/);
        assert.equal(page.nodes('p', messages.error_revision_conflict).length, 1);
        assert.equal(page.nodes('button', messages.lesson_move_to_trash).length, 0);
        assert.doesNotMatch(content(page.root), /Lesson moved to trash|Lesson restored/);
    }
});
