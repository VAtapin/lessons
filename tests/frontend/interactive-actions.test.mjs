import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import ts from 'typescript';
import { parse, compileScript } from '@vue/compiler-sfc';
import * as Vue from 'vue';
import QRCode from 'qrcode';

const compile = source => ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;
const evaluate = (source, dependencies = {}) => {
    const exports = {};
    vm.runInNewContext(compile(source), { exports, setTimeout, clearTimeout, require: name => {
        assert.ok(name in dependencies, `Unexpected component dependency: ${name}`);
        return dependencies[name];
    } });
    return exports;
};
const read = file => fs.readFileSync(new URL('../../resources/js/studio/' + file, import.meta.url), 'utf8');
const { descriptor } = parse(read('InteractiveBlock.vue'));
const component = evaluate(compileScript(descriptor, { id: 'interactive-actions-test', inlineTemplate: true }).content, {
    vue: Vue, './interactive': evaluate(read('interactive.ts')), './sequence-input': evaluate(read('sequence-input.ts')),
    './ClassVoice.vue': { __esModule: true, default: { render: () => null } },
}).default;
const presentationComponent = evaluate(compileScript(parse(read('PresentationBlock.vue')).descriptor, { id: 'presentation-actions-test', inlineTemplate: true }).content, { vue: Vue }).default;
const teacherToolsComponent = evaluate(compileScript(parse(read('TeacherBlockTools.vue')).descriptor, { id: 'teacher-tools-actions-test', inlineTemplate: true }).content, { vue: Vue, './interactive': evaluate(read('interactive.ts')), './BlockRenderer.vue': { __esModule: true, default: { render: () => null } } }).default;
const qrRequests = [];
const joinComponent = evaluate(compileScript(parse(read('JoinProjection.vue')).descriptor, { id: 'join-projection-test', inlineTemplate: true }).content, { vue: Vue, qrcode: { __esModule: true, default: { toDataURL: (...args) => { const pending = QRCode.toDataURL(...args); qrRequests.push(pending); return pending; } } } }).default;
const stub = { __esModule: true, default: { render: () => null } };
const statusComponent = evaluate(compileScript(parse(read('RuntimeStatus.vue')).descriptor, { id: 'runtime-status-actions-test', inlineTemplate: true }).content, {
    vue: Vue, './runtime': evaluate(read('runtime.ts')), './kindness-wave': evaluate(read('kindness-wave.ts')),
    './KindnessWave.vue': stub, './RuntimeTimer.vue': stub, './JoinProjection.vue': { __esModule: true, default: joinComponent },
}).default;
const content = node => node.kind === '#comment' ? '' : node.text + node.children.map(content).join('');
const descendants = node => [node, ...node.children.flatMap(descendants)];
const messages = { undo: 'Undo', sequence_reset: 'Reset', sequence_empty: 'Empty', your_answer: 'Answer', add_answer: 'Add', no_role: 'Release' };

test('reveal table stays hidden until the teacher opens it and renders accessible headers and rows', async t => {
    const block = { id: 'table', type: 'core.presentation', content: { text: 'At the report', label: 'Show table', hideLabel: 'Hide table', modes: [], table: { headers: ['Servant', 'Received', 'Returned'], rows: [['First', '5', '10'], ['Second', '2', '4'], ['Third', '1', '1']] } }, config: { kind: 'reveal' }, runtime: { presentation: { visible: false } } };
    const child = fixture(t, block, { conducting: true, interactive: true }, presentationComponent);
    assert.equal(child.nodes('table').length, 0);
    assert.equal(child.nodes('button').length, 0);
    child.props.block.runtime.presentation.visible = true;
    await Vue.nextTick();
    assert.equal(child.nodes('table').length, 1);
    assert.equal(child.nodes('caption').length, 1);
    assert.equal(child.nodes('th').filter(node => node.props.scope === 'col').length, 3);
    assert.equal(child.nodes('th').filter(node => node.props.scope === 'row').length, 3);
    assert.match(content(child.root), /First510Second24Third11/);
    child.props.block.runtime.presentation.visible = false;
    await Vue.nextTick();
    assert.equal(child.nodes('table').length, 0);
    const teacher = fixture(t, structuredClone(block), { conducting: true, presenter: true }, presentationComponent);
    teacher.click('Show table');
    assert.deepEqual(teacher.events, [['command', 'presentation.toggle', { blockId: 'table' }]]);
});

