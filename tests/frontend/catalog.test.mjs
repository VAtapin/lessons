import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const source = ts.transpileModule(fs.readFileSync(new URL('../../resources/js/catalog/filters.ts', import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { readFilters, filterQuery, catalogLink, catalogPageQuery, taxonomyOptions } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const term = (kind, key, label = key, active = true) => ({ id: `${kind}-${key}`, kind, key, label, active, revision: 1 });
test('shared catalog filter links preserve the backend vocabulary', () => {
    assert.equal(catalogLink('de', 'topic', 'mercy'), '/de/catalog?topic=mercy');
    assert.equal(catalogLink('ru', 'age', '15+'), '/ru/catalog?age=15%2B');
    assert.equal(catalogLink('ru'), '/ru/catalog');
});
test('filter state restores all supported filters and Unicode search from URL', () => {
    const filters = readFilters('?age=11-14&audience=sunday-school&topic=parables&format=interactive&duration=standard&q=%D0%B1%D0%BB%D0%B8%D0%B6%D0%BD%D0%B8%D0%B9');
    assert.deepEqual(filters, { age: '11-14', audience: 'sunday-school', topic: 'parables', format: 'interactive', duration: 'standard', q: 'ближний' });
    assert.deepEqual(readFilters('?' + filterQuery(filters)), filters);
});
test('invalid facets and unrelated URL parameters are not forwarded to catalog API', () => {
    const filters = readFilters('?age=any&topic=missing&format=private&duration=forever&admin=true');
    assert.equal(filterQuery(filters), '');
    assert.equal(readFilters('?q=' + 'a'.repeat(200)).q.length, 120);
});
test('empty filters and whitespace search produce a clean catalog URL', () => {
    assert.equal(filterQuery(readFilters('?q=%20%20')), '');
    const filters = readFilters('?q=%20Wer%20ist%20mein%20N%C3%A4chster%3F%20');
    assert.equal(new URLSearchParams(filterQuery(filters)).get('q'), 'Wer ist mein Nächster?');
});

test('active server taxonomy restores custom facets with search and pagination', () => {
    const terms = [term('topic', 'creation', 'Schöpfung'), term('age', '3-4'), term('audience', 'youth'), term('format', 'workshop')];
    const search = '?topic=creation&age=3-4&audience=youth&format=workshop&duration=short&q=N%C3%A4chster&page=3&admin=true';
    const filters = readFilters(search, terms);
    assert.deepEqual(filters, { q: 'Nächster', age: '3-4', audience: 'youth', topic: 'creation', format: 'workshop', duration: 'short' });
    assert.deepEqual(readFilters(filterQuery(filters), terms), filters);
    const query = catalogPageQuery(search, terms);
    assert.equal(query.get('topic'), 'creation');
    assert.equal(query.get('q'), 'Nächster');
    assert.equal(query.get('page'), '3');
    assert.equal(query.has('admin'), false);
});

test('server taxonomy excludes archived and unknown keys including former seed keys', () => {
    const terms = [term('topic', 'mercy', 'Barmherzigkeit', false), term('topic', 'creation', 'Schöpfung'), term('topic', '../unsafe')];
    assert.equal(readFilters('?topic=mercy&age=5-7', terms).topic, '');
    assert.equal(readFilters('?topic=mercy&age=5-7', terms).age, '');
    assert.equal(readFilters('?topic=missing', terms).topic, '');
    assert.equal(readFilters('?topic=../unsafe', terms).topic, '');
    assert.equal(readFilters('?topic=creation', []).topic, '');
    assert.equal(catalogPageQuery('?page=99999999999999999', terms).has('page'), false);
    assert.equal(catalogPageQuery('?page=0', terms).has('page'), false);
});

test('fallback keeps supported dictionary facets and never invents a custom key', () => {
    assert.equal(readFilters('?topic=creation', null).topic, '');
    assert.equal(readFilters('?topic=mercy', null).topic, 'mercy');
    assert.equal(taxonomyOptions(null, { topic_mercy: 'Barmherzigkeit' }).topic.find(item => item.key === 'mercy').label, 'Barmherzigkeit');
});

test('server labels take priority and seed display positions stay fixed with custom terms appended', () => {
    const terms = [term('age', '3-4'), term('age', 'adults'), term('age', '11-14'), term('age', '5-7'), term('age', '15+'), term('age', '8-10'), term('topic', 'creation', 'Schöpfung'), term('topic', 'mercy', 'Neue Bezeichnung'), term('audience', 'youth', 'Jugend')];
    const options = taxonomyOptions(terms, { topic_mercy: 'Old label' });
    assert.deepEqual(options.age.map(item => item.key), ['5-7', '8-10', '11-14', '15+', 'adults', '3-4']);
    assert.deepEqual(options.topic, [{ key: 'mercy', label: 'Neue Bezeichnung' }, { key: 'creation', label: 'Schöpfung' }]);
    assert.equal(options.audience[0].key, 'youth');
});
