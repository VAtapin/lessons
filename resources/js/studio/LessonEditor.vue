<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { ApiError, api, errorMessage } from './api';
import { move, newBlock, newStage, projectStage } from './document';
import BlockEditor from './BlockEditor.vue';
import StageRenderer from './StageRenderer.vue';
import TemplatePicker from './TemplatePicker.vue';
import MetadataFields from './MetadataFields.vue';
import { emptyMetadata } from './library';
import type { Block, BlockType, Lesson, LessonDocument, Media, MediaResponse, Messages, TeacherState } from './types';
const props = defineProps<{ lessonId: string; locale: string; messages: Messages }>();
const lesson = ref<Lesson>();
const document = ref<LessonDocument>();
const media = ref<Media[]>([]);
const contentLocale = ref('');
const activeStageId = ref('');
const error = ref('');
const notice = ref('');
const conflict = ref(false);
const busy = ref(true);
const preview = ref(false);
const dirty = ref(false);
const pickerOpen = ref(false);
const templateBlockId = ref('');
const templateMetadata = ref(emptyMetadata());
const templateBusy = ref(false);
const activeStage = computed(() => document.value?.stages.find(stage => stage.id === activeStageId.value));
const activeStageIndex = computed(() => document.value?.stages.findIndex(stage => stage.id === activeStageId.value) ?? -1);
const types: BlockType[] = ['core.text', 'core.image', 'core.single-choice'];
const labelFor = (type: BlockType) => props.messages[type === 'core.text' ? 'text' : type === 'core.image' ? 'image' : 'single_choice'];
function adopt(value: Lesson) {
    lesson.value = value;
    document.value = structuredClone(value.document);
    if (!value.document.locales.includes(contentLocale.value)) contentLocale.value = value.document.defaultLocale;
    if (!value.document.stages.some(stage => stage.id === activeStageId.value)) activeStageId.value = value.document.stages[0]!.id;
    dirty.value = false; conflict.value = false;
}
async function load() {
    busy.value = true; error.value = ''; notice.value = '';
    try {
        const [lessonData, mediaData, archivedData] = await Promise.all([api<{ lesson: Lesson }>(`/api/studio/lessons/${props.lessonId}`), api<MediaResponse>('/api/studio/media?archived=0'), api<MediaResponse>('/api/studio/media?archived=1')]);
        media.value = [...new Map([...mediaData.media, ...archivedData.media].map(item => [item.versionId, item])).values()]; adopt(lessonData.lesson);
    } catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
async function refreshMedia() {
    busy.value = true; error.value = '';
    try {
        const responses = await Promise.all([api<MediaResponse>('/api/studio/media?archived=0'), api<MediaResponse>('/api/studio/media?archived=1')]);
        media.value = [...new Map(responses.flatMap(response => response.media).map(item => [item.versionId, item])).values()];
    } catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
const warnBeforeLeave = (event: BeforeUnloadEvent) => {
    if (!dirty.value) return;
    event.preventDefault();
    event.returnValue = '';
};
onMounted(() => { void load(); window.addEventListener('beforeunload', warnBeforeLeave); });
onBeforeUnmount(() => window.removeEventListener('beforeunload', warnBeforeLeave));
async function persist() {
    if (!document.value || !lesson.value) return false;
    if (!dirty.value) return true;
    const response = await api<{ lesson: Lesson }>(`/api/studio/lessons/${props.lessonId}`, 'PUT', { expectedRevision: lesson.value.revision, document: document.value });
    adopt(response.lesson);
    return true;
}
async function action(kind: 'save' | 'release' | 'start') {
    busy.value = true; error.value = ''; notice.value = '';
    try {
        if (!await persist() || !lesson.value) return;
        if (kind === 'save') notice.value = props.messages.saved;
        if (kind === 'release') {
            const response = await api<{ lesson: Lesson }>(`/api/studio/lessons/${props.lessonId}/release`, 'POST', { expectedRevision: lesson.value.revision });
            adopt(response.lesson); notice.value = props.messages.released_notice;
        }
        if (kind === 'start') {
            const response = await api<{ session: TeacherState }>(`/api/studio/lessons/${props.lessonId}/sessions`, 'POST', { expectedRevision: lesson.value.revision, locale: contentLocale.value, prepare: true });
            window.location.assign(`/${props.locale}/teach/${response.session.id}`);
        }
    } catch (problem) { conflict.value = problem instanceof ApiError && problem.code === 'revision_conflict'; error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
function addStage() {
    if (!document.value) return;
    const stage = newStage(document.value.locales, props.messages);
    document.value.stages.push(stage); activeStageId.value = stage.id; dirty.value = true;
}
function deleteStage() {
    if (!document.value || document.value.stages.length <= 1) return;
    document.value.stages.splice(activeStageIndex.value, 1);
    activeStageId.value = document.value.stages[0]!.id; dirty.value = true;
}
function addBlock(type: BlockType) {
    if (!activeStage.value || !document.value || (type === 'core.image' && !media.value.length)) return;
    const image = media.value.find(item => !item.archived);
    if (type === 'core.image' && !image) return;
    activeStage.value.blocks.push(newBlock(type, document.value.locales, props.messages, image)); dirty.value = true;
}
function insertTemplate(block: Block) {
    if (!activeStage.value) return;
    activeStage.value.blocks.push(block); dirty.value = true; pickerOpen.value = false;
}
function prepareTemplate(blockId: string) {
    if (dirty.value || !lesson.value) { error.value = props.messages.save_before_template; return; }
    templateBlockId.value = blockId; templateMetadata.value = emptyMetadata();
}
async function saveTemplate() {
    if (!lesson.value || dirty.value || !templateBlockId.value) { error.value = props.messages.save_before_template; return; }
    templateBusy.value = true; error.value = ''; notice.value = '';
    try {
        await api('/api/studio/templates', 'POST', { lessonId: props.lessonId, expectedLessonRevision: lesson.value.revision, blockId: templateBlockId.value, ...templateMetadata.value });
        templateBlockId.value = ''; notice.value = props.messages.template_saved;
    } catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { templateBusy.value = false; }
}
function duration(event: Event) {
    if (!activeStage.value) return;
    const value = (event.target as HTMLInputElement).value;
    if (value === '') delete activeStage.value.config.durationSeconds;
    else activeStage.value.config.durationSeconds = Number(value);
}
</script>
<template>
    <div class="page-heading"><div><p class="eyebrow">{{ messages.constructor }}</p><h1>{{ document?.content[contentLocale]?.title ?? messages.loading }}</h1></div><span v-if="lesson" class="status-pill">{{ messages[lesson.status] }} · {{ messages.revision }} {{ lesson.revision }}</span></div>
    <div class="editor-library-links"><a class="button-link" :href="`/${locale}/library`" target="_blank" rel="noopener">{{ messages.block_library }} ↗</a><a class="button-link" :href="`/${locale}/media`" target="_blank" rel="noopener">{{ messages.media_library }} ↗</a><button type="button" :disabled="busy" @click="refreshMedia">{{ messages.refresh_media }}</button></div>
    <p v-if="error" role="alert" class="error-banner">{{ error }}</p>
    <div v-if="conflict" class="info-banner"><p>{{ messages.conflict_help }}</p><label class="checkbox-field"><input v-model="preview" type="checkbox" />{{ messages.preview }}</label><button :disabled="busy" @click="load">{{ messages.discard_reload }}</button></div>
    <p v-if="notice" class="success-banner" role="status">{{ notice }}</p>
    <p v-if="!document && busy" role="status">{{ messages.loading }}</p>
    <form v-if="document && lesson" class="editor-form" @submit.prevent="action('save')" @input="dirty = true" @change="dirty = true">
        <fieldset :disabled="busy || conflict" class="editor-fields">
            <div class="studio-card document-settings"><label>{{ messages.material_title }}<input v-model="document.content[contentLocale]!.title" required /></label><label>{{ messages.content_language }}<select v-model="contentLocale"><option v-for="language in document.locales" :key="language">{{ language }}</option></select></label><label>{{ messages.default_language }}<select v-model="document.defaultLocale"><option v-for="language in document.locales" :key="language">{{ language }}</option></select></label></div>
            <div class="editor-layout">
                <aside class="studio-card stage-list"><h2>{{ messages.stages }}</h2><button v-for="(stage, index) in document.stages" :key="stage.id" type="button" :class="['stage-select', { active: stage.id === activeStageId }]" :aria-current="stage.id === activeStageId ? 'step' : undefined" @click="activeStageId = stage.id"><span>{{ index + 1 }}</span>{{ stage.content[contentLocale]!.title }}</button><button type="button" @click="addStage">＋ {{ messages.add_stage }}</button></aside>
                <section v-if="activeStage" class="editor-stage">
                    <div class="studio-card"><div class="section-heading"><h2>{{ messages.stage_settings }}</h2><div class="button-row"><button type="button" :disabled="activeStageIndex === 0" :aria-label="messages.move_up" @click="move(document.stages, activeStageIndex, -1); dirty = true">↑</button><button type="button" :disabled="activeStageIndex === document.stages.length - 1" :aria-label="messages.move_down" @click="move(document.stages, activeStageIndex, 1); dirty = true">↓</button><button type="button" :disabled="document.stages.length <= 1" @click="deleteStage">{{ messages.remove }}</button></div></div><label>{{ messages.stage_title }}<input v-model="activeStage.content[contentLocale]!.title" required /></label><label>{{ messages.notes }}<textarea v-model="activeStage.content[contentLocale]!.notes" rows="2" /></label><label>{{ messages.duration }}<input type="number" min="1" step="1" :value="activeStage.config.durationSeconds" @input="duration" /></label><p class="field-hint">{{ messages.duration_hint }}</p></div>
                    <div v-for="(block, index) in activeStage.blocks" :key="block.id" class="studio-card block-edit"><div class="section-heading"><h3>{{ index + 1 }}. {{ labelFor(block.type) }}</h3><div class="button-row"><button type="button" :disabled="index === 0" :aria-label="messages.move_up" @click="move(activeStage.blocks, index, -1); dirty = true">↑</button><button type="button" :disabled="index === activeStage.blocks.length - 1" :aria-label="messages.move_down" @click="move(activeStage.blocks, index, 1); dirty = true">↓</button><button type="button" :disabled="activeStage.blocks.length <= 1" @click="activeStage.blocks.splice(index, 1); dirty = true">{{ messages.remove }}</button></div></div><button type="button" :disabled="busy || dirty" @click="prepareTemplate(block.id)">{{ messages.save_to_library }}</button><p v-if="dirty" class="field-hint">{{ messages.save_before_template }}</p><BlockEditor :block="block" :locale="contentLocale" :locales="document.locales" :media="media" :messages="messages" @edited="dirty = true" /></div>
                    <div class="add-blocks"><span>{{ messages.add_block }}</span><button v-for="type in types" :key="type" type="button" :disabled="type === 'core.image' && !media.some(item => !item.archived)" @click="addBlock(type)">＋ {{ labelFor(type) }}</button><button type="button" :aria-expanded="pickerOpen" @click="pickerOpen = !pickerOpen">{{ messages.insert_template }}</button></div>
                </section>
            </div>
        </fieldset>
        <div class="editor-toolbar"><span>{{ dirty ? messages.unsaved : messages.up_to_date }}</span><button type="button" @click="preview = !preview" :aria-expanded="preview">{{ messages.preview }}</button><button type="submit" class="primary" :disabled="busy || conflict">{{ busy ? messages.saving : messages.save }}</button><button type="button" :disabled="busy || conflict" @click="action('release')">{{ messages.release }}</button><button type="button" :disabled="busy || conflict" @click="action('start')">{{ messages.start_session }}</button></div>
    </form>
    <TemplatePicker v-if="pickerOpen && document" :locales="document.locales" :messages="messages" @insert="insertTemplate" @close="pickerOpen = false" />
    <form v-if="templateBlockId" class="studio-card save-template-form" @submit.prevent="saveTemplate"><div class="section-heading"><h2>{{ messages.save_to_library }}</h2><button type="button" :disabled="templateBusy" @click="templateBlockId = ''">{{ messages.close }}</button></div><fieldset :disabled="templateBusy" class="editor-fields"><MetadataFields v-model="templateMetadata" :messages="messages" /><button class="primary" :disabled="templateBusy || dirty">{{ messages.save_to_library }}</button></fieldset></form>
    <section v-if="preview && activeStage" class="studio-card preview-panel"><p class="eyebrow">{{ messages.preview }}</p><StageRenderer :stage="projectStage(activeStage, contentLocale, media)" :messages="messages" /></section>
</template>