test('teacher-controlled picture count renders five then four images for pupils without answer buttons', async t => {
    const block = { id: 'herd', type: 'core.presentation', content: { title: 'Our herd', text: 'Sheep', modes: [{ modeId: 'herd', count: 5, title: 'Our herd', text: 'How many?', label: 'Show five' }, { modeId: 'missing', count: 4, title: 'One missing', text: 'Who is missing?', label: 'Take one away' }] }, config: { kind: 'picture-count' }, resources: { image: '/sheep.png' }, runtime: { presentation: {} } };
    const child = fixture(t, block, { interactive: true, answer: { value: { modeId: 'herd' } } }, presentationComponent);
    assert.equal(child.nodes('img').length, 5);
    assert.equal(child.nodes('button').length, 0);
    child.props.block.runtime.presentation.modeId = 'missing';
    await Vue.nextTick();
    assert.equal(child.nodes('img').length, 4);
    assert.match(content(child.root), /One missing/);
    assert.deepEqual(child.events, []);
    const teacher = fixture(t, structuredClone(block), { presenter: true, interactive: false }, presentationComponent);
    teacher.click('Take one away');
    assert.deepEqual(teacher.events, [['command', 'presentation.mode', { blockId: 'herd', modeId: 'missing' }]]);
});

function fixture(t, block, extra = {}, renderedComponent = component) {
    const events = [];
    const node = (kind, text = '') => ({ kind, text, props: {}, children: [], parent: null, value: '', listeners: {},
        addEventListener(name, callback) { this.listeners[name] = callback; },
    });
    const remove = child => {
        if (child.parent) child.parent.children.splice(child.parent.children.indexOf(child), 1);
        child.parent = null;
    };
    const renderer = Vue.createRenderer({
        createElement: kind => node(kind), createText: text => node('#text', text), createComment: text => node('#comment', text),
        setText: (target, text) => { target.text = text; }, setElementText: (target, text) => { target.text = text; target.children = []; },
        patchProp: (target, key, previous, next) => { target.props[key] = next; if (key === 'type') target.type = next; },
        insert: (child, parent, anchor = null) => {
            remove(child);
            const index = anchor ? parent.children.indexOf(anchor) : -1;
            parent.children.splice(index < 0 ? parent.children.length : index, 0, child);
            child.parent = parent;
        }, remove, parentNode: target => target.parent, nextSibling: target => target.parent?.children[target.parent.children.indexOf(target) + 1] ?? null,
    });
    const props = Vue.reactive({ block, messages, interactive: true, conducting: true, ...extra });
    const root = node('root');
    const handlers = {
        onAnswer: (...args) => events.push(['answer', ...structuredClone(args)]),
        onCommand: (...args) => events.push(['command', ...structuredClone(args)]),
        onDismiss: () => events.push(['dismiss']), onClearMessage: () => events.push(['clearMessage']), onHideJoin: () => events.push(['hideJoin']),
    };
    const declaredHandlers = Object.fromEntries(Object.entries(handlers).filter(([name]) => renderedComponent.emits.includes(name[2].toLowerCase() + name.slice(3))));
    const app = renderer.createApp({ setup: () => () => Vue.h(renderedComponent, {
        ...Object.fromEntries(Object.entries(props).filter(([name]) => name in renderedComponent.props)), ...declaredHandlers,
    }) });
    app.mount(root);
    t.after(() => app.unmount());
    const nodes = kind => descendants(root).filter(item => item.kind === kind);
    const button = text => {
        const matches = nodes('button').filter(item => content(item) === text);
        assert.equal(matches.length, 1, `Expected one button: ${text}`);
        return matches[0];
    };
    return { props, events, nodes, root, button, click: text => button(text).props.onClick({}),
        type: text => { const input = nodes('input')[0]; input.value = text; input.listeners.input({ target: input }); nodes('form')[0].props.onInput({}); },
        enter: () => { let prevented = false; nodes('form')[0].props.onSubmit({ preventDefault: () => { prevented = true; } }); assert.equal(prevented, true); },
    };
}

