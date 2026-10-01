import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import ts from 'typescript';
import * as Vue from 'vue';
import { parse, compileScript } from '@vue/compiler-sfc';
import { randomUUID } from 'node:crypto';

class ApiError extends Error { constructor(code, status) { super(code); this.code = code; this.status = status; } }
const calls = [];
let handle;
const stub = { __esModule: true, default: { render: () => null } };
const messages = Object.fromEntries(['empty_trash', 'purge_permanently', 'cancel', 'restore', 'finish_remove_session', 'confirm_finish_session', 'finish_remove_confirm'].map(key => [key, key]));
function component(file) {
    const { descriptor } = parse(fs.readFileSync(new URL('../../resources/js/studio/' + file, import.meta.url), 'utf8'));
    const code = ts.transpileModule(compileScript(descriptor, { id: file, inlineTemplate: true }).content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;
    const exports = {};
    vm.runInNewContext(code, { exports, URLSearchParams, location: { search: '?view=trash' }, crypto: { randomUUID }, require: name => {
        if (name === 'vue') return Vue;
        if (name === './api') return { ApiError, errorMessage: error => error.code, api: async (...args) => { calls.push(structuredClone(args)); return handle(...args); } };
        if (name === './identity') return { accountState: Vue.ref(null) };
        if (name === './document') return { newDocument() {} };
        if (name.endsWith('.vue')) return stub;
        throw new Error(name);
    } });
    return exports.default;
}
const studio = component('StudioList.vue'), active = component('ActiveSessionList.vue');
const text = node => node.kind === '#comment' ? '' : node.text + node.children.map(text).join('');
const descend = node => [node, ...node.children.flatMap(descend)];
async function mount(t, component, props = {}) {
    calls.length = 0;
    const node = (kind, text = '') => ({ kind, text, children: [], parent: null, props: {}, focus() {} });
    const remove = child => { if (child.parent) child.parent.children.splice(child.parent.children.indexOf(child), 1); child.parent = null; };
    const renderer = Vue.createRenderer({
        createElement: kind => node(kind), createText: text => node('#text', text), createComment: text => node('#comment', text),
        setText: (target, text) => { target.text = text; }, setElementText: (target, text) => { target.text = text; target.children = []; },
        patchProp: (target, key, previous, next) => { target.props[key] = next; },
        insert: (child, parent, anchor) => { remove(child); const index = anchor ? parent.children.indexOf(anchor) : -1; parent.children.splice(index < 0 ? parent.children.length : index, 0, child); child.parent = parent; },
        remove, parentNode: target => target.parent, nextSibling: target => target.parent?.children[target.parent.children.indexOf(target) + 1] ?? null,
    });
    const root = node('root'), events = [];
    const app = renderer.createApp(component, { locale: 'de', messages, ...props, ...(component === active ? { onFinished: id => events.push(['finished', id]), onRefresh: () => events.push(['refresh']) } : {}) });
    app.mount(root); t.after(() => app.unmount());
    const flush = async () => { await new Promise(resolve => setImmediate(resolve)); await Vue.nextTick(); };
    await flush();
    const nodes = kind => descend(root).filter(node => node.kind === kind);
    const click = async (label, index = 0) => { const button = nodes('button').filter(node => text(node) === label)[index]; assert.ok(button, label); await button.props.onClick({}); await flush(); };
    return { root, nodes, click, events, flush };
}
const lesson = (id = 'copy', revision = 7) => ({ id, revision, title: `Title ${id}`, status: 'draft', archived: true, archivedAt: '2026-10-01T12:00:00Z', updatedAt: '2026-09-01T12:00:00Z' });

test('trash uses actual deletion date, cancel makes no mutation, and single purge sends confirmed revision', async t => {
    handle = (path, method) => method === 'POST' ? { deletedIds: ['copy'] } : { lessons: [lesson()] };
    const page = await mount(t, studio);
    assert.equal(page.nodes('time')[0].props.datetime, lesson().archivedAt);
    await page.click('purge_permanently');
    await page.click('cancel');
    assert.equal(calls.filter(call => call[1] === 'POST').length, 0);
    await page.click('purge_permanently');
    await page.click('purge_permanently');
    assert.deepEqual(calls.at(-1), ['/api/studio/lessons/copy/purge', 'POST', { expectedRevision: 7 }]);
    assert.equal(page.nodes('article').length, 0);
});

test('empty trash shows exactly the 100-copy snapshot it submits and preserves remaining copies', async t => {
    const lessons = Array.from({ length: 101 }, (_, index) => lesson(String(index), index + 1));
    handle = (path, method, body) => method === 'POST' ? { deletedIds: body.lessons.map(lesson => lesson.id) } : { lessons };
    const page = await mount(t, studio);
    await page.click('empty_trash');
    assert.equal(page.nodes('li').length, 100);
    assert.deepEqual(page.nodes('li').map(text), lessons.slice(0, 100).map(lesson => lesson.title));
    await page.click('purge_permanently');
    assert.deepEqual(calls.at(-1), ['/api/studio/lessons/trash/purge', 'POST', { lessons: lessons.slice(0, 100).map(({ id, revision }) => ({ id, expectedRevision: revision })) }]);
    assert.equal(page.nodes('article').length, 1);
    assert.match(text(page.root), /Title 100/);
});

test('purge conflict refreshes trash and discards the old confirmation rather than retrying deletion', async t => {
    let loads = 0;
    handle = (path, method) => { if (method === 'POST') throw new ApiError('revision_conflict', 409); return { lessons: [lesson('copy', ++loads === 1 ? 7 : 8)] }; };
    const page = await mount(t, studio);
    await page.click('empty_trash'); await page.click('purge_permanently');
    assert.equal(loads, 2);
    assert.equal(page.nodes('li').length, 0);
    assert.match(text(page.root), /revision_conflict/);
    await page.click('empty_trash');
    handle = (path, method, body) => ({ deletedIds: body.lessons.map(lesson => lesson.id) });
    await page.click('purge_permanently');
    assert.equal(calls.at(-1)[2].lessons[0].expectedRevision, 8);
});

const session = { id: 'class', revision: 12, title: 'Class', status: 'running', createdAt: '2026-10-01T12:00:00Z', joinCode: 'ABCDEFGH', stageCount: 4, participantCount: 2 };
test('finish is dashboard-only, cancel is inert, and confirmed command carries revision and checked acknowledgement', async t => {
    handle = (path, method, body) => ({ acknowledgedCommandId: body.commandId, session: { id: 'class', status: 'finished' } });
    const passive = await mount(t, active, { sessions: [session] });
    assert.equal(passive.nodes('button').length, 0);
    const page = await mount(t, active, { sessions: [session], manageable: true });
    await page.click('finish_remove_session'); await page.click('cancel');
    assert.equal(calls.length, 0);
    await page.click('finish_remove_session'); await page.click('confirm_finish_session');
    const [path, method, body] = calls[0];
    assert.equal(path, '/api/studio/sessions/class/commands'); assert.equal(method, 'POST');
    assert.equal(body.expectedRevision, 12); assert.equal(body.action, 'finish'); assert.deepEqual(body.payload, {});
    assert.match(body.commandId, /^[0-9a-f-]{36}$/);
    assert.deepEqual(page.events, [['finished', 'class']]);
});

test('finish conflict requests refresh and cannot emit success or retain stale confirmation', async t => {
    handle = () => { throw new ApiError('revision_conflict', 409); };
    const page = await mount(t, active, { sessions: [session], manageable: true });
    await page.click('finish_remove_session'); await page.click('confirm_finish_session');
    assert.deepEqual(page.events, [['refresh']]);
    assert.equal(page.nodes('button').filter(node => text(node) === 'confirm_finish_session').length, 0);
});

test('finish retries the same command after uncertain network failure and refuses an unrelated ACK', async t => {
    handle = () => { throw new ApiError('network', 0); };
    const page = await mount(t, active, { sessions: [session], manageable: true });
    await page.click('finish_remove_session'); await page.click('confirm_finish_session');
    handle = () => ({ acknowledgedCommandId: 'other-command', session: { id: 'class', status: 'finished' } });
    await page.click('confirm_finish_session');
    assert.equal(calls[0][2].commandId, calls[1][2].commandId);
    assert.deepEqual(page.events, []);
    assert.match(text(page.root), /request/);
});
