import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const component=fs.readFileSync(new URL('../../resources/js/studio/OperationsPanel.vue',import.meta.url),'utf8');
const script=component.match(/<script lang="ts">([\s\S]*?)<\/script>/)[1];
const source=ts.transpileModule(script,{compilerOptions:{module:ts.ModuleKind.ESNext,target:ts.ScriptTarget.ES2022}}).outputText;
const {operationCounters,operationDate,operationScheduleTime,operationMessageKey}=await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
test('operations UI has complete matching RU and DE messages',()=>{
    const dictionaries=['ru','de'].map(locale=>new Set([...fs.readFileSync(new URL(`../../lang/${locale}/admin.php`,import.meta.url),'utf8').matchAll(/'([^']+)'\s*=>/g)].map(match=>match[1])));
    assert.deepEqual([...dictionaries[0]].sort(),[...dictionaries[1]].sort());
    for(const match of component.matchAll(/messages\.(admin_[A-Za-z_]+)/g)) for(const dictionary of dictionaries) assert.ok(dictionary.has(match[1]),match[1]);
});
test('backup and retention expose only their own non-negative safe integer counters',()=>{
    const mixed={databaseBytes:100,mediaBytes:0,mediaVersions:3,sessionsDeleted:8,exception:'private error',directory:'/private/path',password:42};
    assert.deepEqual(operationCounters('backup',mixed),[{key:'databaseBytes',value:100},{key:'mediaBytes',value:0},{key:'mediaVersions',value:3}]);
    assert.deepEqual(operationCounters('retention',mixed),[{key:'sessionsDeleted',value:8}]);
    assert.deepEqual(operationCounters('backup',{databaseBytes:-1,mediaBytes:'42',mediaVersions:1.5}),[]);
    assert.deepEqual(operationCounters('backup',{databaseBytes:Infinity,mediaBytes:Number.MAX_SAFE_INTEGER+1}),[]);
});
test('unknown operations and absent counts have generic safe presentation',()=>{
    for(const operation of ['unknown','__proto__','constructor','toString']){
        assert.deepEqual(operationCounters(operation,{databaseBytes:100}),[]);
        assert.equal(operationMessageKey(operation),'admin_operation_unknown');
    }
    assert.deepEqual(operationCounters('backup',null),[]);
    assert.equal(operationMessageKey('backup'),'admin_operation_backup');
    assert.equal(operationMessageKey('retention'),'admin_operation_retention');
});
test('record times are localized in UTC and invalid times use an honest fallback',()=>{
    const value='2026-10-01T03:30:00+02:00';
    for(const locale of ['ru','de']){
        const formatted=operationDate(value,locale,'unknown');
        assert.match(formatted,/01:30:00 UTC$/);
        assert.equal(formatted,new Intl.DateTimeFormat(locale,{dateStyle:'medium',timeStyle:'medium',timeZone:'UTC'}).format(new Date(value))+' UTC');
        assert.equal(operationDate('invalid',locale,'unknown'),'unknown');
    }
});
test('schedule clock accepts only actual HH:MM and labels it UTC',()=>{
    assert.equal(operationScheduleTime('02:30','unknown'),'02:30 UTC');
    assert.equal(operationScheduleTime('23:59','unknown'),'23:59 UTC');
    for(const value of ['24:00','02:60','2:30','','private/path']) assert.equal(operationScheduleTime(value,'unknown'),'unknown');
});