test('native single-line form submission emits the exact valid answer and retains it pending acknowledgement', async t => {
    const page = fixture(t, { id: 'free', type: 'core.free-response', content: { question: 'Respond', label: 'My decision', placeholder: 'I can help', submitLabel: 'Add to road' }, config: { maxLength: 8, allowRepeat: true }, runtime: { status: 'open' } });
    assert.equal(page.nodes('textarea').length, 0);
    assert.equal(page.nodes('input')[0].props.type, 'text');
    assert.equal(page.nodes('input')[0].props.maxlength, 8);
    assert.equal(page.nodes('input')[0].props.placeholder, 'I can help');
    assert.match(content(page.root), /My decision/);
    assert.ok(page.button('Add to road'));
    page.type('😀Help');
    await Vue.nextTick();
    page.enter();
    assert.deepEqual(page.events, [['answer', 'free', { text: '😀Help' }]]);
    assert.equal(page.nodes('input')[0].value, '😀Help');
    assert.equal(page.nodes('small').some(item => content(item) === '5 / 8'), true);
    page.props.disabled = true;
    await Vue.nextTick();
    page.enter();
    assert.equal(page.events.length, 1, 'Pending or disabled response must not be resent');
});

test('Enter keeps whitespace and over-limit answers unsent while Unicode counts follow existing validation', async t => {
    const page = fixture(t, { id: 'free', type: 'core.free-response', content: {}, config: { maxLength: 2 }, runtime: { status: 'open' } });
    for (const invalid of ['  ', 'abc', '😀😀😀']) {
        page.type(invalid);
        await Vue.nextTick();
        page.enter();
        assert.deepEqual(page.events, []);
        assert.equal(page.button(messages.add_answer).props.disabled, true);
        assert.equal(page.nodes('input')[0].value, invalid);
    }
    page.type('😀😀');
    await Vue.nextTick();
    page.enter();
    assert.deepEqual(page.events, [['answer', 'free', { text: '😀😀' }]]);
});

test('role reset removes hidden choices and blocks a stale click handler while preserving release', async t => {
    const page = fixture(t, { id: 'roles', type: 'core.roles', content: { roles: [{ roleId: 'a', text: 'Traveler' }, { roleId: 'b', text: 'Samaritan' }] }, config: {}, runtime: { mode: 'lesson', status: 'open', presentation: { revealedRoleIds: ['a'] } } });
    assert.equal(page.nodes('button').some(item => content(item).includes('Samaritan')), false);
    const staleClick = page.button('Traveler').props.onClick;
    staleClick({});
    assert.deepEqual(page.events, [['answer', 'roles', { roleId: 'a' }]]);
    page.props.block.runtime.presentation.revealedRoleIds = [];
    await Vue.nextTick();
    staleClick({});
    assert.equal(page.events.length, 1);
    assert.equal(page.nodes('button').some(item => content(item).includes('Traveler')), false);
    page.click(messages.no_role);
    assert.deepEqual(page.events[1], ['answer', 'roles', { roleId: null }]);
});

const sequence = () => ({ id: 'sequence', type: 'core.sequence', content: { items: [{ itemId: 'a', text: 'Saw' }, { itemId: 'b', text: 'Approached' }, { itemId: 'c', text: 'Helped' }] }, config: { allowRepeat: true }, runtime: { status: 'open', presentation: { itemIds: [] } } });

