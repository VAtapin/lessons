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
const voiceSource = parse(fs.readFileSync(new URL('../../resources/js/studio/ClassVoice.vue', import.meta.url), 'utf8')).descriptor;
const voice = url(compile(compileScript(voiceSource, { id: 'class-voice-render-test', inlineTemplate: true }).content).replace(/from ['"]vue['"]/g, `from ${JSON.stringify(pathToFileURL(require.resolve('vue')).href)}`));
const source = fs.readFileSync(new URL('../../resources/js/studio/InteractiveBlock.vue', import.meta.url), 'utf8');
const { descriptor } = parse(source);
const script = compileScript(descriptor, { id: 'sequence-render-test', inlineTemplate: true });
const compiled = compile(script.content).replace(/from ['"]vue['"]/g, `from ${JSON.stringify(pathToFileURL(require.resolve('vue')).href)}`)
    .replace(/from ['"]\.\/interactive['"]/, `from ${JSON.stringify(helper('interactive.ts'))}`)
    .replace(/from ['"]\.\/sequence-input['"]/, `from ${JSON.stringify(helper('sequence-input.ts'))}`)
    .replace(/from ['"]\.\/ClassVoice.vue['"]/, `from ${JSON.stringify(voice)}`);
const component = (await import(url(compiled))).default;
const messages = { sequence_expected: 'Expected', answer_incorrect: 'Incorrect', answer_correct: 'Correct' };
const block = () => ({ id: 'path', type: 'core.sequence', config: { allowRepeat: true }, content: { question: 'Assemble the road', items: [{ itemId: 'saw', text: 'Saw' }, { itemId: 'approached', text: 'Approached' }, { itemId: 'helped', text: 'Helped' }] }, runtime: { status: 'open' } });
const answer = { value: { itemIds: ['helped', 'approached', 'saw'] }, grade: null, status: 'submitted' };
const render = block => renderToString(createSSRApp(component, { block, answer, interactive: true, conducting: true, messages }));

test('a saved wrong road remains learner-selected without expected positions before joint review', async () => {
    const html = await render(block());
    assert.doesNotMatch(html, /sequence-expected/);
    assert.doesNotMatch(html, /Expected:/);
    const road = html.slice(html.indexOf('<ol'), html.indexOf('</ol>'));
    assert.ok(road.indexOf('Helped') < road.indexOf('Approached'));
    assert.ok(road.indexOf('Approached') < road.indexOf('Saw'));
});

test('joint review labels incorrect positions with the actually revealed expected item while keeping the own road', async () => {
    const stageBlock = block();
    stageBlock.runtime = { status: 'revealed', results: { itemIds: ['saw', 'approached', 'helped'] } };
    const html = await render(stageBlock);
    assert.equal((html.match(/class="sequence-expected"/g) ?? []).length, 2);
    assert.match(html, /Expected: Saw/);
    assert.match(html, /Expected: Helped/);
    assert.doesNotMatch(html, /Expected: Approached/);
    assert.match(html, />1 \/ 3<\/p>/);
});

test('a graded choice shows anonymous class votes while its correct answer remains unrevealed', async () => {
    const quiz = { id: 'quiz', type: 'core.single-choice', content: { question: 'Who helped?', options: [{ optionId: 'a', text: 'Priest' }, { optionId: 'b', text: 'Samaritan' }] }, config: {},
        runtime: { status: 'open', summary: { totalAnswers: 3, counts: [{ optionId: 'a', count: 1 }, { optionId: 'b', count: 2 }] } } };
    const html = await renderToString(createSSRApp(component, { block: quiz, interactive: true, conducting: true, messages: { ...messages, class_voice: 'Class voice' } }));
    assert.match(html, /Class voice · 3/);
    assert.match(html, /Priest<\/span><strong>1<\/strong>/);
    assert.match(html, /Samaritan<\/span><strong>2<\/strong>/);
    assert.doesNotMatch(html, /class="option-result/);
});

test('role choices show live distribution using role labels and hide the empty class voice', async () => {
    const roles = { id: 'roles', type: 'core.roles', content: { question: 'Choose a role', roles: [{ roleId: 'a', text: 'Traveler <one>' }, { roleId: 'b', text: 'Samaritan' }] }, config: {},
        runtime: { status: 'open', summary: { totalAnswers: 1, counts: [{ optionId: 'a', count: 0 }, { optionId: 'b', count: 1 }] } } };
    const html = await renderToString(createSSRApp(component, { block: roles, interactive: true, conducting: true, messages: { class_voice: 'Class voice' } }));
    assert.match(html, /Class voice · 1/);
    assert.match(html, /Traveler &lt;one&gt;<\/span><strong>0<\/strong>/);
    assert.match(html, /Samaritan<\/span><strong>1<\/strong>/);
    roles.runtime.summary.totalAnswers = 0;
    const empty = await renderToString(createSSRApp(component, { block: roles, interactive: true, conducting: true, messages: { class_voice: 'Class voice' } }));
    assert.doesNotMatch(empty, /class="class-voice"/);
});
