<script setup lang="ts">
import { ref } from 'vue';
import { acceptProjection } from './runtime';
import { api, ApiError, errorMessage, poll } from './api';
import StageRenderer from './StageRenderer.vue';
import RuntimeStatus from './RuntimeStatus.vue';
import type { AnswerValue, Messages, PublicState } from './types';
const props = defineProps<{ mode: 'student' | 'projector'; sessionId?: string; projectorToken?: string; rehearsal?: boolean; teacherScoped?: boolean; messages: Messages }>();
const session = ref<PublicState>();
const error = ref('');
const connected = ref(false);
const busy = ref(false);
const accessLost = ref(false);
let generation = 0;
const endpoint = props.teacherScoped ? `/api/conduct/sessions/${props.sessionId}/projection` : props.rehearsal ? `/api/studio/rehearsals/${props.sessionId}/preview/${props.mode}` : props.mode === 'student' ? `/api/participation/${props.sessionId}` : `/api/projection/${props.projectorToken}`;
poll(async signal => {
    if (accessLost.value) return;
    const started = generation;
    try {
        const response = await api<{ session: PublicState }>(endpoint, 'GET', undefined, signal);
        if (acceptProjection(started, generation, busy.value, session.value?.revision, response.session.revision)) { session.value = response.session; connected.value = true; error.value = ''; }
    } catch (problem) { if (started === generation && !busy.value) throw problem; }
}, problem => { connected.value = false; error.value = errorMessage(problem, props.messages); if (props.teacherScoped && problem instanceof ApiError && [401, 403, 404, 410, 419].includes(problem.status)) { accessLost.value = true; session.value = undefined; error.value = props.messages.collab_access_lost ?? error.value; } });
async function answer(blockId: string, value: AnswerValue) {
    if (!session.value || !connected.value || busy.value || props.mode !== 'student' || session.value.status !== 'running') return;
    busy.value = true; generation++; error.value = '';
    try {
        session.value = (await api<{ session: PublicState }>(props.rehearsal ? `/api/studio/rehearsals/${props.sessionId}/answers` : `${endpoint}/answers`, 'POST', { stageId: session.value.currentStageId, blockId, value })).session;
        connected.value = true;
    } catch (problem) { connected.value = false; error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; generation++; }
}
</script>
<template>
    <div :class="['public-session', mode]">
        <p v-if="rehearsal" class="info-banner">{{ messages.rehearsal_preview_hint }}</p>
        <p class="eyebrow">{{ mode === 'student' ? messages.student_screen : messages.shared_screen }}</p>
        <p class="field-hint" role="status">{{ connected ? messages.connected : messages.reconnecting }}</p>
        <p v-if="error" role="alert" class="error-banner">{{ error }}</p>
        <p v-if="!session && !accessLost" role="status">{{ messages.loading }}</p>
        <RuntimeStatus v-if="session" :state="session" :messages="messages" />
        <div v-if="session" class="studio-card"><StageRenderer :stage="session.stage" :messages="messages" :interactive="mode === 'student'" :answers="session.ownAnswers" :busy="busy || !connected || session.status !== 'running'" @answer="answer" /></div>
    </div>
</template>
