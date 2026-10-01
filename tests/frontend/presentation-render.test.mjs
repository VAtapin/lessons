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
const source = fs.readFileSync(new URL('../../resources/js/studio/PresentationBlock.vue', import.meta.url), 'utf8');
const { descriptor } = parse(source);
const script = compileScript(descriptor, { id: 'presentation-render-test', inlineTemplate: true });
const compiled = ts.transpileModule(script.content, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText.replace(/from ['"]vue['"]/g, `from ${JSON.stringify(pathToFileURL(require.resolve('vue')).href)}`);
const component = (await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`)).default;
const messages = { presentation_show: 'Show', presentation_hide: 'Hide', board_discuss: 'Discuss', board_discussed: 'Discussed', board_waiting: 'Waiting' };
const render = (block, presenter = false) => renderToString(createSSRApp(component, { block, presenter, conducting: true, messages }));

test('hidden reveal content stays out of public DOM even if a legacy response contains it', async () => {
    const block = { type: 'core.presentation', config: { kind: 'reveal' }, content: { text: 'Hidden explanation' }, runtime: { presentation: { visible: false } } };
    assert.equal((await render(block)).includes('Hidden explanation'), false);
    assert.equal((await render(block, true)).includes('Hidden explanation'), false);
    assert.match(await render(block, true), /Show/);
    block.runtime.presentation.visible = true;
    assert.match(await render(block), /Hidden explanation/);
    assert.doesNotMatch(await render(block), /<button/);
});

test('anonymous discussed board renders saved text and keeps public readers free of presenter actions', async () => {
    const block = { type: 'core.presentation', config: { kind: 'response-board' }, content: {}, runtime: { board: [{ answerId: 5, text: '<real anonymous answer>', discussed: true }] } };
    const html = await render(block);
    assert.match(html, /response-bubble discussed/);
    assert.match(html, /&lt;real anonymous answer&gt;/);
    assert.doesNotMatch(html, /<button/);
    assert.match(await render(block, true), /aria-pressed="true"/);
});

test('discussion shows the server-selected question to every reader and short labels on presenter buttons', async () => {
    const block = { type: 'core.presentation', config: { kind: 'discussion' }, content: { text: 'Introduction', modes: [{ modeId: 'priest', text: 'What could the priest still do?', label: 'Priest' }, { modeId: 'samaritan', text: 'Could the Samaritan find the same excuse?', label: 'Samaritan' }] }, runtime: { presentation: { modeId: 'samaritan' } } };
    const publicHtml = await render(block);
    assert.match(publicHtml, /Introduction/);
    assert.match(publicHtml, /Could the Samaritan find the same excuse\?/);
    assert.doesNotMatch(publicHtml, /What could the priest still do\?/);
    const teacherHtml = await render(block, true);
    assert.match(teacherHtml, />Priest<\/button>/);
    assert.match(teacherHtml, /aria-pressed="true"[^>]*>Samaritan<\/button>/);
});
