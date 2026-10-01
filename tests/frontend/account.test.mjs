import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
const compile = file => ts.transpileModule(fs.readFileSync(new URL('../../resources/js/studio/' + file, import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const url = source => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const helpersUrl = url(compile('account-helpers.ts'));
const helpers = await import(helpersUrl);
const identityModule = name => import(url(compile('identity.ts').replace("'vue'", JSON.stringify(import.meta.resolve('vue'))).replace("'./account-helpers'", JSON.stringify(helpersUrl)) + '\n//' + name));
const account = (id = null) => ({ user: id === null ? null : { id, name: 'Name', verified: false }, quota: { usedBytes: 0, limitBytes: 100 }, guestClaimAvailable: false });

test('identity uses account ID rather than name/email/verification/quota and detects login/logout', () => {
    assert.equal(helpers.identityDiffers(undefined, account(1)), false);
    assert.equal(helpers.identityDiffers('guest', account(1)), true);
    assert.equal(helpers.identityDiffers('user:1', account()), true);
    assert.equal(helpers.identityDiffers('user:1', { ...account(1), user: { id: 1, name: 'New', verified: true } }), false);
    assert.equal(helpers.identityDiffers('user:1', account(2)), true);
});

test('pre-write identity refresh prevents old draft writes under a newly logged in account', async () => {
    const identity = await identityModule('pre-write');
    const original = globalThis.fetch;
    let requests = 0;
    globalThis.fetch = async path => { requests++; assert.equal(path, '/api/account'); return { ok: true, json: async () => account(2) }; };
    try {
        identity.acceptAccount(account(1));
        await assert.rejects(identity.guardWorkspace('/api/studio/lessons/one', 'PUT'), /identity_changed/);
        assert.equal(identity.identityBlocked.value, true);
        assert.equal(identity.accountState.value.user.id, 1);
        await assert.rejects(identity.guardWorkspace('/api/studio/media', 'POST'), /identity_changed/);
        assert.equal(requests, 1);
    } finally { globalThis.fetch = original; }
});

test('auth notifications block workspace immediately while independent participant requests remain available', async () => {
    const identity = await identityModule('broadcast');
    const previousWindow = globalThis.window, previousChannel = globalThis.BroadcastChannel;
    let channel;
    globalThis.window = { addEventListener() {}, removeEventListener() {} };
    globalThis.BroadcastChannel = class { constructor() { channel = this; } close() {} };
    try {
        identity.acceptAccount(account());
        const stop = identity.startIdentityWatch(async () => {});
        channel.onmessage({ data: { type: 'unknown' } });
        assert.equal(identity.identityBlocked.value, false);
        channel.onmessage({ data: { type: 'identity-changed' } });
        assert.equal(identity.identityBlocked.value, true);
        await assert.rejects(identity.guardWorkspace('/api/studio/sessions/one/commands', 'POST'), /identity_changed/);
        await identity.guardWorkspace('/api/participation/one/answers', 'POST');
        stop();
    } finally { globalThis.window = previousWindow; globalThis.BroadcastChannel = previousChannel; }
});

test('revoked authentication blocks pending workspace mutations until explicit page reload', async () => {
    const identity = await identityModule('revoked');
    const original = globalThis.fetch;
    globalThis.fetch = async () => ({ ok: false, status: 401 });
    try {
        identity.acceptAccount(account(1));
        await assert.rejects(identity.guardWorkspace('/api/studio/lessons/one', 'PUT'), /identity_changed/);
        assert.equal(identity.identityBlocked.value, true);
    } finally { globalThis.fetch = original; }
});

test('password preflight counts Unicode characters and bcrypt UTF-8 bytes without trimming', () => {
    assert.equal(helpers.passwordValid('a'.repeat(12)), true);
    assert.equal(helpers.passwordValid('a'.repeat(11)), false);
    assert.equal(helpers.passwordValid('😀'.repeat(18)), true);
    assert.equal(helpers.passwordValid('😀'.repeat(19)), false);
    assert.equal(helpers.passwordValid('a'.repeat(12) + '\0'), false);
    assert.equal(helpers.passwordValid(' '.repeat(12)), true);
});

test('guest transfer requires explicit available pending verified claim within server quota', () => {
    const claim = { available: true, status: 'pending' };
    assert.equal(helpers.mayClaim(claim, true, 100, 100), true);
    assert.equal(helpers.mayClaim(claim, false, 100, 100), false);
    assert.equal(helpers.mayClaim(claim, true, 101, 100), false);
    assert.equal(helpers.mayClaim({ ...claim, status: 'claimed' }, true, 0, 100), false);
    assert.equal(helpers.mayClaim({ ...claim, available: false }, true, 0, 100), false);
});

test('continuation preserves the existing session and finished history has no continuation link', () => {
    for (const status of ['prepared', 'running', 'paused']) assert.equal(helpers.historyContinuePath({ id: 'same-session', status }, 'de'), '/de/teach/same-session');
    assert.equal(helpers.historyContinuePath({ id: 'same-session', status: 'finished' }, 'ru'), undefined);
});

test('registration recovers a committed login after mail failure without asking for repeat registration', () => {
    const user = { id: 3, uiLocale: 'de' };
    assert.equal(helpers.registrationRecoveryPath('register', 'mail_unavailable', user), '/de/verify-email?notice=mail-unavailable');
    assert.equal(helpers.registrationRecoveryPath('register', 'mail_unavailable', null), undefined);
    assert.equal(helpers.registrationRecoveryPath('register', 'registration_unavailable', user), undefined);
    assert.equal(helpers.registrationRecoveryPath('login', 'mail_unavailable', user), undefined);
});

test('history mode and profile save use dedicated labels independent of runtime and material status', () => {
    const history = fs.readFileSync(new URL('../../resources/js/studio/HistoryPage.vue', import.meta.url), 'utf8');
    const profile = fs.readFileSync(new URL('../../resources/js/studio/AccountPage.vue', import.meta.url), 'utf8');
    assert.match(history, /messages\.history_lesson_mode/);
    assert.doesNotMatch(history, /live_session|status_running/);
    assert.match(profile, /notice\.value = props\.messages\.profile_saved/);
    for (const [locale, mode, saved] of [['ru', 'Занятие', 'Профиль сохранён.'], ['de', 'Unterricht', 'Profil gespeichert.']]) {
        const dictionary = fs.readFileSync(new URL(`../../lang/${locale}/studio.php`, import.meta.url), 'utf8');
        assert.ok(dictionary.includes(`'history_lesson_mode' => '${mode}'`));
        assert.ok(dictionary.includes(`'profile_saved' => '${saved}'`));
    }
});