test('learner sequence keeps saved order, undo and reset; stale polling never overwrites a rebuilt road', async t => {
    const saved = { value: { itemIds: ['c', 'a', 'b'] }, status: 'submitted', grade: null };
    const page = fixture(t, sequence(), { answer: saved });
    const road = () => content(page.nodes('ol')[0]);
    assert.ok(road().indexOf('Helped') < road().indexOf('Saw'));
    page.click(messages.undo);
    await Vue.nextTick();
    assert.equal(page.button('Approached').props.disabled, false);
    assert.equal(page.button('Helped').props.disabled, true);
    assert.deepEqual(page.events, []);
    page.click(messages.sequence_reset);
    await Vue.nextTick();
    assert.doesNotMatch(road(), /Saw|Approached|Helped/);
    const pool = page.nodes('button').filter(item => item.props.class?.includes('sequence-tile')).map(content);
    assert.notDeepEqual(pool, ['Saw', 'Approached', 'Helped']);
    page.click('Helped');
    page.props.answer = structuredClone(saved);
    await Vue.nextTick();
    assert.match(road(), /Helped/);
    assert.doesNotMatch(road(), /Saw|Approached/);
    page.click('Saw'); page.click('Approached');
    assert.deepEqual(page.events, [['answer', 'sequence', { itemIds: ['c', 'a', 'b'] }]]);
});

test('presenter selects immediately through shared commands and displays only acknowledged shared progress', async t => {
    const page = fixture(t, sequence(), { presenter: true, interactive: false });
    page.click('Helped');
    assert.deepEqual(page.events, [['command', 'sequence.select', { blockId: 'sequence', itemId: 'c' }]]);
    assert.doesNotMatch(content(page.nodes('ol')[0]), /Helped/);
    page.props.block.runtime.presentation = { itemIds: ['a'], feedback: 'incorrect' };
    await Vue.nextTick();
    assert.match(content(page.nodes('ol')[0]), /Saw/);
    assert.doesNotMatch(content(page.nodes('ol')[0]), /Helped/);
    page.click(messages.sequence_reset);
    assert.deepEqual(page.events[1], ['command', 'sequence.reset', { blockId: 'sequence' }]);
    assert.equal(page.nodes('button').some(item => content(item) === messages.undo), false);
});

test('authored road captions and stable item symbols follow the chosen order and reset without exposing a solution', async t => {
    const block = sequence();
    Object.assign(block.content, { label: 'Rebuild the path', emptyText: '…', feedback: 'The first step begins with attention' });
    block.content.items.forEach((item, index) => { item.icon = ['◉', '●', '+'][index]; });
    const page = fixture(t, block);
    assert.equal(page.button('Saw').props['data-icon'], '◉');
    assert.equal(page.button('Helped').props['data-icon'], '+');
    assert.match(content(page.root), /The first step begins with attention/);
    assert.match(content(page.nodes('ol')[0]), /…/);
    page.click('Helped');
    await Vue.nextTick();
    const firstSlot = descendants(page.nodes('ol')[0]).find(node => node.kind === 'li');
    assert.match(content(firstSlot), /\+Helped/);
    assert.doesNotMatch(content(firstSlot), /◉|Saw/);
    assert.doesNotMatch(content(page.root), /The first step begins with attention/);
    page.click('Rebuild the path');
    await Vue.nextTick();
    assert.match(content(page.root), /The first step begins with attention/);
    assert.doesNotMatch(content(page.nodes('ol')[0]), /Helped|Saw|Approached/);
    assert.deepEqual(page.events, []);
});

