import http from 'node:http';
import { spawnSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { performance } from 'node:perf_hooks';
import { setTimeout as sleep } from 'node:timers/promises';
import { fileURLToPath } from 'node:url';
import { Client, guardedUrl, metrics } from './client.mjs';

const agents = [];
const samples = new Map();
const failures = {};
const counts = { rooms: 0, participants: 0, expectedAnswers: 0, storedAnswers: 0, duplicateAnswers: 0, commandReplays: 0, reconnects: 0, isolationChecks: 0, csrfChecks: 0, integrityFailures: 0 };
const options = {};
const started = performance.now();
let polling = [];
let stopPolling = false;
let passed = false;
let failure = null;

function check(condition) {
    if (!condition) {
        counts.integrityFailures++;
        throw new Error('integrity_failed');
    }
}
function uuid(value) {
    check(typeof value === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value));
}
function record(category, ms, status, failed) {
    if (!samples.has(category)) samples.set(category, []);
    samples.get(category).push(ms);
    if (failed) failures[status ? `http_${status}` : 'transport'] = (failures[status ? `http_${status}` : 'transport'] ?? 0) + 1;
}
function agent(roomIndex) {
    const created = new http.Agent({ keepAlive: true, maxSockets: 64, localAddress: `127.0.0.${roomIndex + 2}` });
    agents.push(created);
    return created;
}
function positive(value, maximum) {
    if (!/^[1-9][0-9]*$/.test(value) || Number(value) > maximum) throw new Error('invalid_options');
    return Number(value);
}
function assertPublic(state, room, ownCount) {
    check(state.id === room.session.id);
    check(!Object.hasOwn(state, 'answers') && !Object.hasOwn(state, 'participants') && !Object.hasOwn(state, 'document'));
    const visit = (value) => {
        if (!value || typeof value !== 'object') return;
        for (const [key, child] of Object.entries(value)) {
            check(!['teacherNotes', 'solution', 'participantId', 'moderation', 'owner_key'].includes(key));
            visit(child);
        }
    };
    visit(state);
    if (ownCount !== undefined) check(state.ownAnswers.length === ownCount);
}
async function command(room, action, payload = {}) {
    // This immutable envelope survives a deliberately discarded first acknowledgement.
    const envelope = Object.freeze({ commandId: randomUUID(), expectedRevision: room.session.revision, action, payload });
    const path = `/api/studio/sessions/${room.session.id}/commands`;
    const first = await room.owner.request(path, { method: 'POST', body: envelope, category: 'command' });
    check(first.acknowledgedCommandId === envelope.commandId);
    const reconnectAgent = agent(room.index);
    const reconnect = new Client(room.owner.url, reconnectAgent, record, new Map(room.owner.jar), room.owner.csrf);
    try {
        const replay = await reconnect.request(path, { method: 'POST', body: envelope, category: 'commandReplay' });
        check(replay.acknowledgedCommandId === envelope.commandId && replay.session.revision === first.session.revision);
        room.session = replay.session;
        room.owner.jar = reconnect.jar;
        counts.commandReplays++;
    } finally {
        reconnectAgent.destroy();
    }
}
async function submit(room, student, stageId, blockId, value, retry = false) {
    const body = Object.freeze({ stageId, blockId, value });
    const path = `/api/participation/${room.session.id}/answers`;
    const first = await student.client.request(path, { method: 'POST', body, category: 'answer' });
    assertPublic(first.session, room);
    const stored = first.session.ownAnswers.find((answer) => answer.blockId === blockId);
    check(stored && JSON.stringify(stored.value) === JSON.stringify(value));
    if (retry) {
        const reconnectAgent = agent(room.index);
        const reconnect = new Client(student.client.url, reconnectAgent, record, new Map(student.client.jar), student.client.csrf);
        try {
            const replay = await reconnect.request(path, { method: 'POST', body, category: 'answerReplay' });
            const repeated = replay.session.ownAnswers.find((answer) => answer.blockId === blockId);
            check(repeated?.id === stored.id && repeated?.revision === stored.revision);
            student.client.jar = reconnect.jar;
            counts.reconnects++;
        } finally {
            reconnectAgent.destroy();
        }
    }
}
async function poll(client, path, validate) {
    // One outstanding poll per browser session, matching the 2-second application cadence.
    while (!stopPolling) {
        const cycle = performance.now();
        validate((await client.request(path, { category: 'poll' })).session);
        if (!stopPolling) await sleep(Math.max(0, 2000 - (performance.now() - cycle)));
    }
}

