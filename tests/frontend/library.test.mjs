import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const compile = file => ts.transpileModule(fs.readFileSync(new URL(file, import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const moduleUrl = source => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const libraryUrl = moduleUrl(compile('../../resources/js/studio/library.ts'));
const library = await import(libraryUrl);
test('new metadata leaves attribution unspecified without claiming a license', () => {
    assert.deepEqual(library.emptyMetadata(), { title: '', tags: [], author: '', source: '', rightsBasis: 'unspecified', usageRights: '' });
});
const document = await import(moduleUrl(compile('../../resources/js/studio/document.ts').replace("'./library'", JSON.stringify(libraryUrl))));
test('template locale compatibility needs every document translation and does not mix interface locale', () => {
    assert.equal(library.compatibleLocales(['ru', 'de'], ['de']), true);
    assert.equal(library.compatibleLocales(['ru'], ['ru', 'de']), false);
    assert.equal(library.compatibleLocales(['ru'], []), false);
});
test('new image selection excludes archive while preserving only the exact existing pinned pair', () => {
    const media = [{ assetId: 'a', versionId: 'old', archived: true }, { assetId: 'a', versionId: 'new', archived: false }, { assetId: 'b', versionId: 'other', archived: true }];
    assert.deepEqual(library.selectableMedia(media).map(item => item.versionId), ['new']);
    assert.deepEqual(library.selectableMedia(media, { assetId: 'a', versionId: 'old' }).map(item => item.versionId), ['old', 'new']);
    assert.deepEqual(library.selectableMedia(media, { assetId: 'b', versionId: 'old' }).map(item => item.versionId), ['new']);
});
test('preview resolves exact authorized media pairs and never adds resources to authored JSON', () => {
    const block = { id: 'image-one', type: 'core.image', schemaVersion: 1, content: { ru: { alt: 'Описание' } }, config: { fit: 'contain' }, media: { image: { assetId: 'a', versionId: 'old' } }, solution: null, origin: null };
    const stage = { id: 'stage', content: { ru: { title: 'Этап', notes: 'secret' } }, config: {}, blocks: [block] };
    const original = JSON.stringify(stage);
    const media = [{ assetId: 'a', versionId: 'old', url: '/media/owned/a/old' }, { assetId: 'a', versionId: 'new', url: '/media/owned/a/new' }];
    const projected = document.projectStage(stage, 'ru', media);
    assert.deepEqual(projected.blocks[0].resources, { image: '/media/owned/a/old' });
    assert.equal(projected.content.notes, undefined);
    assert.equal(JSON.stringify(stage), original);
    assert.deepEqual(library.imageResources(media, 'foreign', 'old'), {});
});
test('metadata copy and normalized unique tags remain independent of server detail', () => {
    const metadata = { ...library.emptyMetadata(), title: 'One', tags: ['a'], revision: 5, versions: [] };
    const copy = library.metadataOf(metadata);
    copy.tags.push('b');
    assert.deepEqual(metadata.tags, ['a']);
    assert.equal('revision' in copy, false);
    assert.deepEqual(library.parseTags(' помощь, , помощь, пара '), ['помощь', 'пара']);
});
test('image file preflight uses binary configured bound and rejects SVG or empty files', () => {
    const limit = 20 * 1024 * 1024;
    assert.equal(library.fileProblem({ type: 'image/png', size: limit }, limit), undefined);
    assert.equal(library.fileProblem({ type: 'image/webp', size: limit + 1 }, limit), 'file_too_large');
    assert.equal(library.fileProblem({ type: 'image/jpeg', size: 0 }, limit), 'file_too_large');
    assert.equal(library.fileProblem({ type: 'image/svg+xml', size: 100 }, limit), 'invalid_media');
});