const discussion = () => ({ id: 'newcomer-discussion', type: 'core.presentation', config: { kind: 'discussion' },
    content: { text: 'A new pupil sits alone', modes: [
        { modeId: 'pass', label: 'How to pass by?', title: 'An excuse for inaction', text: 'What excuse could you invent?' },
        { modeId: 'help', label: 'How to help?', title: 'Concrete help', text: 'What exact words could you say?' },
    ] }, runtime: { status: 'open', presentation: { modeId: 'pass' } },
});

test('student school direction uses its own saved choice, emits an answer and restores it after remount', async t => {
    const page = fixture(t, discussion(), { answer: { value: { modeId: 'help' }, grade: null, status: 'submitted' } }, presentationComponent);
    assert.equal(page.button('How to help?').props['aria-pressed'], true);
    assert.match(content(page.root), /Concrete help/);
    assert.match(content(page.root), /What exact words could you say\?/);
    assert.doesNotMatch(content(page.root), /What excuse could you invent\?/);
    assert.equal(page.nodes('button').some(node => node.props.class === 'discussion-minute'), false);
    page.click('How to pass by?');
    assert.deepEqual(page.events, [['answer', 'newcomer-discussion', { modeId: 'pass' }]]);
    assert.equal(page.button('How to help?').props['aria-pressed'], true, 'A pending request must not change the saved choice');
    const acknowledged = { value: { modeId: 'pass' }, grade: null, status: 'submitted' };
    page.props.answer = acknowledged;
    await Vue.nextTick();
    assert.equal(page.button('How to pass by?').props['aria-pressed'], true);
    assert.match(content(page.root), /An excuse for inaction/);
    assert.match(content(page.root), /What excuse could you invent\?/);
    assert.doesNotMatch(content(page.root), /What exact words could you say\?/);
    const remounted = fixture(t, discussion(), { answer: structuredClone(acknowledged) }, presentationComponent);
    assert.equal(remounted.button('How to pass by?').props['aria-pressed'], true);
    assert.match(content(remounted.root), /An excuse for inaction/);
    assert.deepEqual(remounted.events, []);
});

test('disabled student and shared projector cannot send school directions or teacher commands', async t => {
    for (const extra of [{ disabled: true }, { interactive: false }]) {
        const page = fixture(t, discussion(), extra, presentationComponent);
        assert.equal(page.button('How to help?').props.disabled, true);
        page.click('How to help?');
        assert.deepEqual(page.events, []);
        assert.equal(page.nodes('button').some(node => node.props.class === 'discussion-minute'), false);
    }
});

test('presenter school direction remains a shared command rather than a personal answer', async t => {
    const page = fixture(t, discussion(), { presenter: true, interactive: false, messages: { discussion_minute: 'One minute' } }, presentationComponent);
    page.click('How to help?');
    assert.deepEqual(page.events, [['command', 'presentation.mode', { blockId: 'newcomer-discussion', modeId: 'help' }]]);
    assert.equal(page.button('How to pass by?').props['aria-pressed'], true);
    page.props.block.runtime.presentation.modeId = 'help';
    await Vue.nextTick();
    assert.equal(page.button('How to help?').props['aria-pressed'], true);
    page.click('One minute');
    assert.deepEqual(page.events[1], ['command', 'timer.start', { seconds: 60 }]);
});

