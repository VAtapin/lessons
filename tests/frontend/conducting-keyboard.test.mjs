import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const source = fs.readFileSync(new URL('../../resources/js/studio/conducting-keyboard.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { keyboardStageIndex } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);

test('navigation uses actual stage bounds and Home/End without wrapping', () => {
    assert.equal(keyboardStageIndex('ArrowRight', 5, 13, false), 6);
    assert.equal(keyboardStageIndex('ArrowLeft', 0, 13, false), undefined);
    assert.equal(keyboardStageIndex('ArrowDown', 12, 13, false), undefined);
    assert.equal(keyboardStageIndex('Home', 5, 13, false), 0);
    assert.equal(keyboardStageIndex('End', 5, 13, false), 12);
    assert.equal(keyboardStageIndex('End', 12, 13, false), undefined);
});

test('blocked dialogs, input editing and revoked authority issue no navigation', () => {
    for (const key of ['ArrowRight', 'ArrowLeft', 'ArrowUp', 'ArrowDown', 'Home', 'End']) assert.equal(keyboardStageIndex(key, 5, 13, true), undefined);
    assert.equal(keyboardStageIndex('Enter', 5, 13, false), undefined);
    assert.equal(keyboardStageIndex('Home', 0, 0, false), undefined);
});
