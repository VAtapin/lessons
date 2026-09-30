<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import QRCode from 'qrcode';
import { api, ApiError, errorMessage, poll } from './api';
import { command, connectedControl, acceptProjection, matchesControlMessage, controlReturnConfirmed, recoverCommand, type Command } from './runtime';
import StageRenderer from './StageRenderer.vue';
import RuntimeStatus from './RuntimeStatus.vue';
import type { Messages, TeacherState } from './types';
const props = defineProps<{ sessionId: string; locale: string; messages: Messages; compact?: boolean }>();
const session = ref<TeacherState>();
const error = ref('');
const connected = ref(false);
const busy = ref(false);
const confirmFinish = ref(false);
const pending = ref<Command>();
const pendingKey = `lesson-command:${props.sessionId}`;
try { pending.value = recoverCommand(sessionStorage.getItem(pendingKey)); } catch { /* Storage may be unavailable. */ }
watch(pending, value => {
    try { if (value) sessionStorage.setItem(pendingKey, JSON.stringify(value)); else sessionStorage.removeItem(pendingKey); }
    catch { /* Retry remains available for this page. */ }
}, { flush: 'sync' });
const stagesOpen = ref(!props.compact);
const seconds = ref(60);
const message = ref('');
const qr = ref('');
const detached = ref(false);
const detachError = ref(false);
const instance = new URLSearchParams(location.search).get('instance');
let channel: BroadcastChannel | undefined;
let activeInstance: string | null = null;
let heartbeatAt = 0;
let returning = false;
let returnTimeout: ReturnType<typeof setTimeout> | undefined;
let generation = 0;
const stage = computed(() => session.value?.document.stages.find(item => item.id === session.value?.currentStageId));
const index = computed(() => session.value?.document.stages.findIndex(item => item.id === session.value?.currentStageId) ?? 0);
const disabled = computed(() => busy.value || !!pending.value || !connected.value || session.value?.status === 'finished');
const solutions = computed(() => stage.value?.blocks.flatMap(block => {
    if (block.type !== 'core.single-choice' || !block.solution) return [];
    const option = block.content.options?.find(item => item.optionId === block.solution!.optionId);
    return option ? [{ blockId: block.id, question: block.content.question, answer: option.text }] : [];
}) ?? []);
watch(() => stage.value?.id, () => { seconds.value = stage.value?.config.durationSeconds ?? 60; });
watch(() => session.value?.joinUrl, async url => {
    qr.value = '';
    if (!url) return;
    try { const data = await QRCode.toDataURL(url, { width: 160, margin: 2, color: { dark: '#492d19', light: '#fffaf0' } }); if (url === session.value?.joinUrl) qr.value = data; }
    catch { error.value = props.messages.error_qr ?? ''; }
});
try {
    channel = new BroadcastChannel(`lesson-control:${props.sessionId}`);
    channel.onmessage = event => {
        const data = event.data;
        if (!data || typeof data !== 'object') return;
        if (!props.compact && matchesControlMessage(activeInstance, data)) {
            if (data.type === 'ready' || data.type === 'heartbeat') { heartbeatAt = performance.now(); detached.value = true; detachError.value = false; }
            if (data.type === 'return') channel?.postMessage({ type: 'returnAck', instance: activeInstance });
            if (data.type === 'return' || data.type === 'closed') { detached.value = false; activeInstance = null; heartbeatAt = 0; }
        }
        if (props.compact && controlReturnConfirmed(instance, data, returning)) closeReturnedControl();
    };
} catch { /* Detachment keeps embedded controls available without BroadcastChannel. */ }
const heartbeat = setInterval(() => {
    if (props.compact && instance && connected.value && !returning) channel?.postMessage({ type: 'heartbeat', instance });
    if (!props.compact && activeInstance && !connectedControl(activeInstance, heartbeatAt, performance.now())) {
        detached.value = false;
        if (heartbeatAt > 0) { activeInstance = null; heartbeatAt = 0; detachError.value = true; }
    }
}, 1500);
function announceClose() { if (props.compact && instance) channel?.postMessage({ type: 'closed', instance }); }
window.addEventListener('pagehide', announceClose);
onBeforeUnmount(() => { announceClose(); channel?.close(); clearInterval(heartbeat); clearTimeout(returnTimeout); window.removeEventListener('pagehide', announceClose); });
poll(async signal => {
    const started = generation;
    try {
        const response = await api<{ session: TeacherState }>(`/api/studio/sessions/${props.sessionId}`, 'GET', undefined, signal);
        if (acceptProjection(started, generation, busy.value, session.value?.revision, response.session.revision)) {
            session.value = response.session; connected.value = true;
            if (!pending.value) error.value = '';
            if (props.compact && instance && !returning) channel?.postMessage({ type: 'ready', instance });
        }
    } catch (problem) { if (started === generation && !busy.value) throw problem; }
}, problem => { connected.value = false; error.value = errorMessage(problem, props.messages); });
async function send(action: string, payload: Record<string, unknown> = {}) {
    if (!session.value || disabled.value) return;
    pending.value = command(session.value.revision, action, payload);
    await retry();
}
async function retry() {
    if (!pending.value || busy.value) return;
    busy.value = true; generation++; error.value = '';
    try {
        const response = await api<{ session: TeacherState; acknowledgedCommandId: string }>(`/api/studio/sessions/${props.sessionId}/commands`, 'POST', pending.value);
        if (response.acknowledgedCommandId !== pending.value.commandId) throw new ApiError('request', 0);
        session.value = response.session; connected.value = true; pending.value = undefined;
    } catch (problem) {
        error.value = errorMessage(problem, props.messages);
        if (problem instanceof ApiError && problem.status >= 400 && problem.status < 500) {
            const state = (problem.data as { session?: TeacherState } | undefined)?.session;
            if (state) session.value = state;
            connected.value = !!state;
            pending.value = undefined;
        } else { connected.value = false; }
    } finally { busy.value = false; generation++; }
}
function navigate(stageId: string) { if (stageId !== session.value?.currentStageId) void send('stage', { stageId }); }
function detach(asTab = false) {
    detachError.value = false;
    if (!channel) { detachError.value = true; return; }
    activeInstance = crypto.randomUUID(); heartbeatAt = 0;
    const url = `/${props.locale}/control/${props.sessionId}?instance=${activeInstance}`;
    const opened = window.open(url, '_blank', asTab ? undefined : 'popup,width=520,height=820');
    if (opened) opened.opener = null;
    if (!opened) { activeInstance = null; detachError.value = true; return; }
    const attempted = activeInstance;
    setTimeout(() => { if (activeInstance === attempted && !detached.value) { activeInstance = null; detachError.value = true; } }, 8000);
}
function restore() {
    if (props.compact) {
        if (returning) return;
        returning = true;
        if (instance) channel?.postMessage({ type: 'return', instance });
        returnTimeout = setTimeout(() => { location.assign(`/${props.locale}/teach/${props.sessionId}`); }, 1000);
    } else {
        if (activeInstance) channel?.postMessage({ type: 'restore', instance: activeInstance });
        activeInstance = null; heartbeatAt = 0; detached.value = false;
    }
}
function closeReturnedControl() {
    returning = true;
    clearTimeout(returnTimeout);
    window.close();
    // A directly opened browser tab may prohibit script closing; retain access there.
    returnTimeout = setTimeout(() => { location.assign(`/${props.locale}/teach/${props.sessionId}`); }, 150);
}
function finish() { confirmFinish.value = true; }
function answerText(blockId: string, optionId: string) {
    const block = session.value?.document.stages.flatMap(item => item.blocks).find(item => item.id === blockId);
    return block?.content.options?.find(option => option.optionId === optionId)?.text ?? optionId;
}
</script>
<template>
    <div :class="['teacher-panel', { 'compact-control': compact }]">
        <div class="page-heading"><div><p class="eyebrow">{{ messages.teacher_panel }}</p><h1>{{ session?.document.content.title ?? messages.loading }}</h1></div><span class="status-pill" role="status">{{ connected ? messages.connected : messages.reconnecting }}</span></div>
        <p v-if="error" role="alert" class="error-banner">{{ error }}</p>
        <div v-if="pending" class="info-banner" role="status">{{ messages.command_pending }} <button :disabled="busy" @click="retry">{{ messages.retry_command }}</button></div>
        <p v-if="!session" role="status">{{ messages.loading }}</p>
        <template v-if="session && stage">
            <RuntimeStatus :state="session" :messages="messages" />
            <div class="join-strip studio-card"><div><small>{{ messages.join_code }}</small><strong class="join-code">{{ session.joinCode }}</strong><a :href="session.joinUrl" target="_blank" rel="noopener">{{ messages.student_join }} ↗</a><details class="qr-details"><summary>{{ messages.show_qr }}</summary><img v-if="qr" :src="qr" :alt="messages.qr_alt" width="160" height="160" /></details></div><a class="button-link" :href="session.projectorUrl" target="_blank" rel="noopener">{{ messages.open_projector }} ↗</a></div>
            <div class="detach-toolbar"><template v-if="compact || detached"><p v-if="detached">{{ messages.control_detached }}</p><button @click="restore">{{ messages.return_control }}</button></template><template v-else><button @click="detach()">{{ messages.detach_window }} ↗</button><button @click="detach(true)">{{ messages.detach_tab }} ↗</button></template></div>
            <p v-if="detachError" class="info-banner" role="status">{{ messages.detach_failed }} <button @click="detach(true)">{{ messages.detach_tab }}</button></p>
            <div :class="['teacher-layout', { 'stages-collapsed': !stagesOpen, detached, compact }]">
                <aside v-if="!detached" class="studio-card stage-list"><button :aria-expanded="stagesOpen" aria-controls="conducting-stages" @click="stagesOpen = !stagesOpen">{{ stagesOpen ? messages.hide_stages : messages.show_stages }}</button><div v-if="stagesOpen" id="conducting-stages"><h2>{{ messages.stages }}</h2><button v-for="(item, number) in session.document.stages" :key="item.id" :disabled="disabled" :class="['stage-select', { active: item.id === session.currentStageId }]" :aria-current="item.id === session.currentStageId ? 'step' : undefined" @click="navigate(item.id)"><span>{{ number + 1 }}</span>{{ item.content.title }}</button></div></aside>
                <div class="teacher-main">
                    <div v-if="!compact" class="studio-card screen-preview" tabindex="0" role="region" :aria-label="messages.shared_screen"><p class="eyebrow">{{ messages.shared_screen }}</p><StageRenderer :stage="session.publicStage" :messages="messages" /></div>
                    <template v-if="!detached">
                        <div class="studio-card navigation-controls"><button :disabled="disabled || index === 0" @click="navigate(session.document.stages[index - 1]!.id)">← {{ messages.previous }}</button><span>{{ index + 1 }} / {{ session.document.stages.length }}</span><button class="primary" :disabled="disabled || index === session.document.stages.length - 1" @click="navigate(session.document.stages[index + 1]!.id)">{{ messages.next }} →</button></div>
                        <details v-if="stage.content.notes" class="studio-card teacher-notes" :open="!compact"><summary>{{ messages.notes }}</summary><p class="plain-text">{{ stage.content.notes }}</p></details><details v-if="solutions.length" class="studio-card teacher-solutions"><summary>{{ messages.correct_answers }}</summary><dl><div v-for="solution in solutions" :key="solution.blockId"><dt>{{ solution.question }}</dt><dd>{{ solution.answer }}</dd></div></dl></details>
                    </template>
                </div>
                <aside v-if="!detached" class="teacher-tools">
                        <section class="studio-card conducting-controls"><div class="action-row"><button v-if="session.status === 'prepared'" class="primary" :disabled="disabled" @click="send('begin')">{{ messages.begin_session }}</button><button v-if="session.status === 'running'" :disabled="disabled" @click="send('pause')">{{ messages.pause_session }}</button><button v-if="session.status === 'paused'" class="primary" :disabled="disabled" @click="send('resume')">{{ messages.resume_session }}</button><button v-if="session.status !== 'finished' && !confirmFinish" :disabled="disabled" @click="finish">{{ messages.finish_session }}</button></div><div v-if="confirmFinish && session.status !== 'finished'" role="alert" class="info-banner"><p>{{ messages.finish_confirm }}</p><div class="action-row"><button class="primary" :disabled="disabled" @click="confirmFinish = false; send('finish')">{{ messages.finish_yes }}</button><button @click="confirmFinish = false">{{ messages.cancel }}</button></div></div><h2>{{ messages.timer }}</h2><form class="timer-form" @submit.prevent="send('timer.start', { seconds: Number(seconds) })"><label>{{ messages.timer_seconds }}<input v-model="seconds" type="number" required min="1" max="7200" :disabled="disabled" /></label><button :disabled="disabled || session.status !== 'running'">{{ messages.start_timer }}</button></form><div class="action-row"><button v-if="session.timer.status === 'running'" :disabled="disabled" @click="send('timer.pause')">{{ messages.pause_timer }}</button><button v-if="session.timer.status === 'paused'" :disabled="disabled || session.status !== 'running'" @click="send('timer.resume')">{{ messages.resume_timer }}</button><button v-if="session.timer.status !== 'idle'" :disabled="disabled" @click="send('timer.clear')">{{ messages.clear_timer }}</button></div><p class="field-hint">{{ messages.timer_hint }}</p></section>
                        <section class="studio-card quick-actions"><h2>{{ messages.quick_actions }}</h2><button :disabled="disabled" @click="send('wave')">♥ {{ messages.send_wave }}</button><form @submit.prevent="send('message.set', { text: message })"><label>{{ messages.screen_message }}<textarea v-model="message" maxlength="1000" rows="2" :disabled="disabled" required /></label><div class="action-row"><button :disabled="disabled || !message.trim()">{{ messages.show_message }}</button><button type="button" :disabled="disabled || !session.message" @click="send('message.clear')">{{ messages.clear_message }}</button></div></form></section>
                    <section class="studio-card participants"><div class="section-heading"><h2>{{ messages.participants }}</h2><span class="status-pill">{{ session.participants.length }}</span></div><p class="field-hint">{{ messages.activity_hint }}</p><p v-if="!session.participants.length" class="field-hint">{{ messages.waiting_participants }}</p><div v-for="participant in session.participants" :key="participant.id" class="participant"><strong>{{ participant.name }}</strong><small>{{ participant.connected ? messages.participant_connected : messages.participant_away }}</small><ul v-if="session.answers.some(answer => answer.participantId === participant.id && stage!.blocks.some(block => block.id === answer.blockId))"><li v-for="answer in session.answers.filter(answer => answer.participantId === participant.id && stage!.blocks.some(block => block.id === answer.blockId))" :key="answer.blockId">{{ answerText(answer.blockId, answer.optionId) }}</li></ul><small v-else>{{ messages.no_answers }}</small></div></section>
                </aside>
            </div>
        </template>
    </div>
</template>