test('authored presenter role labels distinguish next, reset and first role after acknowledged reset', async t => {
    const block = { id: 'roles', type: 'core.roles', content: { roles: [{ roleId: 'a', text: 'Traveler' }, { roleId: 'b', text: 'Samaritan' }] }, config: {}, runtime: { status: 'open', mode: 'lesson', presentation: { revealedRoleIds: [], roleReset: false } } };
    const page = fixture(t, block, { interactive: false, presenter: true, presentationLabels: {
        subtitle: 'Who shall we call first?', actionLabel: 'Next role', resetLabel: 'Reset roles', restartLabel: 'First role', resetText: 'Starting a new set of roles',
    } });
    assert.match(content(page.root), /Who shall we call first\?/);
    page.click('Next role');
    assert.deepEqual(page.events, [['command', 'role.reveal.next', { blockId: 'roles' }]]);
    page.props.block.runtime.presentation = { revealedRoleIds: ['a', 'b'], roleReset: false };
    await Vue.nextTick();
    assert.ok(page.button('Reset roles'));
    page.click('Reset roles');
    assert.deepEqual(page.events[1], ['command', 'role.reveal.reset', { blockId: 'roles' }]);
    page.props.block.runtime.presentation.revealedRoleIds = [];
    page.props.block.runtime.presentation.roleReset = true;
    await Vue.nextTick();
    assert.match(content(page.root), /Starting a new set of roles/);
    assert.doesNotMatch(content(page.root), /Traveler|Samaritan/);
    page.click('First role');
    assert.deepEqual(page.events[2], ['command', 'role.reveal.next', { blockId: 'roles' }]);
});

test('authored shared-road feedback uses first mistake, later mistake, next step, completion and joint review states', async t => {
    const block = sequence();
    Object.assign(block.content, { feedback: 'Start with attention', feedbackFirstWrong: 'First notice the person', feedbackWrong: 'That act comes later', feedbackCorrect: 'Correct. Now step {step}.', feedbackComplete: 'Care continued after departure', reviewLabel: 'Joint review in progress' });
    const page = fixture(t, block, { presenter: true, interactive: false });
    assert.match(content(page.root), /Start with attention/);
    for (const [presentation, expected] of [
        [{ itemIds: [], feedback: 'incorrect' }, 'First notice the person'],
        [{ itemIds: ['a'], feedback: 'incorrect' }, 'That act comes later'],
        [{ itemIds: ['a', 'b'], feedback: 'correct' }, 'Correct. Now step 3.'],
        [{ itemIds: ['a', 'b', 'c'], feedback: 'complete' }, 'Care continued after departure'],
    ]) {
        page.props.block.runtime.presentation = presentation;
        await Vue.nextTick();
        const feedback = page.nodes('p').filter(node => node.props.class === 'sequence-feedback');
        assert.equal(feedback.length, 1);
        assert.equal(content(feedback[0]), expected);
    }
    page.props.block.runtime.results = { itemIds: ['a', 'b', 'c'] };
    await Vue.nextTick();
    assert.equal(content(page.nodes('p').find(node => node.props.class === 'sequence-feedback')), 'Joint review in progress');
    assert.deepEqual(page.events, []);
});

test('join overlay generates an actual 768px QR for the exact saved classroom URL and keeps close controls presenter-only', async t => {
    const url = 'https://lessons.example.test/ru/join?code=ABC12345';
    const labels = { scan_qr: 'Scan to join', student_join: 'Join class', show_qr_on_screen: 'Join projection', hide_qr_on_screen: 'Hide projection', qr_alt: 'Class QR' };
    for (const dismissible of [false, true]) {
        const page = fixture(t, undefined, { code: 'ABC12345', url, messages: labels, dismissible }, joinComponent);
        await Promise.all(qrRequests);
        await new Promise(resolve => setImmediate(resolve));
        await Vue.nextTick();
        assert.match(content(page.root), /ABC12345/);
        assert.ok(content(page.root).includes(url));
        const image = page.nodes('img')[0];
        assert.ok(image, 'A generated QR must be rendered');
        assert.equal(image.props.width, '768');
        assert.equal(image.props.height, '768');
        assert.equal(image.props.alt, 'Class QR');
        assert.equal(image.props.src, await QRCode.toDataURL(url, { width: 768, margin: 3, color: { dark: '#173f3b', light: '#ffffff' } }));
        const png = Buffer.from(image.props.src.split(',')[1], 'base64');
        assert.equal(png.readUInt32BE(16), 768);
        assert.equal(png.readUInt32BE(20), 768);
        assert.equal(page.nodes('button').length, dismissible ? 1 : 0);
        if (dismissible) {
            page.nodes('button')[0].props.onClick({});
            assert.deepEqual(page.events, [['dismiss']]);
        }
    }
});

