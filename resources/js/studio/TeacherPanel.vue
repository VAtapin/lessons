<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import QRCode from 'qrcode';
import { api, ApiError, errorMessage, poll } from './api';
import { connectedControl, acceptProjection, matchesControlMessage, controlReturnConfirmed } from './runtime';
import { canCommand, captureTeacherCommand, controlChannel, localInterfaceUrl, recoverTeacherCommand, sameTeacherAuthority, type TeacherPending } from './collaboration';
import CollaborationPanel from './CollaborationPanel.vue';
import '../../css/collaboration.css';
import TeacherBlockTools from './TeacherBlockTools.vue';
import TeacherAnswers from './TeacherAnswers.vue';
import LiveAnswerCard from './LiveAnswerCard.vue';
import { liveAnswer, reviewAndPublish } from './live-answer';
import LessonDocumentation from './LessonDocumentation.vue';
import { isInteractive, valueText } from './interactive';
import StageRenderer from './StageRenderer.vue';
import RuntimeStatus from './RuntimeStatus.vue';
import RuntimeTimer from './RuntimeTimer.vue';
import StudioIcon from './StudioIcon.vue';
import { activeStageAnswers, countAnsweredParticipants } from './conducting';
import { keyboardStageIndex } from './conducting-keyboard';
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
const panelOpen = ref<'stages' | 'tools' | 'answers' | 'notes' | 'finish' | 'leave' | null>(null);
const toolsOpen = ref(!props.compact);
let panelOpener: HTMLElement | undefined;
async function openPanel(panel: typeof panelOpen.value, event?: Event) { panelOpener = event?.currentTarget instanceof HTMLElement ? event.currentTarget : undefined; if (panel === 'tools') { toolsOpen.value = !toolsOpen.value; return; } panelOpen.value = panel; await nextTick(); document.getElementById('focus-panel-close')?.focus(); }
async function closePanel() { panelOpen.value = null; await nextTick(); if (panelOpener?.isConnected) panelOpener.focus(); else document.querySelector<HTMLElement>('.focus-control-toggle, .focus-current')?.focus(); }
function trapPanel(event: KeyboardEvent) {
    if (event.key === 'Escape') { event.preventDefault(); void closePanel(); return; }
    if (event.key !== 'Tab') return;
    const nodes = Array.from((event.currentTarget as HTMLElement).querySelectorAll<HTMLElement>('a[href],button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),summary,[tabindex="0"]')).filter(node => node.getClientRects().length);
    const first = nodes[0], last = nodes.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
}
const seconds = ref(60);
const message = ref('');
const qr = ref('');
const detached = ref(false);
const fullscreenAvailable = document.fullscreenEnabled;
const fullScreen = ref(!!document.fullscreenElement);
function fullscreenChanged() { fullScreen.value = !!document.fullscreenElement; }
document.addEventListener('fullscreenchange', fullscreenChanged);
onBeforeUnmount(() => document.removeEventListener('fullscreenchange', fullscreenChanged));
async function toggleFullscreen() {
    try { if (document.fullscreenElement) await document.exitFullscreen(); else await document.querySelector<HTMLElement>('.conducting-app')?.requestFullscreen(); }
    catch { error.value = props.messages.error_fullscreen; }
}
watch(detached, value => { if (value) panelOpen.value = null; });
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
const dismissedAnswers = ref(new Set<string>());
const noticedId = ref<number>();
const freshAnswer = computed(() => {
    const candidates = stageAnswers.value.filter(answer => !dismissedAnswers.value.has(`${answer.id}:${answer.revision}`) && (answer.moderation?.status === 'pending' || (answer.value.question && !answer.acknowledged)));
    return candidates.find(answer => answer.id === noticedId.value) ?? liveAnswer(candidates, dismissedAnswers.value);
});
watch(freshAnswer, answer => { noticedId.value = answer?.id; });
function dismissAnswer() { if (freshAnswer.value) dismissedAnswers.value = new Set([...dismissedAnswers.value, `${freshAnswer.value.id}:${freshAnswer.value.revision}`]); }
async function publishFreshAnswer() { if (freshAnswer.value && stage.value) await reviewAndPublish(freshAnswer.value, stage.value.id, () => session.value, send); }
const copied = ref(false);
const joinField = ref<HTMLInputElement>();
async function copyJoinLink() {
    copied.value = false;
    if (!joinUrl.value) return;
    try { await navigator.clipboard.writeText(joinUrl.value); copied.value = true; }
    catch { joinField.value?.focus(); joinField.value?.select(); copied.value = document.execCommand('copy'); if (!copied.value) error.value = props.messages.copy_link_manual; }
}
const returnUrl = `/${props.locale}/${scope === 'grant' ? 'catalog' : 'studio?view=overview'}`;
let leaving = false;
function warnBeforeLeave(event: BeforeUnloadEvent) {
    if (!leaving && !props.compact && !accessLost.value && session.value && session.value.status !== 'finished') { event.preventDefault(); event.returnValue = ''; }
}
window.addEventListener('beforeunload', warnBeforeLeave);
onBeforeUnmount(() => window.removeEventListener('beforeunload', warnBeforeLeave));
function leaveLesson() { leaving = true; location.assign(returnUrl); }
const stageImage = (item: ProjectedStage) => scope === 'grant' && item.id !== session.value?.currentStageId ? undefined : item.blocks.find(block => block.resources?.image)?.resources?.image;
const stageSymbol = (item: ProjectedStage) => item.blocks.some(block => isInteractive(block.type) || block.type === 'core.prompt') ? 'question' : item.blocks.some(block => block.type === 'core.image') ? 'media' : 'text';
const disabled = computed(() => busy.value || !!pending.value || !connected.value || accessLost.value || session.value?.status === 'finished');
const presentDisabled = computed(() => disabled.value || !actor.value?.capabilities.includes('present'));
const moderationDisabled = computed(() => disabled.value || !actor.value?.capabilities.includes('moderate'));
const manageDisabled = computed(() => busy.value || !!pending.value || !connected.value || accessLost.value || !actor.value?.capabilities.includes('manageCollaboration'));
const pendingStale = computed(() => !!pending.value && !sameTeacherAuthority(pending.value, actor.value, collaboration.value?.controlEpoch ?? -1));
const detachDisabled = computed(() => busy.value || !!pending.value);
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
    return await retry();
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
        return true;
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
function keyboardNavigate(event: KeyboardEvent) {
    const target = event.target instanceof HTMLElement ? event.target : undefined;
    const blocked = presentDisabled.value || detached.value || panelOpen.value !== null || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || event.repeat || !!target?.closest('input,textarea,select,[contenteditable="true"],[role="textbox"]');
    const next = keyboardStageIndex(event.key, index.value, session.value?.document.stages.length ?? 0, blocked);
    if (next === undefined) return;
    event.preventDefault(); navigate(session.value!.document.stages[next]!.id);
}
window.addEventListener('keydown', keyboardNavigate);
onBeforeUnmount(() => window.removeEventListener('keydown', keyboardNavigate));
function detach(asTab = false) {
    if (detachDisabled.value) return;
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
    <div :class="['teacher-panel', 'conducting-app', { 'compact-control': compact, 'focus-detached': detached, 'has-embedded-tools': !compact && !detached && toolsOpen }]">
        <div v-if="error || accessLost || (pending && !busy) || invitationReplayed || detachError" class="focus-notifications">
            <p v-if="error && !accessLost" role="alert" class="error-banner">{{ error }}</p>
            <p v-if="accessLost" role="alert" class="error-banner">{{ messages.collab_access_lost }}</p>
            <p v-if="invitationReplayed" role="status" class="info-banner">{{ messages.collab_link_replayed }}</p>
            <div v-if="pending && !busy" class="info-banner" role="status">{{ pendingStale ? messages.collab_authority_changed : messages.command_pending }} <button v-if="pendingStale" :disabled="busy" @click="pending = undefined">{{ messages.collab_discard_pending }}</button><button v-else :disabled="busy" @click="retry">{{ messages.retry_command }}</button></div>
            <p v-if="detachError" class="info-banner" role="status">{{ messages.detach_failed }} <button :disabled="detachDisabled" @click="detach(true)">{{ messages.detach_tab }}</button></p>
        </div>
        <template v-if="session && stage">
            <main v-if="!compact" class="conducting-canvas" :inert="panelOpen !== null ? true : undefined" :aria-label="messages.shared_screen">
                <RuntimeStatus :state="session" :messages="messages" hide-timer hide-status />
                <StageRenderer :key="stage.id" :stage="session.publicStage" :messages="messages" :presenter="!detached" :busy="presentDisabled" focus @command="send" />
                <LiveAnswerCard v-if="freshAnswer && !detached && !panelOpen && actor?.capabilities.includes('moderate') && session.status !== 'finished'" :key="freshAnswer.id" :answer="freshAnswer" :name="session.participants.find(participant => participant.id === freshAnswer?.participantId)?.name ?? ''" :messages="messages" :disabled="moderationDisabled" :submit-command="send" @publish="publishFreshAnswer" @dismiss="dismissAnswer" @edit="openPanel('answers', $event)" @command="send" />
            </main>
            <button v-if="panelOpen" class="focus-backdrop" tabindex="-1" :aria-label="messages.focus_close_panel" @click="closePanel"></button>
            <aside v-if="panelOpen === 'stages'" class="focus-drawer focus-stages" role="dialog" aria-modal="true" :aria-label="messages.stages" @keydown="trapPanel">
                <div class="focus-panel-heading"><h2>{{ messages.stages }}</h2><button id="focus-panel-close" :aria-label="messages.focus_close_panel" @click="closePanel"><StudioIcon name="close" /></button></div>
                <p class="stage-progress">{{ index + 1 }} / {{ session.document.stages.length }}</p>
                <div id="conducting-stages"><button v-for="(item, number) in session.document.stages" :key="item.id" :disabled="presentDisabled" :class="['stage-select', { active: item.id === session.currentStageId }]" :aria-current="item.id === session.currentStageId ? 'step' : undefined" @click="navigate(item.id); closePanel()"><span class="stage-number">{{ number + 1 }}</span><img v-if="stageImage(item)" :src="stageImage(item)" alt="" class="stage-thumbnail" /><span v-else class="stage-type-symbol" aria-hidden="true"><StudioIcon :name="stageSymbol(item)" /></span><strong>{{ item.content.title }}</strong></button></div>
            </aside>
            <aside v-if="!detached && (compact || toolsOpen)" id="embedded-tools" :class="['focus-tools', compact ? 'focus-control-body' : 'embedded-control']" :aria-label="messages.teacher_panel" :inert="panelOpen !== null ? true : undefined">
                <div v-if="!compact" class="focus-panel-heading"><h2>{{ messages.teacher_panel }}</h2><button :aria-label="messages.focus_close_panel" @click="toolsOpen = false"><StudioIcon name="close" /></button></div>
                <div class="focus-session-summary"><span :class="['status-pill', 'session-state', session.status]">{{ messages['session_' + session.status] }}</span><span :class="['connection-indicator', { offline: !connected }]" role="status"><span class="state-dot" aria-hidden="true"></span>{{ connected ? messages.connected : messages.reconnecting }}</span></div>
                <LiveAnswerCard v-if="compact && freshAnswer && actor?.capabilities.includes('moderate') && session.status !== 'finished'" :key="freshAnswer.id" :answer="freshAnswer" :name="session.participants.find(participant => participant.id === freshAnswer?.participantId)?.name ?? ''" :messages="messages" :disabled="moderationDisabled" :submit-command="send" @publish="publishFreshAnswer" @dismiss="dismissAnswer" @edit="openPanel('answers')" @command="send" />
                <section class="focus-join">
                    <div class="focus-class-count"><span>{{ messages.participants }} <strong>{{ session.participants.length }}</strong></span><span>{{ messages.answered }} <strong>{{ answeredParticipants }}</strong></span></div>
                    <details v-if="session.mode !== 'rehearsal'" :open="session.status === 'prepared'"><summary>{{ messages.join_code }} <strong class="join-code">{{ session.joinCode }}</strong> · {{ messages.show_qr }}</summary><img v-if="qr" :src="qr" :alt="messages.qr_alt" width="160" height="160" /><a :href="joinUrl" target="_blank" rel="noopener">{{ messages.student_join }} ↗</a></details>
                    <div v-if="session.mode !== 'rehearsal'" class="join-copy-row"><input ref="joinField" :aria-label="messages.student_join" :value="joinUrl" readonly @click="($event.target as HTMLInputElement).select()" /><button type="button" @click="copyJoinLink">{{ messages[copied ? 'copied_link' : 'copy_link'] }}</button></div>
                    <div v-else class="rehearsal-links"><span class="status-pill">{{ messages.rehearsal }}</span><a :href="`/${locale}/rehearsal/${session.id}/student`" target="_blank" rel="noopener">{{ messages.student_screen }} ↗</a><a :href="`/${locale}/rehearsal/${session.id}/projector`" target="_blank" rel="noopener">{{ messages.shared_screen }} ↗</a></div>
                    <a v-if="session.mode !== 'rehearsal'" :href="projectorUrl" target="_blank" rel="noopener">{{ messages.open_projector }} ↗</a>
                </section>


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

                <div class="focus-tools-links"><button @click="openPanel('answers', $event)">{{ messages.student_answers }} · {{ stageAnswers.length }}</button><button @click="openPanel('notes', $event)">{{ messages.notes }}</button><button @click="openPanel('stages', $event)">{{ messages.stages }}</button></div>
            </aside>
            <aside v-if="panelOpen === 'finish'" class="focus-drawer focus-detail" role="dialog" aria-modal="true" :aria-label="messages.finish_session" @keydown="trapPanel"><div class="focus-panel-heading"><h2>{{ messages.finish_session }}</h2><button id="focus-panel-close" :aria-label="messages.focus_close_panel" @click="confirmFinish = false; closePanel()"><StudioIcon name="close" /></button></div><p>{{ messages.finish_confirm }}</p><div class="action-row"><button class="primary" :disabled="disabled || !actor?.capabilities.includes('finish')" @click="confirmFinish = false; closePanel(); send('finish')">{{ messages.finish_yes }}</button><button @click="confirmFinish = false; closePanel()">{{ messages.cancel }}</button></div></aside><aside v-if="panelOpen === 'answers' || panelOpen === 'notes'" class="focus-drawer focus-detail" role="dialog" aria-modal="true" :aria-label="panelOpen === 'answers' ? messages.student_answers : messages.notes" @keydown="trapPanel">
                <div class="focus-panel-heading"><h2>{{ panelOpen === 'answers' ? messages.student_answers : messages.notes }}</h2><button id="focus-panel-close" :aria-label="messages.focus_close_panel" @click="closePanel"><StudioIcon name="close" /></button></div>
                <TeacherAnswers v-if="panelOpen === 'answers'" :session="session" :stage="stage" :answers="stageAnswers" :messages="messages" :disabled="moderationDisabled" @command="send" />
                <div v-else class="teacher-private"><LessonDocumentation v-if="session.document.documentation" :documentation="session.document.documentation" :messages="messages" /><details v-for="block in stage.blocks.filter(block => block.teacherNotes)" :key="block.id" class="studio-card teacher-notes" open><summary>{{ messages.block_notes }} · {{ block.content.title ?? block.content.question ?? messages.text }}</summary><p class="plain-text">{{ block.teacherNotes }}</p></details><details v-if="stage.content.notes" class="studio-card teacher-notes" open><summary>{{ messages.notes }}</summary><p class="plain-text">{{ stage.content.notes }}</p></details><details v-if="solutions.length" class="studio-card teacher-solutions"><summary>{{ messages.correct_answers }}</summary><dl><div v-for="solution in solutions" :key="solution.blockId"><dt>{{ solution.question }}</dt><dd>{{ solution.answer }}</dd></div></dl></details></div>
            </aside>
            <aside v-if="panelOpen === 'leave'" class="focus-drawer focus-detail" role="dialog" aria-modal="true" :aria-label="messages.leave_lesson_title" @keydown="trapPanel"><div class="focus-panel-heading"><h2>{{ messages.leave_lesson_title }}</h2><button id="focus-panel-close" :aria-label="messages.focus_close_panel" @click="closePanel"><StudioIcon name="close" /></button></div><p class="plain-text">{{ messages.leave_lesson_hint }}</p><p v-if="session.joinCode">{{ messages.join_code }}: <strong>{{ session.joinCode }}</strong></p><div class="action-row"><button class="primary" @click="closePanel">{{ messages.stay_in_lesson }}</button><button :disabled="busy" @click="leaveLesson">{{ messages.leave_keep_session }}</button></div></aside>
            <nav class="focus-dock" :aria-label="messages.teacher_panel" :inert="panelOpen !== null ? true : undefined">
                <a class="focus-return" :href="returnUrl" :title="messages.focus_return" :aria-label="messages.focus_return" @click.prevent="session.status === 'finished' ? leaveLesson() : openPanel('leave', $event)">↖</a>
                <template v-if="!detached">
                    <button class="focus-previous" :disabled="presentDisabled || index === 0" :aria-label="messages.previous" :title="messages.previous" @click="navigate(session.document.stages[index - 1]!.id)">←</button>
                    <button class="focus-current" :aria-label="messages.stages" aria-haspopup="dialog" @click="openPanel('stages', $event)"><span>{{ index + 1 }} / {{ session.document.stages.length }}</span><strong>{{ stage.content.title }}</strong></button>
                    <button v-if="index < session.document.stages.length - 1" class="primary focus-next" :disabled="presentDisabled" :aria-label="messages.next" :title="messages.next" @click="navigate(session.document.stages[index + 1]!.id)">→</button><button v-else-if="actor?.capabilities.includes('finish') && session.status !== 'finished'" class="primary focus-next" :disabled="disabled" @click="finish(); openPanel('finish', $event)">{{ messages.finish_session }}</button>
                    <button v-if="session.status === 'prepared'" class="primary focus-begin" :disabled="presentDisabled" @click="send('begin')">{{ messages.begin_session }}</button>
                    <RuntimeTimer v-if="session.timer.status !== 'idle'" :state="session" :messages="messages" />
                    <button v-if="!compact" class="focus-control-toggle" :aria-label="messages.teacher_panel" :title="messages.teacher_panel" :aria-expanded="toolsOpen" aria-controls="embedded-tools" @click="openPanel('tools', $event)"><StudioIcon name="menu" /><span>{{ messages.focus_controls }}</span></button>
                    <button v-else class="focus-answers-toggle" :aria-label="messages.student_answers" :title="messages.student_answers" aria-haspopup="dialog" @click="openPanel('answers', $event)"><StudioIcon name="question" /><span>{{ stageAnswers.length }}</span></button>
                </template>
                <span v-else class="focus-detached-stage">{{ index + 1 }} / {{ session.document.stages.length }} · {{ stage.content.title }}</span>
                <div class="focus-window-actions"><button v-if="!compact && fullscreenAvailable" :aria-label="messages.focus_fullscreen" :title="messages.focus_fullscreen" :aria-pressed="fullScreen" @click="toggleFullscreen">⛶</button><button v-if="compact || detached" :title="messages.return_control" @click="restore"><StudioIcon name="screen" /><span>{{ messages.return_control }}</span></button><template v-else><button class="focus-detach-window" :disabled="detachDisabled" :title="messages.detach_window" @click="detach()"><StudioIcon name="screen" /><span>{{ messages.focus_window }}</span></button><button class="focus-detach-tab" :disabled="detachDisabled" :aria-label="messages.detach_tab" :title="messages.detach_tab" @click="detach(true)">↗</button></template></div>
            </nav>
        </template>
        <div v-else class="focus-waiting"><p role="status">{{ accessLost ? messages.collab_access_ended : messages.loading }}</p><a :href="`/${locale}/catalog`">{{ messages.focus_return }}</a></div>
    </div>
</template>
