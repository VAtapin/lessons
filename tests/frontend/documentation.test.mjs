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
const source = fs.readFileSync(new URL('../../resources/js/studio/documentation.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { youtubeVideoId } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);
const componentSource = fs.readFileSync(new URL('../../resources/js/studio/LessonDocumentation.vue', import.meta.url), 'utf8');
const { descriptor } = parse(componentSource);
const componentScript = compileScript(descriptor, { id: 'documentation-test', inlineTemplate: true }).content;
const componentJS = ts.transpileModule(componentScript, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText
    .replace(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`);
const { default: LessonDocumentation } = await import(`data:text/javascript;base64,${Buffer.from(componentJS).toString('base64')}`);
const editorSource = fs.readFileSync(new URL('../../resources/js/studio/DocumentationEditor.vue', import.meta.url), 'utf8');
const editorDescriptor = parse(editorSource).descriptor;
const editorScript = compileScript(editorDescriptor, { id: 'documentation-editor-test', inlineTemplate: true }).content;
const editorJS = ts.transpileModule(editorScript, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText
    .replace(/from ['"]vue['"]/g, `from '${pathToFileURL(require.resolve('vue')).href}'`)
    .replace(/from ['"]\.\/documentation['"]/g, `from 'data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}'`)
    .replace(/from ['"]\.\/LessonDocumentation.vue['"]/g, `from 'data:text/javascript;base64,${Buffer.from(componentJS).toString('base64')}'`);
const { default: DocumentationEditor } = await import(`data:text/javascript;base64,${Buffer.from(editorJS).toString('base64')}`);
const messages = { documentation_title: 'Materials', documentation_hint: 'Teacher materials', documentation_plan: 'Plan', documentation_presentation: 'Presentation', documentation_video: 'Video', documentation_video_external: 'External video', documentation_read_plan: 'Read plan', documentation_plan_unavailable: 'No plan in this language' };
const render = documentation => renderToString(createSSRApp(LessonDocumentation, { documentation, messages }));

test('YouTube parser accepts IDs and official watch/share links, refuses lookalikes and unsafe URL forms', () => {
    for (const input of ['GZZkS1DThlg', 'https://www.youtube.com/watch?v=GZZkS1DThlg', 'https://youtube.com/watch?v=GZZkS1DThlg&t=30', 'https://youtu.be/GZZkS1DThlg?si=share']) {
        assert.equal(youtubeVideoId(input), 'GZZkS1DThlg');
    }
    for (const input of ['', 'short', 'javascript:alert(1)', 'http://youtu.be/GZZkS1DThlg', 'https://youtube.com.evil.test/watch?v=GZZkS1DThlg', 'https://user:secret@youtube.com/watch?v=GZZkS1DThlg', 'https://youtube.com:444/watch?v=GZZkS1DThlg', 'https://youtu.be/GZZkS1DThlg/other', 'https://youtube.com/watch?v=GZZkS1DThlg<script>']) {
        assert.equal(youtubeVideoId(input), undefined);
    }
});

test('full Russian and German plans render every paragraph as escaped text without truncation', async () => {
    for (const locale of ['ru', 'de']) {
        const paragraphs = Array.from({ length: 8 }, (_, index) => `${index + 1}. ${locale === 'ru' ? 'Полная инструкция учителю' : 'Vollständige Anweisung für die Lehrkraft'}\n${'long lesson content '.repeat(70)}END-${index}`);
        const html = await render({ plan: paragraphs.join('\n\n') + '\n\n<script>secret()</script>', files: [], video: null });
        for (const paragraph of paragraphs) assert.ok(html.includes(paragraph));
        assert.ok(html.includes('END-7'));
        assert.ok(html.includes('&lt;script&gt;secret()&lt;/script&gt;'));
        assert.equal(html.includes('<script>'), false);
    }
});

test('German plan retains explicit Russian file and video labels without fabricating German downloads', async () => {
    const html = await render({ plan: 'Deutscher Unterrichtsplan', files: [
        { fileId: 'neighbor-plan-ru-v1', kind: 'plan', locale: 'ru' },
        { fileId: 'neighbor-presentation-ru-v1', kind: 'presentation', locale: 'ru', url: '/lesson-files/neighbor-presentation-ru-v1' },
    ], video: { id: 'GZZkS1DThlg', locale: 'ru' } });
    assert.ok(html.includes('Deutscher Unterrichtsplan'));
    assert.ok(html.includes('Plan · RU'));
    assert.ok(html.includes('Presentation · RU'));
    assert.ok(html.includes('Video · RU'));
    assert.ok(html.includes('href="/lesson-files/neighbor-plan-ru-v1"'));
    assert.ok(html.includes('href="https://www.youtube.com/watch?v=GZZkS1DThlg"'));
    assert.equal(html.includes('Plan · DE'), false);
    assert.equal(html.includes('Presentation · DE'), false);
});

test('missing localized plan reports absence while actual source files remain available', async () => {
    const html = await render({ plan: null, files: [{ fileId: 'actual-plan', kind: 'plan', locale: 'ru' }], video: null });
    assert.ok(html.includes('No plan in this language'));
    assert.ok(html.includes('href="/lesson-files/actual-plan"'));
    assert.equal(html.includes('documentation-plan-text'), false);
});

test('editor preserves source video language even when released lesson contains only German', async () => {
    const document = { locales: ['de'], documentation: { schemaVersion: 1, content: { de: { plan: 'Deutsch vollständig' } }, files: [], video: { id: 'GZZkS1DThlg', locale: 'ru' } } };
    const before = JSON.stringify(document);
    const html = await renderToString(createSSRApp(DocumentationEditor, { document, locale: 'de', messages }));
    assert.match(html, /<option[^>]*value="ru"[^>]*>RU<\/option>/);
    assert.match(html, /<option[^>]*value="de"[^>]*>DE<\/option>/);
    assert.ok(html.includes('Video · RU'));
    assert.equal(JSON.stringify(document), before);
});

test('editor never fills missing German plan with Russian source text', async () => {
    const document = { locales: ['ru', 'de'], documentation: { schemaVersion: 1, content: { ru: { plan: 'Только русский план' } }, files: [] } };
    const html = await renderToString(createSSRApp(DocumentationEditor, { document, locale: 'de', messages }));
    assert.ok(html.includes('No plan in this language'));
    assert.equal(html.includes('Только русский план'), false);
    assert.equal(document.documentation.content.de, undefined);
});

test('supplied document labels distinguish DOCX, PDF and scenario files without changing pinned links', async () => {
    const html = await render({ plan: null, video: null, files: [{ fileId: 'words-docx', kind: 'plan', locale: 'ru', label: 'Сценарий · DOCX', url: '/lesson-files/words-docx' }] });
    assert.match(html, /Сценарий · DOCX/);
    assert.match(html, /href="\/lesson-files\/words-docx"/);
    assert.doesNotMatch(html, />Plan/);
});