test('runtime message renders as a classroom overlay and clear/QR-close events remain teacher-only', async t => {
    const state = { id: 'class-one', status: 'running', timer: { status: 'idle' }, message: '<b>Discuss in pairs</b>', wave: null, joinProjection: null };
    const labels = { screen_message: 'Class message', clear_message: 'Clear message', hide_qr_on_screen: 'Hide QR' };
    for (const presenter of [false, true]) {
        const page = fixture(t, undefined, { state: structuredClone(state), messages: labels, presenter, interactive: false, hideStatus: true }, statusComponent);
        assert.equal(page.nodes('section').length, 1);
        assert.equal(page.nodes('section')[0].props.class, 'classroom-overlay message-projection');
        assert.equal(page.nodes('section')[0].props.role, 'status');
        assert.equal(page.nodes('section')[0].props['aria-live'], 'polite');
        assert.match(content(page.root), /<b>Discuss in pairs<\/b>/);
        assert.equal(page.nodes('b').length, 0, 'Broadcast text must not become executable markup');
        assert.equal(page.nodes('button').length, presenter ? 1 : 0);
        if (presenter) {
            page.nodes('button')[0].props.onClick({});
            assert.deepEqual(page.events, [['clearMessage']]);
        }
        page.props.state.message = null;
        page.props.state.joinProjection = { code: 'JOIN1234', url: 'https://lessons.example.test/ru/join?code=JOIN1234' };
        await Promise.all(qrRequests);
        await new Promise(resolve => setImmediate(resolve));
        await Vue.nextTick();
        assert.equal(page.nodes('section').length, 1);
        assert.equal(page.nodes('section')[0].props.class, 'classroom-overlay join-projection');
        if (presenter) {
            page.nodes('button')[0].props.onClick({});
            assert.deepEqual(page.events.at(-1), ['hideJoin']);
        } else assert.equal(page.nodes('button').length, 0);
    }
});

test('private weekly choice saves only the student answer and has no projector or presenter buttons', async t => {
    const block = { id: 'private', type: 'core.presentation', config: { kind: 'personal-choice' }, content: { text: 'Choose privately', modes: [{ modeId: 'pause', text: 'Pause' }, { modeId: 'apology', text: 'Apologize' }] }, runtime: { presentation: { modeId: 'pause' } } };
    const page = fixture(t, block, { answer: { value: { modeId: 'apology' } } }, presentationComponent);
    assert.equal(page.button('Apologize').props['aria-pressed'], true);
    page.click('Pause');
    assert.deepEqual(page.events, [['answer', 'private', { modeId: 'pause' }]]);
    for (const presenter of [false, true]) {
        const publicPage = fixture(t, structuredClone(block), { interactive: false, presenter }, presentationComponent);
        assert.equal(publicPage.nodes('button').length, 0);
        assert.equal(content(publicPage.root), 'Choose privately');
    }
});

test('sequential teacher control opens the next phrase only after the previous result is revealed', async t => {
    const stage = { config: { sequentialTasks: true }, blocks: ['first', 'second'].map(id => ({ id, type: 'core.poll', config: {}, content: { question: id } })) };
    const session = { publicStage: stage, blockStates: [{ blockId: 'first', status: 'open' }, { blockId: 'second', status: 'prepared' }], answers: [], participants: [] };
    const page = fixture(t, {}, { stage, session, disabled: false, messages: { next_task: 'Next phrase', review_together: 'Review' } }, teacherToolsComponent);
    assert.equal(page.button('Next phrase').props.disabled, true);
    page.props.session.blockStates[0].status = 'revealed';
    await Vue.nextTick();
    assert.equal(page.button('Next phrase').props.disabled, false);
    page.click('Next phrase');
    assert.deepEqual(page.events, [['command', 'block.open', { blockId: 'second' }]]);
});
