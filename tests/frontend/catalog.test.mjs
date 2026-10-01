import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const source = ts.transpileModule(fs.readFileSync(new URL('../../resources/js/catalog/filters.ts', import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { readFilters, filterQuery, catalogLink } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
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