try {
    const args = process.argv.slice(2);
    if (args.length % 2) throw new Error('invalid_options');
    for (let i = 0; i < args.length; i += 2) {
        if (!['--url', '--rooms', '--students', '--duration-seconds', '--max-p95-ms'].includes(args[i]) || Object.hasOwn(options, args[i])) throw new Error('invalid_options');
        options[args[i]] = args[i + 1];
    }
    if (Number(process.versions.node.split('.')[0]) !== 22 || process.platform !== 'linux'
        || process.env.APP_ENV !== 'testing' || process.env.DB_DATABASE !== 'lessons_test'
        || !['127.0.0.1', 'localhost', '::1'].includes(process.env.DB_HOST)
        || !['mysql', 'mariadb'].includes(process.env.DB_CONNECTION) || process.env.DB_URL) {
        throw new Error('linux_node22_testing_database_required');
    }
    const url = guardedUrl(options['--url'] ?? 'http://127.0.0.1:8765/');
    const roomCount = positive(options['--rooms'] ?? '10', 100);
    const studentCount = positive(options['--students'] ?? '30', 100);
    const duration = positive(options['--duration-seconds'] ?? '15', 120) * 1000;
    const maxP95 = options['--max-p95-ms'] ? positive(options['--max-p95-ms'], 60000) : null;
    check(roomCount >= 2 && duration >= 6000);
    const fixtureProcess = spawnSync(process.env.LESSONS_LOAD_PHP ?? 'php', [fileURLToPath(new URL('./fixture.php', import.meta.url))], { encoding: 'utf8', timeout: 30000 });
    if (fixtureProcess.status !== 0) throw new Error('fixture_guard_failed');
    const fixture = JSON.parse(fixtureProcess.stdout);
    check(fixture.guard === 'testing/lessons_test/MariaDB10.6');
    const rooms = await Promise.all(Array.from({ length: roomCount }, async (_, index) => {
        const room = { index, owner: new Client(url, agent(index), record), projector: new Client(url, agent(index), record), students: [] };
        await room.owner.bootstrap();
        const invalidCsrf = new Client(url, room.owner.agent, record, new Map(room.owner.jar), 'invalid-token');
        await invalidCsrf.request('/api/studio/lessons', { method: 'POST', body: { document: fixture.document }, expected: 419, category: 'isolation' });
        counts.csrfChecks++;
        const created = (await room.owner.request('/api/studio/lessons', { method: 'POST', body: { document: fixture.document }, expected: 201 })).lesson;
        uuid(created.id);
        const released = (await room.owner.request(`/api/studio/lessons/${created.id}/release`, { method: 'POST', body: { expectedRevision: created.revision } })).lesson;
        check(released.status === 'released');
        uuid(released.versionId);
        room.lesson = released;
        room.session = (await room.owner.request(`/api/studio/lessons/${released.id}/sessions`, { method: 'POST', body: { expectedRevision: released.revision, prepare: true }, expected: 201 })).session;
        uuid(room.session.id);
        room.projectorPath = `/api/projection/${new URL(room.session.projectorUrl).pathname.split('/').at(-1)}`;
        // Edit the authoring draft after release; the running immutable snapshot must retain its title.
        const edited = structuredClone(released.document);
        edited.content.ru.title = 'Edited authoring draft after immutable release';
        await room.owner.request(`/api/studio/lessons/${released.id}`, { method: 'PUT', body: { expectedRevision: released.revision, document: edited } });
        check((await room.owner.request(`/api/studio/sessions/${room.session.id}`)).session.document.content.title === fixture.document.content.ru.title);
        await command(room, 'begin');
        await command(room, 'block.open', { blockId: 'poll' });
        room.students = await Promise.all(Array.from({ length: studentCount }, async (_, studentIndex) => {
            const client = new Client(url, room.owner.agent, record);
            await client.bootstrap();
            return { client, studentIndex, name: `Synthetic room ${index} student ${studentIndex}` };
        }));
        return room;
    }));
    counts.rooms = rooms.length;
    const allStudents = rooms.flatMap((room) => room.students.map((student) => ({ room, student })));
    const tokens = [...rooms.map((room) => room.owner.csrf), ...allStudents.map(({ student }) => student.client.csrf)];
    check(new Set(tokens).size === tokens.length);
    await Promise.all(allStudents.map(async ({ room, student }) => {
        const joined = await student.client.request('/api/join', { method: 'POST', body: { code: room.session.joinCode, name: student.name }, category: 'join' });
        check(joined.sessionId === room.session.id);
        student.id = joined.participant.id;
        uuid(student.id);
    }));
    check(new Set(allStudents.map(({ student }) => student.id)).size === allStudents.length);
    counts.participants = allStudents.length;
    const loadStarted = performance.now();
    polling = allStudents.map(({ room, student }) => poll(student.client, `/api/participation/${room.session.id}`, (state) => assertPublic(state, room)));
    for (const room of rooms) {
        polling.push(poll(room.owner, `/api/studio/sessions/${room.session.id}`, (state) => check(state.id === room.session.id)));
        polling.push(poll(room.projector, room.projectorPath, (state) => assertPublic(state, room)));
    }
    // Install rejection handlers immediately while other concurrent phases execute.
    const pollResults = Promise.allSettled(polling);
    await Promise.all(allStudents.map(({ room, student }) => submit(room, student, 'first', 'single', { optionId: student.studentIndex % 2 ? 'b' : 'a' })));
    await Promise.all(allStudents.map(({ room, student }) => submit(room, student, 'first', 'poll', { optionId: student.studentIndex % 2 ? 'a' : 'b' })));
    await Promise.all(rooms.map(async (room) => {
        await command(room, 'stage', { stageId: 'second' });
        await command(room, 'block.open', { blockId: 'free' });
    }));
    await Promise.all(allStudents.map(({ room, student }) => submit(room, student, 'second', 'free', { text: `room-${room.index}/student-${student.studentIndex}` }, true)));
    if (performance.now() - loadStarted < duration) await sleep(duration - (performance.now() - loadStarted));
    stopPolling = true;
    check((await pollResults).every((result) => result.status === 'fulfilled'));
    counts.expectedAnswers = allStudents.length * 3;
    for (const room of rooms) {
        const state = (await room.owner.request(`/api/studio/sessions/${room.session.id}`, { category: 'verify' })).session;
        check(state.participants.length === studentCount && state.answers.length === studentCount * 3);
        const ids = new Set(room.students.map((student) => student.id));
        const distinct = new Set(state.answers.map((answer) => `${answer.participantId}/${answer.blockId}`));
        counts.duplicateAnswers += state.answers.length - distinct.size;
        counts.storedAnswers += state.answers.length;
        for (const student of room.students) {
            check(state.participants.find((participant) => participant.id === student.id)?.name === student.name);
            const answers = state.answers.filter((answer) => answer.participantId === student.id);
            check(answers.length === 3 && answers.every((answer) => answer.revision === 1 && ids.has(answer.participantId)));
            for (const answer of answers) uuid(answer.id);
            check(answers.find((answer) => answer.blockId === 'free')?.value.text === `room-${room.index}/student-${student.studentIndex}`);
            check(answers.find((answer) => answer.blockId === 'single')?.value.optionId === (student.studentIndex % 2 ? 'b' : 'a'));
            check(answers.find((answer) => answer.blockId === 'poll')?.value.optionId === (student.studentIndex % 2 ? 'a' : 'b'));
        }
    }
    await Promise.all(allStudents.map(async ({ room, student }) => {
        const own = (await student.client.request(`/api/participation/${room.session.id}`, { category: 'verify' })).session;
        assertPublic(own, room, 3);
        check(own.ownAnswers.find((answer) => answer.blockId === 'free')?.value.text === `room-${room.index}/student-${student.studentIndex}`);
        const foreign = rooms[(room.index + 1) % rooms.length];
        await student.client.request(`/api/participation/${foreign.session.id}`, { expected: 404, category: 'isolation' });
        counts.isolationChecks++;
    }));
    await Promise.all(rooms.map(async (room) => {
        const foreign = rooms[(room.index + 1) % rooms.length];
        await room.owner.request(`/api/studio/sessions/${foreign.session.id}`, { expected: 404, category: 'isolation' });
        counts.isolationChecks++;
        assertPublic((await room.projector.request(room.projectorPath, { category: 'verify' })).session, room);
        await command(room, 'finish');
    }));
    check(counts.storedAnswers === counts.expectedAnswers && counts.duplicateAnswers === 0);
    const loadMetrics = metrics([...samples].filter(([key]) => !['setup', 'verify', 'isolation'].includes(key)).flatMap(([, values]) => values));
    if (maxP95 !== null && loadMetrics.p95Ms > maxP95) throw new Error('approved_latency_threshold_failed');
    passed = Object.values(failures).reduce((sum, count) => sum + count, 0) === 0;
} catch (error) {
    // Public output never includes response bodies, URLs with bearer tokens, cookies or SQL.
    failure = ['integrity_failed', 'invalid_options', 'loopback_url_required', 'linux_node22_testing_database_required', 'fixture_guard_failed', 'http_test_guard_required', 'approved_latency_threshold_failed'].includes(error.message) ? error.message : 'load_run_failed';
} finally {
    stopPolling = true;
    await Promise.allSettled(polling);
    for (const created of agents) created.destroy();
    console.log(JSON.stringify({ passed, failure, durationSeconds: Number(((performance.now() - started) / 1000).toFixed(2)), counts, requestErrors: failures,
        measured: metrics([...samples].filter(([key]) => !['setup', 'verify', 'isolation'].includes(key)).flatMap(([, values]) => values)),
        byOperation: Object.fromEntries([...samples].map(([key, values]) => [key, metrics(values)])),
        latencyThresholdMs: options['--max-p95-ms'] ? Number(options['--max-p95-ms']) : null, profileStatus: 'proposed_until_owner_acceptance' }));
    process.exitCode = passed ? 0 : 1;
}
