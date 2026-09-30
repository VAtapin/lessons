<script setup lang="ts">
import { ref } from 'vue';
import { api, errorMessage, poll } from './api';
import StageRenderer from './StageRenderer.vue';
import type { Messages, PublicState } from './types';
const props = defineProps<{ mode: 'student' | 'projector'; sessionId?: string; projectorToken?: string; messages: Messages }>();
const session = ref<PublicState>();
const error = ref('');
const busy = ref(false);
let generation = 0;
const endpoint = props.mode === 'student' ? `/api/participation/${props.sessionId}` : `/api/projection/${props.projectorToken}`;
poll(async signal => {
    const started = generation;
    try {
        const response = await api<{ session: PublicState }>(endpoint, 'GET', undefined, signal);
        if (started === generation && !busy.value && (!session.value || response.session.revision >= session.value.revision)) { session.value = response.session; error.value = ''; }
    } catch (problem) { if (started === generation && !busy.value) throw problem; }
}, problem => { error.value = errorMessage(problem, props.messages); });
async function answer(blockId: string, optionId: string) {
    if (!session.value || busy.value || props.mode !== 'student') return;
    busy.value = true; generation++; error.value = '';
    try {
        session.value = (await api<{ session: PublicState }>(`${endpoint}/answers`, 'POST', { stageId: session.value.currentStageId, blockId, optionId })).session;
    } catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; generation++; }
}
</script>
<template>
    <div :class="['public-session', mode]">
        <p class="eyebrow">{{ mode === 'student' ? messages.student_screen : messages.shared_screen }}</p>
        <p v-if="error" role="alert" class="error-banner">{{ error }}</p>
        <p v-if="!session" role="status">{{ messages.loading }}</p>
        <div v-if="session" class="studio-card"><StageRenderer :stage="session.stage" :messages="messages" :interactive="mode === 'student'" :answers="session.ownAnswers" :busy="busy" @answer="answer" /></div>
    </div>
</template>
