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

test('signal distribution preserves authored labels instead of replacing lesson words', async () => {
    const signals = { id: 'signals', type: 'core.signals', config: {}, content: { readyLabel: 'Готов ✦', questionLabel: 'Есть вопрос' }, runtime: { status: 'open', summary: { totalAnswers: 1, ready: 1, question: 0 } } };
    const html = await renderToString(createSSRApp((await import(voice)).default, { block: signals, messages: { class_voice: 'Голос класса', ready: 'Generic ready', question_signal: 'Generic question' } }));
    assert.match(html, /Готов ✦<\/span><strong>1<\/strong>/);
    assert.match(html, /Есть вопрос<\/span><strong>0<\/strong>/);
    assert.doesNotMatch(html, /Generic ready|Generic question/);
});

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
        runtime: { status: 'open', mode: 'lesson', presentation: { revealedRoleIds: ['a', 'b'] }, summary: { totalAnswers: 1, counts: [{ optionId: 'a', count: 0 }, { optionId: 'b', count: 1 }] } } };
    const html = await renderToString(createSSRApp(component, { block: roles, interactive: true, conducting: true, messages: { class_voice: 'Class voice' } }));
    assert.match(html, /Class voice · 1/);
    assert.match(html, /Traveler &lt;one&gt;<\/span><strong>0<\/strong>/);
    assert.match(html, /Samaritan<\/span><strong>1<\/strong>/);
    roles.runtime.summary.totalAnswers = 0;
    const empty = await renderToString(createSSRApp(component, { block: roles, interactive: true, conducting: true, messages: { class_voice: 'Class voice' } }));
    assert.doesNotMatch(empty, /class="class-voice"/);
});

test('classroom role buttons and class votes expose only roles revealed by the presenter', async () => {
    const roles = { id: 'roles', type: 'core.roles', content: { question: 'Choose a role', roles: [{ roleId: 'a', text: 'Traveler' }, { roleId: 'b', text: 'Hidden Samaritan' }] }, config: {},
        runtime: { status: 'open', mode: 'lesson', presentation: { revealedRoleIds: ['a'] }, summary: { totalAnswers: 1, counts: [{ optionId: 'b', count: 1 }] } } };
    const html = await renderToString(createSSRApp(component, { block: roles, interactive: true, conducting: true, messages: { class_voice: 'Class voice' } }));
    assert.match(html, /Traveler/);
    assert.doesNotMatch(html, /Hidden Samaritan/);
    assert.equal((html.match(/class="role-card/g) ?? []).length, 1);
    roles.runtime.presentation.revealedRoleIds = [];
    const reset = await renderToString(createSSRApp(component, { block: roles, interactive: true, conducting: true, messages: { class_voice: 'Class voice' } }));
    assert.doesNotMatch(reset, /Traveler|Hidden Samaritan/);
    assert.doesNotMatch(reset, /class="role-card/);
});

test('authoring preview and private rehearsal retain all reusable role choices', async () => {
    for (const runtime of [undefined, { status: 'open', mode: 'rehearsal', presentation: { revealedRoleIds: [] } }]) {
        const roles = { id: 'roles', type: 'core.roles', content: { roles: [{ roleId: 'a', text: 'Traveler' }, { roleId: 'b', text: 'Samaritan' }] }, config: {}, runtime };
        const html = await renderToString(createSSRApp(component, { block: roles, interactive: true, conducting: !!runtime, messages }));
        assert.match(html, /Traveler/);
        assert.match(html, /Samaritan/);
        assert.equal((html.match(/class="role-card/g) ?? []).length, 2);
    }
});

test('shared projector road retains the left passive pool and right saved shared order without answer actions', async () => {
    const path = block();
    path.runtime.presentation = { itemIds: ['helped', 'saw'] };
    const html = await renderToString(createSSRApp(component, { block: path, conducting: true, messages }));
    assert.ok(html.indexOf('class="sequence-pool"') < html.indexOf('class="sequence-road-panel"'));
    assert.match(html, /class="sequence-tile sequence-tile-used"/);
    assert.doesNotMatch(html, /<button/);
    const road = html.slice(html.indexOf('<ol'), html.indexOf('</ol>'));
    assert.ok(road.indexOf('Helped') < road.indexOf('Saw'));
    assert.doesNotMatch(road, /Approached/);
});

test('conducting free response renders one native text field with length counter; authoring keeps multiline preview', async () => {
    const free = { id: 'free', type: 'core.free-response', content: { question: 'What would you do?' }, config: { maxLength: 120 }, runtime: { status: 'open' } };
    const props = { block: free, interactive: true, answer: { value: { text: '😀Help' }, grade: null, status: 'submitted' }, messages: { your_answer: 'Your answer', answer_placeholder: 'Type an answer' } };
    const html = await renderToString(createSSRApp(component, { ...props, conducting: true }));
    assert.match(html, /class="free-response-input" type="text" required maxlength="120" placeholder="Type an answer" value="😀Help"/);
    assert.match(html, /<small>5 \/ 120<\/small>/);
    assert.doesNotMatch(html, /<textarea/);
    const preview = await renderToString(createSSRApp(component, { ...props, conducting: false }));
    assert.match(preview, /<textarea required rows="4" maxlength="120"/);
    assert.doesNotMatch(preview, /free-response-input/);
});
