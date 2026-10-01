import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
import { parse, compileScript } from '@vue/compiler-sfc';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';

const read = path => fs.readFileSync(new URL(path, import.meta.url), 'utf8');
const compile = source => ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const data = source => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const { createWavePlayback } = await import(data(compile(read('../../resources/js/studio/kindness-wave.ts'))));
const state = (id = 'wave-one', remaining = 6000) => ({ id: 'class-one', status: 'running', serverNow: '2026-10-01T12:00:00.000Z', wave: { id, expiresAt: new Date(Date.parse('2026-10-01T12:00:00.000Z') + remaining).toISOString(), aggregate: { points: 7, participants: 3 } } });

test('celebration lasts the original 3.6 seconds, never restarts on polling or refresh', () => {
    const values = new Map();
    const storage = { getItem: key => values.get(key), setItem: (key, value) => values.set(key, value) };
    const play = createWavePlayback(storage);
    assert.equal(play(state()), 3600);
    assert.equal(play(state()), 0);
    assert.equal(createWavePlayback(storage)(state()), 0);
    assert.equal(play(state('wave-two', 850)), 850);
    assert.equal(play({ ...state('wave-two'), id: 'other-class' }), 3600);
});

test('expired and finished waves cannot play; unavailable storage retains an in-page guard', () => {
    const play = createWavePlayback({ getItem() { throw Error('restricted'); }, setItem() { throw Error('restricted'); } });
    assert.equal(play(state('expired', 0)), 0);
    assert.equal(play({ ...state(), status: 'finished' }), 0);
    assert.equal(play(state()), 3600);
    assert.equal(play(state()), 0);
});

const { descriptor } = parse(read('../../resources/js/studio/KindnessWave.vue'));
const vueUrl = pathToFileURL(createRequire(import.meta.url).resolve('vue')).href;
const code = compile(compileScript(descriptor, { id: 'wave-test', inlineTemplate: true }).content)
    .replace(/import ['"].*\.css['"];?/g, '')
    .replace(/from ['"]vue['"]/g, `from '${vueUrl}'`);
const component = (await import(data(code))).default;
test('full-screen celebration announces real anonymous contribution including zero', async () => {
    const messages = { wave_title: 'Wave!', wave_subtitle: 'Every good deed', wave_contribution: ':points rays · :participants contributors' };
    const html = await renderToString(createSSRApp(component, { wave: state().wave, messages }));
    assert.match(html, /7 rays · 3 contributors/);
    assert.match(html, /aria-atomic="true"/);
    assert.equal((html.match(/<i /g) ?? []).length, 12);
    const zero = await renderToString(createSSRApp(component, { wave: { ...state().wave, aggregate: { points: 0, participants: 0 } }, messages }));
    assert.match(zero, /0 rays · 0 contributors/);
    const css = read('../../resources/css/kindness-wave.css');
    const reduced = css.split('@media (prefers-reduced-motion:reduce)')[1];
    for (const animated of ['.kindness-celebration', '.kindness-celebration-star', '.kindness-celebration-rays i']) assert.ok(reduced.includes(animated));
    assert.match(reduced, /animation:none/);
});
