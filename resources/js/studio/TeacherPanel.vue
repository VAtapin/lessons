<script setup lang="ts">
import { computed, ref } from 'vue';
import { api, errorMessage, poll } from './api';
import StageRenderer from './StageRenderer.vue';
import type { Messages, TeacherState } from './types';
const props = defineProps<{ sessionId: string; locale: string; messages: Messages }>();
const session = ref<TeacherState>();
const error = ref('');
const busy = ref(false);
let generation = 0;
const stage = computed(() => session.value?.document.stages.find(item => item.id === session.value?.currentStageId));
const index = computed(() => session.value?.document.stages.findIndex(item => item.id === session.value?.currentStageId) ?? 0);
const solutions = computed(() => stage.value?.blocks.flatMap(block => {
    if (block.type !== 'core.single-choice' || !block.solution) return [];
    const option = block.content.options?.find(item => item.optionId === block.solution!.optionId);
    return option ? [{ blockId: block.id, question: block.content.question, answer: option.text }] : [];
}) ?? []);
poll(async signal => {
    const started = generation;
    try {
        const response = await api<{ session: TeacherState }>(`/api/studio/sessions/${props.sessionId}`, 'GET', undefined, signal);
        if (started === generation && !busy.value && (!session.value || response.session.revision >= session.value.revision)) { session.value = response.session; error.value = ''; }
    } catch (problem) { if (started === generation && !busy.value) throw problem; }
}, problem => { error.value = errorMessage(problem, props.messages); });
async function navigate(stageId: string) {
    if (!session.value || busy.value || stageId === session.value.currentStageId) return;
    busy.value = true; generation++; error.value = '';
    try { session.value = (await api<{ session: TeacherState }>(`/api/studio/sessions/${props.sessionId}/stage`, 'POST', { expectedRevision: session.value.revision, stageId })).session; }
    catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; generation++; }
}
function answerText(blockId: string, optionId: string) {
    const block = session.value?.document.stages.flatMap(item => item.blocks).find(item => item.id === blockId);
    return block?.content.options?.find(option => option.optionId === optionId)?.text ?? optionId;
}
</script>
<template>
    <div class="page-heading"><div><p class="eyebrow">{{ messages.teacher_panel }}</p><h1>{{ session?.document.content.title ?? messages.loading }}</h1></div><span class="status-pill">{{ messages.live_session }}</span></div>
    <p v-if="error" role="alert" class="error-banner">{{ error }}</p>
    <p v-if="!session" role="status">{{ messages.loading }}</p>
    <template v-if="session && stage">
        <div class="join-strip studio-card"><div><small>{{ messages.join_code }}</small><strong class="join-code">{{ session.joinCode }}</strong><a :href="`/${locale}/join`" target="_blank" rel="noopener">{{ messages.student_join }} ↗</a></div><a class="button-link" :href="session.projectorUrl" target="_blank" rel="noopener">{{ messages.open_projector }} ↗</a></div>
        <div class="teacher-layout">
            <aside class="studio-card stage-list"><h2>{{ messages.stages }}</h2><button v-for="(item, number) in session.document.stages" :key="item.id" :disabled="busy" :class="['stage-select', { active: item.id === session.currentStageId }]" :aria-current="item.id === session.currentStageId ? 'step' : undefined" @click="navigate(item.id)"><span>{{ number + 1 }}</span>{{ item.content.title }}</button></aside>
            <div class="teacher-main"><div class="studio-card screen-preview"><p class="eyebrow">{{ messages.shared_screen }}</p><StageRenderer :stage="stage" :messages="messages" /></div><div class="studio-card navigation-controls"><button :disabled="busy || index === 0" @click="navigate(session.document.stages[index - 1]!.id)">← {{ messages.previous }}</button><span>{{ index + 1 }} / {{ session.document.stages.length }}</span><button class="primary" :disabled="busy || index === session.document.stages.length - 1" @click="navigate(session.document.stages[index + 1]!.id)">{{ messages.next }} →</button></div><section v-if="stage.content.notes" class="studio-card teacher-notes"><h2>{{ messages.notes }}</h2><p class="plain-text">{{ stage.content.notes }}</p></section><section v-if="solutions.length" class="studio-card teacher-solutions"><h2>{{ messages.correct_answers }}</h2><dl><div v-for="solution in solutions" :key="solution.blockId"><dt>{{ solution.question }}</dt><dd>{{ solution.answer }}</dd></div></dl></section></div>
            <aside class="studio-card participants"><div class="section-heading"><h2>{{ messages.participants }}</h2><span class="status-pill">{{ session.participants.length }}</span></div><p v-if="!session.participants.length" class="field-hint">{{ messages.waiting_participants }}</p><div v-for="participant in session.participants" :key="participant.id" class="participant"><strong>{{ participant.name }}</strong><ul v-if="session.answers.some(answer => answer.participantId === participant.id && stage!.blocks.some(block => block.id === answer.blockId))"><li v-for="answer in session.answers.filter(answer => answer.participantId === participant.id && stage!.blocks.some(block => block.id === answer.blockId))" :key="answer.blockId">{{ answerText(answer.blockId, answer.optionId) }}</li></ul><small v-else>{{ messages.no_answers }}</small></div></aside>
        </div>
    </template>
</template>
