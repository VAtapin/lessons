<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import QRCode from 'qrcode';
import { api, ApiError, errorMessage, poll } from './api';
import { connectedControl, acceptProjection, matchesControlMessage, controlReturnConfirmed } from './runtime';
import { canCommand, captureTeacherCommand, controlChannel, localInterfaceUrl, recoverTeacherCommand, sameTeacherAuthority, type TeacherPending } from './collaboration';
import CollaborationPanel from './CollaborationPanel.vue';
import '../../css/collaboration.css';
import TeacherBlockTools from './TeacherBlockTools.vue';
import TeacherAnswers from './TeacherAnswers.vue';
import { isInteractive, valueText } from './interactive';
import StageRenderer from './StageRenderer.vue';
import RuntimeStatus from './RuntimeStatus.vue';
import RuntimeTimer from './RuntimeTimer.vue';
import StudioIcon from './StudioIcon.vue';
import { activeStageAnswers, countAnsweredParticipants } from './conducting';
import type { ProjectedStage } from './types';
import type { CollaborationState, Messages, TeacherActorResponse, TeacherActorState, TeacherState } from './types';
const props = defineProps<{ sessionId: string; locale: string; messages: Messages; compact?: boolean; teacherScope?: 'grant' }>();
const session = ref<TeacherState>();
const actor = ref<TeacherActorState>();
const collaboration = ref<CollaborationState>();
const invitation = ref<TeacherActorResponse['invitation']>();
const invitationReplayed = ref(false);
const collaborationOpen = ref(false);
const accessLost = ref(false);
const scope = props.teacherScope ?? 'owner';
const endpoint = `/api/${scope === 'grant' ? 'conduct' : 'studio'}/sessions/${props.sessionId}`;
const teacherUrl = `/${props.locale}/${scope === 'grant' ? 'conduct' : 'teach'}/${props.sessionId}`;
const error = ref('');
const connected = ref(false);
const busy = ref(false);
const confirmFinish = ref(false);
const pending = ref<TeacherPending>();
const pendingKey = `lesson-command:v7:${scope}:${props.sessionId}`;
let recovered = false;
watch(pending, value => {
    if (scope === 'grant') return; // Grant retries remain only in this actor's memory.
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
const stageAnswers = computed(() => activeStageAnswers(session.value?.answers ?? [], stage.value));
const joinUrl = computed(() => localInterfaceUrl(session.value?.joinUrl, props.locale, location.origin));
const projectorUrl = computed(() => scope === 'grant' ? `/${props.locale}/conduct/${props.sessionId}/projector` : localInterfaceUrl(session.value?.projectorUrl, props.locale, location.origin));
const answeredParticipants = computed(() => countAnsweredParticipants(stageAnswers.value));
const stageImage = (item: ProjectedStage) => scope === 'grant' && item.id !== session.value?.currentStageId ? undefined : item.blocks.find(block => block.resources?.image)?.resources?.image;
const stageSymbol = (item: ProjectedStage) => item.blocks.some(block => isInteractive(block.type) || block.type === 'core.prompt') ? 'question' : item.blocks.some(block => block.type === 'core.image') ? 'media' : 'text';
const disabled = computed(() => busy.value || !!pending.value || !connected.value || accessLost.value || session.value?.status === 'finished');
const presentDisabled = computed(() => disabled.value || !actor.value?.capabilities.includes('present'));
const moderationDisabled = computed(() => disabled.value || !actor.value?.capabilities.includes('moderate'));
const manageDisabled = computed(() => busy.value || !!pending.value || !connected.value || accessLost.value || !actor.value?.capabilities.includes('manageCollaboration'));
const pendingStale = computed(() => !!pending.value && !sameTeacherAuthority(pending.value, actor.value, collaboration.value?.controlEpoch ?? -1));
const solutions = computed(() => stage.value?.blocks.flatMap(block => {
    if (!block.solution) return [];
    return [{ blockId: block.id, question: block.content.question, answer: valueText(block.content, block.solution, props.messages) }];
}) ?? []);
watch(() => stage.value?.id, () => { seconds.value = stage.value?.config.durationSeconds ?? 60; });
watch(joinUrl, async url => {
    qr.value = '';
    if (!url) return;
    try { const data = await QRCode.toDataURL(url, { width: 160, margin: 2, color: { dark: '#492d19', light: '#fffaf0' } }); if (url === joinUrl.value) qr.value = data; }
    catch { error.value = props.messages.error_qr ?? ''; }
});
try {
    channel = new BroadcastChannel(controlChannel(props.sessionId, scope));
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
    if (accessLost.value) return;
    const started = generation;
    try {
        const response = await api<TeacherActorResponse>(scope === 'owner' && session.value?.mode === 'lesson' ? `${endpoint}/collaboration` : endpoint, 'GET', undefined, signal);
        if (acceptProjection(started, generation, busy.value, session.value?.revision, response.session.revision)) {
            applyState(response); connected.value = true;
            if (!pending.value) error.value = '';
            if (props.compact && instance && !returning) channel?.postMessage({ type: 'ready', instance });
        }
    } catch (problem) { if (started === generation && !busy.value) throw problem; }
}, problem => { connected.value = false; error.value = errorMessage(problem, props.messages); revokeIfForbidden(problem); });
function applyState(response: TeacherActorResponse) {
    session.value = response.session; actor.value = response.actor; collaboration.value = response.collaboration;
    if (!recovered) {
        recovered = true;
        if (scope === 'owner') { try { pending.value = recoverTeacherCommand(sessionStorage.getItem(pendingKey), response.actor, response.collaboration.controlEpoch); if (!pending.value) sessionStorage.removeItem(pendingKey); } catch { /* Storage is optional. */ } }
    }
}
function revokeIfForbidden(problem: unknown) {
    if (scope !== 'grant' || !(problem instanceof ApiError) || ![401, 403, 404, 410, 419].includes(problem.status)) return;
    accessLost.value = true; session.value = undefined; actor.value = undefined; collaboration.value = undefined;
    pending.value = undefined; invitation.value = undefined; qr.value = ''; message.value = ''; connected.value = false;
}
async function send(action: string, payload: Record<string, unknown> = {}) {
    const governance = ['invite.create', 'invite.revoke', 'grant.revoke', 'presenter.transfer', 'presenter.reclaim'].includes(action);
    if (!session.value || !actor.value || !collaboration.value || (governance ? manageDisabled.value : disabled.value) || !canCommand(actor.value, action)) return;
    if (action === 'invite.create') { invitation.value = undefined; invitationReplayed.value = false; }
    pending.value = captureTeacherCommand(actor.value, session.value.revision, collaboration.value.controlEpoch, action, payload);
    await retry();
}
async function retry() {
    if (!pending.value || busy.value || pendingStale.value || accessLost.value) return;
    busy.value = true; generation++; error.value = '';
    try {
        const body = pending.value.command;
        const governance = ['invite.create', 'invite.revoke', 'grant.revoke', 'presenter.transfer', 'presenter.reclaim'].includes(body.action);
        const response = await api<TeacherActorResponse>(`${endpoint}${governance ? '/collaboration' : ''}/commands`, 'POST', body);
        if (response.acknowledgedCommandId !== body.commandId) throw new ApiError('request', 0);
        applyState(response); connected.value = true; pending.value = undefined;
        if (body.action === 'invite.create') { invitation.value = response.invitation; invitationReplayed.value = !response.invitation; }
        if (body.action === 'invite.revoke' && body.payload.id === invitation.value?.id) invitation.value = undefined;
    } catch (problem) {
        error.value = errorMessage(problem, props.messages);
        if (problem instanceof ApiError && problem.status >= 400 && problem.status < 500) {
            const state = problem.data as Partial<TeacherActorResponse> | undefined;
            if (state?.session && state.actor && state.collaboration) applyState(state as TeacherActorResponse);
            connected.value = !!state?.session;
            pending.value = undefined;
        } else { connected.value = false; }
        revokeIfForbidden(problem);
    } finally { busy.value = false; generation++; }
}
function navigate(stageId: string) { if (stageId !== session.value?.currentStageId) void send('stage', { stageId }); }
function detach(asTab = false) {
    detachError.value = false;
    if (!channel) { detachError.value = true; return; }
    activeInstance = crypto.randomUUID(); heartbeatAt = 0;
    const url = `${scope === 'grant' ? `/${props.locale}/conduct/${props.sessionId}/control` : `/${props.locale}/control/${props.sessionId}`}?instance=${activeInstance}`;
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
        returnTimeout = setTimeout(() => { location.assign(teacherUrl); }, 1000);
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
    returnTimeout = setTimeout(() => { location.assign(teacherUrl); }, 150);
}
function finish() { confirmFinish.value = true; }
</script>
<template>
    <div :class="['teacher-panel', { 'compact-control': compact }]">
        <div class="page-heading conducting-heading">
            <div class="conducting-title"><div class="conducting-title-row"><h1>{{ messages.teacher_panel }}</h1><span v-if="session" :class="['status-pill', 'session-state', session.status]" role="status"><span class="state-dot" aria-hidden="true"></span>{{ messages['session_' + session.status] }}</span></div><p class="lesson-subtitle">{{ session?.document.content.title ?? messages.loading }}</p></div>
            <div class="heading-actions">
                <button v-if="!detached && actor?.capabilities.includes('manageCollaboration') && session?.mode === 'lesson'" :disabled="manageDisabled || session.status === 'finished'" @click="collaborationOpen = !collaborationOpen"><StudioIcon name="account" />{{ messages.collab_invite }}</button>
                <button v-if="session?.status === 'prepared'" class="primary" :disabled="presentDisabled" @click="send('begin')">{{ messages.begin_session }}</button>
                <a v-if="session && session.mode !== 'rehearsal'" class="button-link projector-link" :href="projectorUrl" target="_blank" rel="noopener"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="13" rx="1" /><path d="M12 17v4m-4 0h8" /></svg>{{ messages.open_projector }} ↗</a>
            </div>
        </div>
        <p v-if="error" role="alert" class="error-banner">{{ error }}</p>
        <p v-if="accessLost" role="alert" class="error-banner">{{ messages.collab_access_lost }}</p>
        <p v-if="invitationReplayed" role="status" class="info-banner">{{ messages.collab_link_replayed }}</p>
        <div v-if="pending" class="info-banner" role="status">{{ pendingStale ? messages.collab_authority_changed : messages.command_pending }} <button v-if="pendingStale" :disabled="busy" @click="pending = undefined">{{ messages.collab_discard_pending }}</button><button v-else :disabled="busy" @click="retry">{{ messages.retry_command }}</button></div>
        <p v-if="!session && !accessLost" role="status">{{ messages.loading }}</p>
        <template v-if="session && stage">
            <RuntimeStatus :state="session" :messages="messages" hide-timer hide-status />
            <div class="join-strip studio-card conducting-strip">
                <div v-if="session.mode !== 'rehearsal'" class="join-details"><span class="participant-total"><svg aria-hidden="true" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="7" r="3" /><path d="M3 20v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 5v2" /></svg>{{ messages.participants }} <strong>{{ session.participants.length }}</strong></span><div class="join-code-group"><small>{{ messages.join_code }}</small><strong class="join-code">{{ session.joinCode }}</strong></div><details class="qr-details"><summary>{{ messages.show_qr }}</summary><div class="qr-popover"><img v-if="qr" :src="qr" :alt="messages.qr_alt" width="160" height="160" /><a :href="joinUrl" target="_blank" rel="noopener">{{ messages.student_join }} ↗</a></div></details></div>
                <div v-if="session.mode === 'rehearsal'" class="rehearsal-links"><span class="status-pill">{{ messages.rehearsal }}</span><a class="button-link" :href="`/${locale}/rehearsal/${session.id}/student`" target="_blank" rel="noopener">{{ messages.student_screen }} ↗</a><a class="button-link" :href="`/${locale}/rehearsal/${session.id}/projector`" target="_blank" rel="noopener">{{ messages.shared_screen }} ↗</a></div><div class="control-placement"><span :class="['connection-indicator', { offline: !connected }]" role="status"><span class="state-dot" aria-hidden="true"></span>{{ connected ? messages.connected : messages.reconnecting }}</span><template v-if="compact || detached"><button @click="restore">{{ messages.return_control }}</button></template><details v-else class="detach-options"><summary>{{ messages.control_placement }}</summary><div class="detach-choices"><button @click="detach()">{{ messages.detach_window }} ↗</button><button @click="detach(true)">{{ messages.detach_tab }} ↗</button></div></details></div>
            </div>
            <p v-if="detached" class="info-banner" role="status">{{ messages.control_detached }}</p>
            <p v-if="detachError" class="info-banner" role="status">{{ messages.detach_failed }} <button @click="detach(true)">{{ messages.detach_tab }}</button></p>
            <div :class="['teacher-layout', { 'stages-collapsed': !stagesOpen, detached, compact }]">
                <aside v-if="!detached" class="studio-card stage-list conducting-stages">
                    <div class="section-heading"><h2>{{ messages.stages }}</h2><button class="stage-collapse" :aria-label="stagesOpen ? messages.hide_stages : messages.show_stages" :aria-expanded="stagesOpen" aria-controls="conducting-stages" @click="stagesOpen = !stagesOpen">{{ stagesOpen ? '−' : '+' }}</button></div><p class="stage-progress">{{ index + 1 }} / {{ session.document.stages.length }}</p>
                    <div v-if="stagesOpen" id="conducting-stages"><button v-for="(item, number) in session.document.stages" :key="item.id" :disabled="presentDisabled" :class="['stage-select', { active: item.id === session.currentStageId }]" :aria-current="item.id === session.currentStageId ? 'step' : undefined" @click="navigate(item.id)"><span class="stage-number">{{ number + 1 }}</span><img v-if="stageImage(item)" :src="stageImage(item)" alt="" class="stage-thumbnail" /><span v-else class="stage-type-symbol" aria-hidden="true"><StudioIcon :name="stageSymbol(item)" /></span><strong>{{ item.content.title }}</strong></button></div>
                </aside>
                <div class="teacher-main">
                    <div v-if="!compact" class="studio-card screen-preview">
                        <div class="section-heading preview-heading"><h2>{{ messages.shared_screen }}</h2><span v-if="stage.blocks.some(block => isInteractive(block.type))" class="answer-count">{{ messages.answered }} {{ answeredParticipants }} / {{ session.participants.length }}</span></div>
                        <div class="preview-content" tabindex="0" role="region" :aria-label="messages.shared_screen"><StageRenderer :stage="session.publicStage" :messages="messages" /></div>
                        <div v-if="!detached" class="navigation-controls"><button :disabled="presentDisabled || index === 0" @click="navigate(session.document.stages[index - 1]!.id)">← {{ messages.previous }}</button><button class="primary" :disabled="presentDisabled || index === session.document.stages.length - 1" @click="navigate(session.document.stages[index + 1]!.id)">{{ messages.next }} →</button><a class="button-link answers-link" href="#conducting-answers">{{ messages.student_answers }} ↓</a></div>
                    </div>
                    <div v-if="compact" class="studio-card navigation-controls"><button :disabled="presentDisabled || index === 0" @click="navigate(session.document.stages[index - 1]!.id)">← {{ messages.previous }}</button><span>{{ index + 1 }} / {{ session.document.stages.length }}</span><button class="primary" :disabled="presentDisabled || index === session.document.stages.length - 1" @click="navigate(session.document.stages[index + 1]!.id)">{{ messages.next }} →</button></div>
                </div>
                <aside v-if="!detached" class="teacher-tools">

                    <section class="studio-card conducting-controls"><h2>{{ messages.timer }}</h2><RuntimeTimer :state="session" :messages="messages" prominent />
                        <div class="timer-buttons"><button v-if="session.timer.status === 'running'" class="round-button primary" :aria-label="messages.pause_timer" :title="messages.pause_timer" :disabled="presentDisabled" @click="send('timer.pause')"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="5" width="4" height="14" rx="1" /><rect x="14" y="5" width="4" height="14" rx="1" /></svg></button><button v-if="session.timer.status === 'paused'" class="round-button primary" :aria-label="messages.resume_timer" :title="messages.resume_timer" :disabled="presentDisabled || session.status !== 'running'" @click="send('timer.resume')"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="m8 5 11 7-11 7z" /></svg></button><button v-if="session.timer.status !== 'idle'" class="round-button" :aria-label="messages.clear_timer" :title="messages.clear_timer" :disabled="presentDisabled" @click="send('timer.clear')"><svg aria-hidden="true" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m7 7 10 10m0-10L7 17" /></svg></button></div>
                        <details class="timer-settings" :open="session.timer.status === 'idle'"><summary>{{ messages.set_timer }}</summary><form class="timer-form" @submit.prevent="send('timer.start', { seconds: Number(seconds) })"><label>{{ messages.timer_seconds }}<input v-model="seconds" type="number" required min="1" max="7200" :disabled="presentDisabled" /></label><button :disabled="presentDisabled || session.status !== 'running'">{{ messages.start_timer }}</button></form><p class="field-hint">{{ messages.timer_hint }}</p></details>
                    </section>
                    <TeacherBlockTools :session="session" :stage="stage" :messages="messages" :disabled="presentDisabled" :moderation-disabled="moderationDisabled" @command="send" />
                    <section class="studio-card quick-actions"><h2>{{ messages.quick_actions }}</h2><button class="wave-button" :disabled="presentDisabled" @click="send('wave')"><StudioIcon name="heart" />{{ messages.send_wave }}</button>
                        <details class="message-action"><summary><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4h16v12H9l-5 4z" /><path d="M8 8h8M8 12h5" /></svg>{{ messages.screen_message }}</summary><form @submit.prevent="send('message.set', { text: message })"><label>{{ messages.screen_message }}<textarea v-model="message" maxlength="1000" rows="2" :disabled="presentDisabled" required /></label><div class="action-row"><button :disabled="presentDisabled || !message.trim()">{{ messages.show_message }}</button><button type="button" :disabled="presentDisabled || !session.message" @click="send('message.clear')">{{ messages.clear_message }}</button></div></form></details>
                        <button v-if="session.status === 'running'" class="session-action" :disabled="presentDisabled" @click="send('pause')"><span aria-hidden="true">Ⅱ</span>{{ messages.pause_session }}</button><button v-if="session.status === 'paused'" class="session-action primary" :disabled="presentDisabled" @click="send('resume')"><span aria-hidden="true">▶</span>{{ messages.resume_session }}</button>
                        <button v-if="actor?.capabilities.includes('finish') && session.status !== 'finished' && !confirmFinish" class="finish-link" :disabled="disabled" @click="finish">{{ messages.finish_session }}</button><div v-if="actor?.capabilities.includes('finish') && confirmFinish && session.status !== 'finished'" role="alert" class="info-banner finish-confirmation"><p>{{ messages.finish_confirm }}</p><div class="action-row"><button class="primary" :disabled="disabled" @click="confirmFinish = false; send('finish')">{{ messages.finish_yes }}</button><button @click="confirmFinish = false">{{ messages.cancel }}</button></div></div>
                    </section>
                    <CollaborationPanel v-if="session.mode === 'lesson' && actor && collaboration" :session-id="sessionId" :locale="locale" :messages="messages" :actor="actor" :collaboration="collaboration" :status="session.status" :server-now="session.serverNow" :disabled="manageDisabled" :invitation="invitation" :expanded="collaborationOpen" @command="send" />
                </aside>
                <div v-if="!detached" class="teacher-bottom">
                    <TeacherAnswers :session="session" :stage="stage" :answers="stageAnswers" :messages="messages" :disabled="moderationDisabled" @command="send" />
                    <div class="teacher-private"><details v-for="block in stage.blocks.filter(block => block.teacherNotes)" :key="block.id" class="studio-card teacher-notes"><summary>{{ messages.block_notes }} · {{ block.content.title ?? block.content.question ?? messages.text }}</summary><p class="plain-text">{{ block.teacherNotes }}</p></details><details v-if="stage.content.notes" class="studio-card teacher-notes" :open="!compact"><summary>{{ messages.notes }}</summary><p class="plain-text">{{ stage.content.notes }}</p></details><details v-if="solutions.length" class="studio-card teacher-solutions"><summary>{{ messages.correct_answers }}</summary><dl><div v-for="solution in solutions" :key="solution.blockId"><dt>{{ solution.question }}</dt><dd>{{ solution.answer }}</dd></div></dl></details></div>
                </div>
            </div>
        </template>
    </div>
</template>
