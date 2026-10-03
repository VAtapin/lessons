import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import ts from 'typescript';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
const require = createRequire(import.meta.url);
const url = source => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const compile = source => ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const helper = file => url(compile(fs.readFileSync(new URL('../../resources/js/studio/' + file, import.meta.url), 'utf8')));
const stub = url('export default { render: () => null };');
const finishedDescriptor = parse(fs.readFileSync(new URL('../../resources/js/studio/FinishedLesson.vue', import.meta.url), 'utf8')).descriptor;
const finished = url(compile(compileScript(finishedDescriptor, { id: 'finished-lesson-test', inlineTemplate: true }).content)
    .replace(/from ['"]vue['"]/g, `from ${JSON.stringify(pathToFileURL(require.resolve('vue')).href)}`));
// Inject a server DTO as the initial state; polling and nested renderers are outside this page-status test.
const source = fs.readFileSync(new URL('../../resources/js/studio/PublicSession.vue', import.meta.url), 'utf8').replace('const session = ref<PublicState>();', 'const session = ref<PublicState>(globalThis.__publicCounterFixture);').replace(/import ['"]\.\.\/\.\.\/css\/public-lesson-app.css['"];?/, '');
const { descriptor } = parse(source);
const script = compileScript(descriptor, { id: 'public-session-counter-test', inlineTemplate: true });
const compiled = compile(script.content).replace(/from ['"]vue['"]/g, `from ${JSON.stringify(pathToFileURL(require.resolve('vue')).href)}`)
    .replace(/from ['"]\.\/runtime['"]/, `from ${JSON.stringify(helper('runtime.ts'))}`)
    .replace(/from ['"]\.\/public-session['"]/, `from ${JSON.stringify(helper('public-session.ts'))}`)
    .replace(/from ['"]\.\/api['"]/, `from ${JSON.stringify(url('export const poll=()=>{}; export const api=()=>{}; export class ApiError extends Error{}; export const errorMessage=()=>"Error";'))}`)
    .replace(/from ['"]\.\/StageRenderer.vue['"]/, `from ${JSON.stringify(stub)}`)
    .replace(/from ['"]\.\/FinishedLesson.vue['"]/, `from ${JSON.stringify(finished)}`)
    .replace(/from ['"]\.\/RuntimeStatus.vue['"]/, `from ${JSON.stringify(stub)}`);
const component = (await import(url(compiled))).default;
async function render(mode, points, state = {}) {
    globalThis.__publicCounterFixture = { id: 'real-session', status: 'running', locale: 'de', currentStageId: 'stage', kindnessPoints: points, stage: { id: 'stage', blocks: [], content: { title: 'Stage' }, config: {} }, ...state };
    try { return await renderToString(createSSRApp(component, { mode, messages: { kindness_points: 'Kindness points', lesson_close_window: 'Close window' } })); }
    finally { delete globalThis.__publicCounterFixture; }
}

test('student status renders both earned and zero actual kindnessPoints from the server DTO', async () => {
    for (const points of [0, 4]) {
        const html = await render('student', points);
        assert.match(html, /public-lesson-points/);
        assert.match(html, /class="public-lesson-stage-title">Stage<\/strong>/);
        assert.match(html, new RegExp(`✦<\\/span> ${points}<\\/span>`));
    }
});

test('picture-count header follows the displayed mode without announcing the missing sheep early', async () => {
    const block = { type: 'core.presentation', config: { kind: 'picture-count' }, content: { modes: [{ modeId: 'herd', title: 'Our little herd' }, { modeId: 'missing', title: 'One missing' }] }, runtime: { presentation: {} } };
    const stage = { id: 'stage', content: { title: 'One missing' }, config: { theme: 'lavender' }, blocks: [block] };
    for (const mode of ['student', 'projector']) {
        assert.match(await render(mode, 0, { stage }), /public-lesson-stage-title">Our little herd<\/strong>/);
        block.runtime.presentation.modeId = 'missing';
        assert.match(await render(mode, 0, { stage }), /public-lesson-stage-title">One missing<\/strong>/);
        delete block.runtime.presentation.modeId;
    }
});

test('lesson palettes reach student and projector during the lesson and closing', async () => {
    const closing = { content: { title: 'Finished', text: 'Reflection' } };
    for (const theme of ['slate', 'lavender', 'ocean', 'berry', 'cobalt', 'plum', 'copper', 'indigo', 'rose', 'graphite', 'sand', 'steel', 'wine', 'umber', 'azure', 'ochre']) for (const mode of ['student', 'projector']) {
        const stage = { id: 'stage', content: { title: 'Context' }, blocks: [], config: { theme } };
        for (const status of ['running', 'finished']) {
            const html = await render(mode, 0, { stage, status, closing });
            assert.match(html, new RegExp(`conducting-app theme-${theme}`));
            if (status === 'finished') assert.match(html, /class="finished-lesson"/);
        }
        assert.match(await render(mode, 0), /conducting-app theme-green/);
    }
});

test('projector and responses without an actual personal score never synthesize a counter', async () => {
    assert.doesNotMatch(await render('projector', 4), /public-lesson-points/);
    assert.doesNotMatch(await render('student', undefined), /public-lesson-points/);
});

test('public conducting layouts override the legacy projector width cap', () => {
    const css = fs.readFileSync(new URL('../../resources/css/public-lesson-app.css', import.meta.url), 'utf8');
    const legacy = fs.readFileSync(new URL('../../resources/css/studio.css', import.meta.url), 'utf8');
    assert.match(legacy, /\.public-session\.projector\s*\{\s*max-width:\s*1280px/);
    assert.match(css, /\.conducting-app\.conducting-app\.public-session\.public-lesson-app\s*\{[^}]*width:\s*100%;\s*max-width:\s*none;/);
});

test('teacher drawer backdrop remains translucent when the pointer moves over the scene', () => {
    const css = fs.readFileSync(new URL('../../resources/css/public-lesson-app.css', import.meta.url), 'utf8');
    assert.match(css, /\.conducting-app\.conducting-app \.focus-backdrop,\s*\.conducting-app\.conducting-app \.focus-backdrop:hover:not\(:disabled\)\s*\{\s*background: #3d241745;/);
});

test('finished student closes the window while the projector retains the locale return link', async () => {
    const closing = { content: { title: 'Original closing title', eyebrow: 'Original ending', text: 'Exact released thanks', quote: 'Exact released quotation', source: 'Original source' } };
    for (const mode of ['student', 'projector']) {
        const html = await render(mode, undefined, { status: 'finished', closing });
        assert.match(html, /class="finished-lesson"/);
        assert.doesNotMatch(html, /class="public-lesson-status"/);
        for (const text of Object.values(closing.content)) assert.ok(html.includes(text));
        if (mode === 'student') {
            assert.match(html, /<button[^>]*finished-return[^>]*>Close window<\/button>/);
            assert.doesNotMatch(html, /href="\/de\/catalog"/);
        } else assert.match(html, /href="\/de\/catalog"/);
        assert.doesNotMatch(await render(mode, undefined, { status: 'running', closing }), /class="finished-lesson"/);
    }
});

test('student closing invokes browser close and explains how to close a protected tab', async () => {
    const previousWindow = globalThis.window;
    let closes = 0;
    globalThis.window = { close() { closes++; } };
    try {
        const closingComponent = (await import(finished)).default;
        const renderClosing = closingComponent.setup({ student: true, returnUrl: '/de/catalog', messages: { lesson_close_window: 'Close window', lesson_close_window_hint: 'Close this browser tab manually.' } }, { expose() {} });
        const before = renderClosing({}, []);
        const button = before.children.find(child => child.type === 'button');
        assert.ok(button);
        button.props.onClick();
        assert.equal(closes, 1);
        const after = renderClosing({}, []);
        assert.ok(after.children.some(child => child.props?.role === 'status' && child.children === 'Close this browser tab manually.'));
        assert.ok(!after.children.some(child => child.type === 'a'));
    } finally { globalThis.window = previousWindow; }
});
