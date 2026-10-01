import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
import { reactive } from 'vue';
const compile = file => ts.transpileModule(fs.readFileSync(new URL('../../resources/js/studio/' + file, import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const url = source => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const api = url('export class ApiError extends Error { constructor(code,status){super(code);this.code=code;this.status=status;} }');
const { ApiError } = await import(api);
const { publicationMetadata, submissionPayload, reviewPayload, adminError, plainCopy } = await import(url(compile('admin.ts').replace("'./api'",JSON.stringify(api))));
test('publication metadata uses exact released locales, title and summed stage duration', () => {
    const document = { locales: ['de'], content: { de: { title: 'Original' } }, stages: [{ config: { durationSeconds: 120 } }, { config: { durationSeconds: 61 } }] };
    const metadata = publicationMetadata(document);
    assert.deepEqual(Object.keys(metadata.translations), ['de']);
    assert.equal(metadata.translations.de.title, 'Original');
    assert.equal(metadata.durationMinutes, 4);
    assert.deepEqual(metadata.topic, []);
});
test('submission pins source version and revision and freezes independent plain metadata', () => {
    const metadata = reactive({ translations: { ru: { title: 'Pinned', description: 'Text' } }, age: ['8-10'], topic: [], audience: [], format: [], durationMinutes: 45 });
    const body = submissionPayload('lesson','released-version',7,'  source-lesson  ',metadata);
    metadata.translations.ru.title = 'Later draft'; metadata.age.push('11-14');
    assert.equal(body.versionId, 'released-version'); assert.equal(body.expectedLessonRevision,7);
    assert.equal(body.slug, 'source-lesson'); assert.equal(body.metadata.translations.ru.title, 'Pinned');
    assert.deepEqual(body.metadata.age,['8-10']);
    assert.deepEqual(plainCopy(reactive({nested:{value:1}})),{nested:{value:1}});
});
test('return requires reason and revision; approve never leaks a stale return reason', () => {
    assert.throws(() => reviewPayload(3,'return','   '),/reason_required/);
    assert.deepEqual(reviewPayload(3,'return','  Please fix  '),{expectedRevision:3,decision:'return',reason:'Please fix'});
    assert.deepEqual(reviewPayload(3,'approve','Private draft'),{expectedRevision:3,decision:'approve'});
});
test('access denial, validation and conflict have honest dedicated messages', () => {
    const messages={admin_login_required:'login',admin_verification_required:'verify',admin_access_denied:'denied',admin_conflict:'conflict',admin_invalid:'invalid',admin_error:'request'};
    assert.equal(adminError(new ApiError('authentication_required',401),messages),'login');
    assert.equal(adminError(new ApiError('verification_required',403),messages),'verify');
    assert.equal(adminError(new ApiError('admin_required',403),messages),'denied');
    assert.equal(adminError(new ApiError('revision_conflict',409),messages),'conflict');
    assert.equal(adminError(new ApiError('invalid_document',422),messages),'invalid');
});
test('RU and DE admin messages have matching complete keys for all new components', () => {
    const dictionaries=['ru','de'].map(locale=>new Set([...fs.readFileSync(new URL(`../../lang/${locale}/admin.php`,import.meta.url),'utf8').matchAll(/'([^']+)'\s*=>/g)].map(match=>match[1])));
    assert.deepEqual([...dictionaries[0]].sort(),[...dictionaries[1]].sort());
    for(const file of ['CatalogSubmissions.vue','AdminPage.vue','CommonLibrary.vue','admin.ts']) {
        const source=fs.readFileSync(new URL('../../resources/js/studio/'+file,import.meta.url),'utf8');
        for(const match of source.matchAll(/messages\.(admin_[A-Za-z_]+)/g)) for(const dictionary of dictionaries) assert.ok(dictionary.has(match[1]),`${file}: ${match[1]}`);
    }
});
test('author publication reads strict released version rather than working partial translations', () => {
    const source=fs.readFileSync(new URL('../../resources/js/studio/CatalogSubmissions.vue',import.meta.url),'utf8');
    assert.match(source,/version\.status === 'released'/);
    assert.match(source,/document: result\.version\.document/);
    assert.doesNotMatch(source,/document: props\.lesson\.document/);
    assert.doesNotMatch(source,/editorDocument/);
});
