<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { acceptProjection } from './runtime';
import { api, ApiError, errorMessage, poll } from './api';
import StageRenderer from './StageRenderer.vue';
import FinishedLesson from './FinishedLesson.vue';
import RuntimeStatus from './RuntimeStatus.vue';
import type { AnswerValue, Messages, PublicState } from './types';
import { canAnswerPublicSession } from './public-session';
import '../../css/public-lesson-app.css';
const props = defineProps<{ mode: 'student' | 'projector'; sessionId?: string; projectorToken?: string; rehearsal?: boolean; teacherScoped?: boolean; messages: Messages }>();
const session = ref<PublicState>();
const error = ref('');
const connected = ref(false);
const busy = ref(false);
const accessLost = ref(false);
const canvas = ref<HTMLElement>();
watch(() => session.value?.currentStageId, async () => {
    await nextTick();
    const copy = canvas.value?.querySelector('.focus-stage-copy');
    if (copy) copy.scrollTop = 0;
    if (canvas.value) canvas.value.scrollTop = 0;
});
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
    if (!session.value || !canAnswerPublicSession(props.mode, session.value.status, connected.value, busy.value, session.value.stage.blocks.find(block => block.id === blockId)?.type)) return;
    busy.value = true; generation++; error.value = '';
    try {
        session.value = (await api<{ session: PublicState }>(props.rehearsal ? `/api/studio/rehearsals/${props.sessionId}/answers` : `${endpoint}/answers`, 'POST', { stageId: session.value.currentStageId, blockId, value })).session;
        connected.value = true;
    } catch (problem) { connected.value = false; error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; generation++; }
}
</script>
<template>
    <div :class="['public-session', 'public-lesson-app', 'conducting-app', mode]">
        <div v-if="session?.status !== 'finished'" class="public-lesson-status">
            <span class="public-lesson-mode">{{ mode === 'student' ? messages.student_screen : messages.shared_screen }}</span>
            <strong v-if="session" class="public-lesson-stage-title">{{ session.stage.content.title }}</strong>
            <span v-if="mode === 'student' && typeof session?.kindnessPoints === 'number'" class="public-lesson-points" role="status" :aria-label="messages.kindness_points"><span aria-hidden="true">✦</span> {{ session.kindnessPoints }}</span>
            <span :class="['public-lesson-connection', { disconnected: !connected }]" role="status"><span aria-hidden="true">●</span> {{ connected ? messages.connected : messages.reconnecting }}</span>
            <RuntimeStatus v-if="session" :state="session" :messages="messages" :hide-status="session.status === 'running'" />
        </div>
        <p v-if="rehearsal" class="info-banner public-lesson-notice">{{ messages.rehearsal_preview_hint }}</p>
        <p v-if="error" role="alert" class="error-banner public-lesson-notice">{{ error }}</p>
        <div ref="canvas" class="public-lesson-canvas">
            <p v-if="!session && !accessLost" class="public-lesson-loading" role="status">{{ messages.loading }}</p>
            <FinishedLesson v-if="session?.status === 'finished'" :block="session.closing" :messages="messages" :return-url="`/${session.locale}/catalog`" />
            <StageRenderer v-else-if="session" :key="session.currentStageId" :stage="session.stage" :messages="messages" :focus="true" :interactive="mode === 'student'" :answers="session.ownAnswers" :prepared="session.status === 'prepared'" :busy="mode !== 'student' || !connected || busy || !['prepared', 'running'].includes(session.status)" @answer="answer" />
        </div>
    </div>
</template>
