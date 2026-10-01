<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { api, errorMessage } from './api';
import { projectStage } from './document';
import { cloneEditor } from './editor-save';
import { valueText } from './interactive';
import StageRenderer from './StageRenderer.vue';
import type { LessonDocument, Media, Messages, ProjectedStage, Readiness } from './types';
const props = defineProps<{ lessonId: string; document: LessonDocument; revision: number; locale: string; stageId: string; media: Media[]; blocked?: boolean; messages: Messages }>();
const audience = ref('projector');
const remote = ref<ProjectedStage>();
const readiness = ref<Readiness>();
const error = ref('');
const busy = ref(false);
let generation = 0;
const local = computed(() => { const stage = props.document.stages.find(item => item.id === props.stageId); return stage && projectStage(stage, props.locale, props.media); });
watch(() => [props.document, props.locale, props.stageId, audience.value], () => { generation++; remote.value = undefined; readiness.value = undefined; }, { deep: true });
async function validate() {
    if (props.blocked) return;
    const started = generation;
    busy.value = true; error.value = '';
    try { const response = await api<{ preview: { stage: ProjectedStage; readiness: Readiness } }>(`/api/studio/lessons/${props.lessonId}/preview`, 'POST', { expectedRevision: props.revision, document: cloneEditor(props.document), audience: audience.value, locale: props.locale, stageId: props.stageId }); if (generation === started) { remote.value = response.preview.stage; readiness.value = response.preview.readiness; } }
    catch (problem) { if (generation === started) error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
const shown = computed(() => remote.value ?? local.value);
</script>
<template>
    <section class="studio-card editor-preview"><div class="section-heading"><h2>{{ messages.preview }}</h2><label>{{ messages.preview_audience }}<select v-model="audience"><option value="teacher">{{ messages.teacher_panel }}</option><option value="student">{{ messages.student_screen }}</option><option value="projector">{{ messages.shared_screen }}</option></select></label></div><p class="field-hint">{{ remote ? messages.preview_server_checked : messages.preview_local_hint }}</p><button type="button" :disabled="busy || blocked" @click="validate">{{ messages.check_preview }}</button><p v-if="error" class="error-banner" role="alert">{{ error }}</p><p v-if="readiness" class="field-hint">{{ messages['translation_' + (readiness.locales.find(item => item.locale === locale)?.status ?? 'draft')] }}</p><StageRenderer v-if="shown" :stage="shown" :messages="messages" /><template v-if="remote && audience === 'teacher'"><details v-if="remote.content.notes"><summary>{{ messages.notes }}</summary><p class="plain-text">{{ remote.content.notes }}</p></details><div v-for="block in remote.blocks" :key="block.id"><details v-if="block.teacherNotes"><summary>{{ messages.block_notes }}</summary><p class="plain-text">{{ block.teacherNotes }}</p></details><details v-if="block.solution"><summary>{{ messages.correct_answers }}</summary><p class="plain-text">{{ valueText(block.content, block.solution, messages) }}</p></details></div></template><p class="field-hint">{{ messages.preview_rehearsal_hint }}</p>
    </section>
</template>
