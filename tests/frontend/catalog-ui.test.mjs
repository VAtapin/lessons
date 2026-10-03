import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import ts from 'typescript';
import * as Vue from 'vue';
import { renderToString } from '@vue/server-renderer';
import { parse, compileScript } from '@vue/compiler-sfc';

const read = file => fs.readFileSync(new URL('../../resources/js/' + file, import.meta.url), 'utf8');
const location = { search: '', assign: url => navigations.push(url) };
const navigations = [];
const stub = { __esModule: true, default: { render: () => null } };
const accountState = Vue.ref(null);
function evaluate(source, dependencies = {}) {
    const exports = {};
    const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;
    vm.runInNewContext(code, { exports, URLSearchParams, AbortController, DOMException, window: { location }, require: name => {
        if (name.endsWith('.png') || name.endsWith('.webp')) return { __esModule: true, default: '/test-image.png' };
        assert.ok(name in dependencies, `Unexpected dependency: ${name}`);
        return dependencies[name];
    } });
    return exports;
}
const filters = evaluate(read('catalog/filters.ts'));
function component(file, dependencies) {
    return evaluate(compileScript(parse(read(file)).descriptor, { id: file, inlineTemplate: true }).content, { vue: Vue, ...dependencies }).default;
}
const app = component('App.vue', { './studio/api': { api: async () => ({}) }, './studio/identity': { accountState, acceptAccount() {} }, './catalog/CatalogPage.vue': stub, './catalog/PublicIcon.vue': stub, './catalog/filters': filters });
const picker = component('catalog/CatalogFilters.vue', { '../studio/api': { api: async () => ({ terms: [] }) }, './filters': filters, './PublicIcon.vue': stub });
const messages = { any_filter: 'All', start_selection: 'Start selection', view_topics: 'View topics', sign_in: 'Sign in', my_workspace: 'Workspace' };

test('home has no filter panel and all selection entry points lead to the localized catalog', async () => {
    location.search = '';
    for (const locale of ['ru', 'de']) {
        accountState.value = null;
        const html = await renderToString(Vue.createSSRApp(app, { locale, messages }));
        assert.doesNotMatch(html, /material-picker|id="find-materials"|href="#find-materials"/);
        assert.ok(html.includes(`class="header-search" href="/${locale}/catalog#find-materials"`));
        assert.ok(html.includes(`href="/${locale}/catalog">Start selection`));
        assert.ok(html.includes(`href="/${locale}/catalog">View topics`));
        assert.ok(html.includes(`href="/${locale}/login">Sign in`));
        accountState.value = { user: { id: 1 } };
        const signedIn = await renderToString(Vue.createSSRApp(app, { locale, messages }));
        assert.ok(signedIn.includes(`href="/${locale}/studio?view=overview">Workspace`));
    }
    accountState.value = null;
});

function mount(t, extra = {}) {
    navigations.length = 0;
    const node = (kind, text = '') => ({ kind, text, children: [], parent: null, props: {}, listeners: {}, value: '', selected: false,
        get options() { return this.children.filter(child => child.kind === 'option'); },
        addEventListener(name, callback) { this.listeners[name] = callback; },
    });
    const remove = child => { if (child.parent) child.parent.children.splice(child.parent.children.indexOf(child), 1); child.parent = null; };
    const renderer = Vue.createRenderer({
        createElement: kind => node(kind), createText: text => node('#text', text), createComment: text => node('#comment', text),
        setText: (target, text) => { target.text = text; }, setElementText: (target, text) => { target.text = text; target.children = []; },
        patchProp: (target, key, previous, next) => { target.props[key] = next; if (key === 'value') { target.value = next; target._value = next; } },
        insert: (child, parent, anchor) => { remove(child); const index = anchor ? parent.children.indexOf(anchor) : -1; parent.children.splice(index < 0 ? parent.children.length : index, 0, child); child.parent = parent; },
        remove, parentNode: target => target.parent, nextSibling: target => target.parent?.children[target.parent.children.indexOf(target) + 1] ?? null,
    });
    const root = node('root');
    const instance = renderer.createApp(picker, { locale: 'de', messages, full: true, terms: null, ...extra });
    instance.mount(root); t.after(() => instance.unmount());
    const descendants = node => [node, ...node.children.flatMap(descendants)];
    const nodes = kind => descendants(root).filter(node => node.kind === kind);
    return { nodes, submit: () => nodes('form')[0].props.onSubmit({ preventDefault() {} }), select: async (name, value) => {
        const select = nodes('select').find(node => node.props.name === name);
        assert.ok(select.options.some(option => option.value === value));
        select.options.forEach(option => { option.selected = option.value === value; });
        select.listeners.change(); await Vue.nextTick();
    } };
}

test('native facets submit one value each, clear independently, and preserve search and duration', async t => {
    location.search = '?age=7-10&audience=school&topic=mercy&format=game&q=%D0%9C%D0%B8%D1%80&duration=short';
    const page = mount(t);
    assert.equal(page.nodes('select').length, 5);
    assert.equal(page.nodes('button').length, 1, 'Facets are selects, not option button panels');
    assert.equal(page.nodes('input')[0].value, 'Мир');
    await page.select('topic', 'family');
    await page.select('age', '');
    page.submit();
    const url = new URL(navigations[0], 'https://lessons.example');
    assert.equal(url.pathname, '/de/catalog');
    assert.deepEqual(Object.fromEntries(url.searchParams), { audience: 'school', topic: 'family', format: 'game', q: 'Мир', duration: 'short' });
    assert.equal(page.nodes('a')[0].props.href, '/de/catalog');
});

test('custom taxonomy options remain selectable and loading blocks navigation', async t => {
    location.search = '?topic=custom';
    const terms = [{ id: 1, kind: 'topic', key: 'custom', label: 'Custom topic', active: true, sortOrder: 0 }];
    const page = mount(t, { terms });
    assert.ok(page.nodes('option').some(option => option.text === 'Custom topic'));
    page.submit();
    assert.equal(navigations[0], '/de/catalog?topic=custom');
    const loading = mount(t, { terms, taxonomyLoading: true });
    assert.ok(loading.nodes('select').every(select => select.props.disabled));
    loading.submit();
    assert.deepEqual(navigations, []);
});


test('material cards render direct file downloads and a separate related lesson link', async () => {
    location.search = '?format=presentation';
    const catalog = component('catalog/CatalogPage.vue', {
        '../studio/api': { api: async () => ({}), ApiError: class extends Error {} },
        './CatalogFilters.vue': stub, './PublicIcon.vue': stub, '../studio/ActiveSessionList.vue': stub,
        './start-session': { catalogStartDecision() {} }, '../studio/StageRenderer.vue': stub,
        '../studio/LessonDocumentation.vue': stub, './filters': filters,
    });
    const html = await renderToString(Vue.createSSRApp(catalog, { locale: 'ru', messages: { download_material: 'Download', related_lesson: 'Related lesson' }, initial: {
        entries: [{ slug: 'source-lesson', materialId: 'slides', title: 'Slides', description: '', format: ['presentation'], locales: ['ru'], topic: [], downloads: [{ url: '/lesson-files/slides', extension: 'PPTX', bytes: 200 }], coverUrl: null }],
        pagination: { page: 1, total: 1, lastPage: 1, perPage: 12 },
    } }));
    assert.match(html, /href="\/lesson-files\/slides"[^>]*download/);
    assert.match(html, /Download PPTX/);
    assert.match(html, /href="\/ru\/catalog\/source-lesson"/);
    assert.match(html, /Related lesson/);
});
