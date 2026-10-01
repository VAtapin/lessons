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

test('authored scene retains its exact title, question, subtitle and source in the shared renderer', async () => {
    const html = await render({ type: 'core.presentation', config: { kind: 'scene', scene: 'story' }, content: {
        eyebrow: 'Сценка · Лк 10:30', title: 'Человек на дороге', subtitle: 'Кого позовём первым?',
        text: 'Путник шёл из Иерусалима в Иерихон. Разбойники ограбили и избили его. Он остался ждать помощи.', source: 'Лк 10:30', quote: '«Иди, и ты поступай так же»',
    } });
    assert.match(html, /Сценка · Лк 10:30/);
    assert.match(html, /Человек на дороге/);
    assert.match(html, /Кого позовём первым\?/);
    assert.match(html, /Путник шёл из Иерусалима в Иерихон\. Разбойники ограбили и избили его\. Он остался ждать помощи\./);
    assert.match(html, /class="scene-source">Лк 10:30/);
    assert.match(html, /class="scene-quote">«Иди, и ты поступай так же»/);
    assert.doesNotMatch(html, /<button|<input/);
});

test('lesson summary keeps the three authored takeaway cards, quote and source', async () => {
    const html = await render({ type: 'core.presentation', config: { kind: 'summary' }, content: {
        eyebrow: 'Итог урока', title: 'Ближним становятся', text: 'Не вопрос «кто достоин моей помощи?», а решение: «чьим ближним могу стать я?»',
        items: [
            { itemId: 'notice', label: 'Увидеть', text: 'заметить человека и его нужду' },
            { itemId: 'approach', label: 'Подойти', text: 'не прятаться за удобным оправданием' },
            { itemId: 'help', label: 'Помочь', text: 'сделать конкретный посильный шаг' },
        ], quote: '«Иди, и ты поступай так же»', source: 'Лк 10:37', subtitle: 'Назовите одним словом, что вы уносите с этого урока.',
    } });
    assert.match(html, /Ближним становятся/);
    assert.match(html, /Не вопрос «кто достоин моей помощи\?», а решение: «чьим ближним могу стать я\?»/);
    assert.equal((html.match(/class="takeaway-index"/g) ?? []).length, 3);
    assert.ok(html.indexOf('Увидеть') < html.indexOf('Подойти'));
    assert.ok(html.indexOf('Подойти') < html.indexOf('Помочь'));
    for (const text of ['заметить человека и его нужду', 'не прятаться за удобным оправданием', 'сделать конкретный посильный шаг', '«Иди, и ты поступай так же»', 'Лк 10:37']) assert.ok(html.includes(text));
    assert.match(html, /class="summary-subtitle">Назовите одним словом, что вы уносите с этого урока\./);
    assert.doesNotMatch(html, /<button|<input/);
});
