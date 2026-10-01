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
// Inject a server DTO as the initial state; polling and nested renderers are outside this page-status test.
const source = fs.readFileSync(new URL('../../resources/js/studio/PublicSession.vue', import.meta.url), 'utf8').replace('const session = ref<PublicState>();', 'const session = ref<PublicState>(globalThis.__publicCounterFixture);').replace(/import ['"]\.\.\/\.\.\/css\/public-lesson-app.css['"];?/, '');
const { descriptor } = parse(source);
const script = compileScript(descriptor, { id: 'public-session-counter-test', inlineTemplate: true });
const compiled = compile(script.content).replace(/from ['"]vue['"]/g, `from ${JSON.stringify(pathToFileURL(require.resolve('vue')).href)}`)
    .replace(/from ['"]\.\/runtime['"]/, `from ${JSON.stringify(helper('runtime.ts'))}`)
    .replace(/from ['"]\.\/public-session['"]/, `from ${JSON.stringify(helper('public-session.ts'))}`)
    .replace(/from ['"]\.\/api['"]/, `from ${JSON.stringify(url('export const poll=()=>{}; export const api=()=>{}; export class ApiError extends Error{}; export const errorMessage=()=>"Error";'))}`)
    .replace(/from ['"]\.\/StageRenderer.vue['"]/, `from ${JSON.stringify(stub)}`)
    .replace(/from ['"]\.\/RuntimeStatus.vue['"]/, `from ${JSON.stringify(stub)}`);
const component = (await import(url(compiled))).default;
async function render(mode, points) {
    globalThis.__publicCounterFixture = { id: 'real-session', status: 'running', currentStageId: 'stage', kindnessPoints: points, stage: { id: 'stage', blocks: [], content: { title: 'Stage' }, config: {} } };
    try { return await renderToString(createSSRApp(component, { mode, messages: { kindness_points: 'Kindness points' } })); }
    finally { delete globalThis.__publicCounterFixture; }
}

test('student status renders both earned and zero actual kindnessPoints from the server DTO', async () => {
    for (const points of [0, 4]) {
        const html = await render('student', points);
        assert.match(html, /public-lesson-points/);
        assert.match(html, new RegExp(`✦<\\/span> ${points}<\\/span>`));
    }
});

test('projector and responses without an actual personal score never synthesize a counter', async () => {
    assert.doesNotMatch(await render('projector', 4), /public-lesson-points/);
    assert.doesNotMatch(await render('student', undefined), /public-lesson-points/);
});
