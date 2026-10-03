<script setup lang="ts">
import { blockTypes, blockLabel } from './interactive';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ApiError, api, errorMessage } from './api';
import { move, newBlock, newStage } from './document';
import BlockEditor from './BlockEditor.vue';
import EditorLanguages from './EditorLanguages.vue';
import EditorSaveStatus from './EditorSaveStatus.vue';
import EditorPreview from './EditorPreview.vue';
import { useEditorDraft } from './useEditorDraft';
import { copyBlock, copyStage, addContentLocale, removeContentLocale, pointerSegment, blankOtherTranslations } from './editor-document';
import TemplatePicker from './TemplatePicker.vue';
import CommonLibrary from './CommonLibrary.vue';
import CatalogSubmissions from './CatalogSubmissions.vue';
import MetadataFields from './MetadataFields.vue';
import DocumentationEditor from './DocumentationEditor.vue';
import { emptyMetadata } from './library';
import type { Block, BlockType, EditorIssue, EditorLesson, Media, MediaResponse, Messages, TeacherState } from './types';
const props = defineProps<{ lessonId: string; locale: string; messages: Messages }>();
const media = ref<Media[]>([]);
const contentLocale = ref('');
const activeStageId = ref('');
const { lesson, document, save, history, dirty, error, composing, adopt, persist, restore, markText, markOperation, endComposition } = useEditorDraft(props.lessonId, activeStageId, contentLocale, props.messages);
const conflict = computed(() => save.status === 'conflict');
const releaseLocales = ref<string[]>([]);
const editorElement = ref<HTMLElement>();
const actionsAllowed = computed(() => save.status === 'clean' && !save.pending);
const readyToRun = computed(() => actionsAllowed.value && !!lesson.value?.readiness.readyLocales.includes(document.value?.defaultLocale ?? '') && !!lesson.value?.readiness.readyLocales.includes(contentLocale.value));
watch(() => lesson.value?.readiness, readiness => { if (readiness) releaseLocales.value = readiness.readyLocales.slice(); });
const notice = ref('');
const busy = ref(true);
const preview = ref(false);
const pickerOpen = ref(false);
const commonLibraryOpen = ref(false), publicationOpen = ref(false);
const templateBlockId = ref('');
const templateMetadata = ref(emptyMetadata());
const templateBusy = ref(false);
const activeStage = computed(() => document.value?.stages.find(stage => stage.id === activeStageId.value));
const activeStageIndex = computed(() => document.value?.stages.findIndex(stage => stage.id === activeStageId.value) ?? -1);
const types = blockTypes;
const labelFor = (type: BlockType) => blockLabel(type, props.messages);
async function load() {
    busy.value = true; error.value = ''; notice.value = '';
    try {
        const [lessonData, mediaData, archivedData] = await Promise.all([api<{ lesson: EditorLesson }>(`/api/studio/lessons/${props.lessonId}`), api<MediaResponse>('/api/studio/media?archived=0'), api<MediaResponse>('/api/studio/media?archived=1')]);
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
    if (!dirty.value && !save.pending) return;
    event.preventDefault();
    event.returnValue = '';
};
onMounted(() => { void load(); window.addEventListener('beforeunload', warnBeforeLeave); });
onBeforeUnmount(() => window.removeEventListener('beforeunload', warnBeforeLeave));
function copyActiveStage() { if (!document.value || !activeStage.value) return; const copied = copyStage(activeStage.value); document.value.stages.splice(activeStageIndex.value + 1, 0, copied); activeStageId.value = copied.id; }
function addLocale(locale: string) { if (document.value && addContentLocale(document.value, locale, contentLocale.value)) contentLocale.value = locale; else error.value = props.messages.error_invalid_locale; }
function removeLocale(locale: string) { if (document.value && removeContentLocale(document.value, locale) && contentLocale.value === locale) contentLocale.value = document.value.defaultLocale; }
async function focusIssue(issue: EditorIssue) { if (!document.value) return; if (issue.locale && document.value.locales.includes(issue.locale)) contentLocale.value = issue.locale; if (issue.stageId) activeStageId.value = issue.stageId; else { const match = issue.path.match(/^\/stages\/(\d+)/); if (match) activeStageId.value = document.value.stages[Number(match[1])]?.id ?? activeStageId.value; } await nextTick(); const field = Array.from(editorElement.value?.querySelectorAll<HTMLElement>('[data-editor-path]') ?? []).find(element => element.dataset.editorPath === issue.path); (field ?? editorElement.value?.querySelector<HTMLElement>(issue.blockId ? '[data-block-id="' + CSS.escape(issue.blockId) + '"] input, [data-block-id="' + CSS.escape(issue.blockId) + '"] textarea' : 'input'))?.focus(); }
function keyboard(event: KeyboardEvent) { if (!(event.ctrlKey || event.metaKey) || !['z','y'].includes(event.key.toLowerCase())) return; event.preventDefault(); restore(event.shiftKey || event.key.toLowerCase() === 'y' ? 'redo' : 'undo'); }
async function action(kind: 'save' | 'release' | 'start' | 'rehearsal') {
    if (kind === 'save') { await persist(); return; }
    if (!actionsAllowed.value || !lesson.value) return;
    busy.value = true; error.value = ''; notice.value = '';
    try {
        if (kind === 'release') {
            const response = await api<{ lesson: EditorLesson }>(`/api/studio/lessons/${props.lessonId}/release`, 'POST', { expectedRevision: lesson.value.revision, locales: releaseLocales.value });
            adopt(response.lesson, false); notice.value = props.messages.released_notice;
        }
        if (kind === 'rehearsal') { const response = await api<{ session: TeacherState }>(`/api/studio/lessons/${props.lessonId}/rehearsals`, 'POST', { expectedRevision: lesson.value.revision, locale: contentLocale.value }); location.assign(`/${props.locale}/teach/${response.session.id}`); }
        if (kind === 'start') {
            const response = await api<{ session: TeacherState }>(`/api/studio/lessons/${props.lessonId}/sessions`, 'POST', { expectedRevision: lesson.value.revision, locale: contentLocale.value, prepare: true });
            window.location.assign(`/${props.locale}/teach/${response.session.id}`);
        }
    } catch (problem) { if (problem instanceof ApiError && problem.status === 409) save.fail(409, (problem.data as { lesson?: EditorLesson })?.lesson); if (problem instanceof ApiError && problem.status === 422) save.issues = (problem.data as { issues?: EditorIssue[] })?.issues ?? []; error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
function addStage() {
    if (!document.value) return;
    const stage = blankOtherTranslations(newStage(document.value.locales, props.messages), contentLocale.value);
    stage.blocks.forEach(block => blankOtherTranslations(block, contentLocale.value));
    document.value.stages.push(stage); activeStageId.value = stage.id;
}
function deleteStage() {
    if (!document.value || document.value.stages.length <= 1) return;
    const removedIndex = activeStageIndex.value;
    document.value.stages.splice(removedIndex, 1);
    activeStageId.value = document.value.stages[Math.min(removedIndex, document.value.stages.length - 1)]!.id;
}
function addBlock(type: BlockType) {
    if (!activeStage.value || !document.value || (type === 'core.image' && !media.value.length)) return;
    const image = media.value.find(item => !item.archived);
    if (type === 'core.image' && !image) return;
    activeStage.value.blocks.push(blankOtherTranslations(newBlock(type, document.value.locales, props.messages, image), contentLocale.value));
}
function insertTemplate(block: Block) {
    if (!activeStage.value || busy.value || save.status === 'blocked' || conflict.value) return;
    markOperation();
    activeStage.value.blocks.push(block); pickerOpen.value = false; commonLibraryOpen.value = false;
}
function prepareTemplate(blockId: string) {
    if (!actionsAllowed.value || !lesson.value) { error.value = props.messages.save_before_template; return; }
    templateBlockId.value = blockId; templateMetadata.value = emptyMetadata();
}
async function saveTemplate() {
    if (!lesson.value || !actionsAllowed.value || !templateBlockId.value) { error.value = props.messages.save_before_template; return; }
    templateBusy.value = true; error.value = ''; notice.value = '';
    try {
        await api('/api/studio/templates', 'POST', { lessonId: props.lessonId, expectedLessonRevision: lesson.value.revision, blockId: templateBlockId.value, ...templateMetadata.value });
        templateBlockId.value = ''; notice.value = props.messages.template_saved;
    } catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { templateBusy.value = false; }
}
function answerDuration(event: Event) { const value = (event.target as HTMLInputElement).value; if (value === '') delete activeStage.value!.config.answerSeconds; else activeStage.value!.config.answerSeconds = Number(value); }
function duration(event: Event) {
    if (!activeStage.value) return;
    const value = (event.target as HTMLInputElement).value;
    if (value === '') delete activeStage.value.config.durationSeconds;
    else activeStage.value.config.durationSeconds = Number(value);
}
</script>
<template>
    <div class="page-heading"><div><p class="eyebrow">{{ messages.constructor }}</p><h1>{{ document ? (document.content[contentLocale]?.title || messages.untitled_material) : messages.loading }}</h1></div><span v-if="lesson" class="status-pill">{{ messages[lesson.status] }} · {{ messages.revision }} {{ lesson.revision }}</span></div>
    <div class="editor-library-links"><button type="button" :disabled="busy || save.status === 'blocked'" :aria-expanded="commonLibraryOpen" @click="commonLibraryOpen = !commonLibraryOpen">{{ messages.admin_editor_common }}</button><button type="button" :aria-expanded="publicationOpen" @click="publicationOpen = !publicationOpen">{{ messages.admin_publication_panel }}</button><a class="button-link" :href="`/${locale}/library`" target="_blank" rel="noopener">{{ messages.block_library }} ↗</a><a class="button-link" :href="`/${locale}/media`" target="_blank" rel="noopener">{{ messages.media_library }} ↗</a><button type="button" :disabled="busy" @click="refreshMedia">{{ messages.refresh_media }}</button></div>
    <CatalogSubmissions v-if="publicationOpen && lesson" :locale="locale" :messages="messages" :lesson="lesson" :media="media" />
    <CommonLibrary v-if="commonLibraryOpen && document" :locale="locale" :messages="messages" :locales="document.locales" :inert="busy || save.status === 'blocked' || conflict ? true : undefined" @insert="insertTemplate" />
    <p v-if="error" role="alert" class="error-banner">{{ error }}</p>
    <EditorSaveStatus :status="save.status" :pending="!!save.pending" :issues="save.issues" :messages="messages" @retry="persist(true)" @focus="focusIssue" />
    <div v-if="conflict" class="info-banner"><p>{{ messages.conflict_help }}</p><label class="checkbox-field"><input v-model="preview" type="checkbox" />{{ messages.preview }}</label><button :disabled="busy" @click="load">{{ messages.discard_reload }}</button></div>
    <p v-if="notice" class="success-banner" role="status">{{ notice }}</p>
    <p v-if="!document && busy" role="status">{{ messages.loading }}</p>
    <form v-if="document && lesson" ref="editorElement" tabindex="-1" class="editor-form" @submit.prevent="action('save')" @input.capture="markText" @click.capture="markOperation" @compositionstart="composing = true" @compositionend="endComposition" @keydown="keyboard">
        <fieldset :disabled="busy || save.status === 'blocked'" class="editor-fields">
            <div class="studio-card document-settings"><label>{{ messages.material_title }}<input v-model="document.content[contentLocale]!.title" :data-editor-path="'/content/' + pointerSegment(contentLocale) + '/title'" /></label><label>{{ messages.content_language }}<select v-model="contentLocale"><option v-for="language in document.locales" :key="language">{{ language }}</option></select></label><label>{{ messages.default_language }}<select v-model="document.defaultLocale"><option v-for="language in document.locales" :key="language">{{ language }}</option></select></label></div>
            <EditorLanguages :document="document" :selected="contentLocale" :readiness="lesson.readiness" :stale="dirty" :messages="messages" @select="contentLocale = $event" @add="addLocale" @remove="removeLocale" @focus="focusIssue" />
            <DocumentationEditor :document="document" :locale="contentLocale" :messages="messages" />
            <div class="editor-layout">
                <aside class="studio-card stage-list"><h2>{{ messages.stages }}</h2><button v-for="(stage, index) in document.stages" :key="stage.id" type="button" :class="['stage-select', { active: stage.id === activeStageId }]" :aria-current="stage.id === activeStageId ? 'step' : undefined" @click="activeStageId = stage.id"><span>{{ index + 1 }}</span>{{ stage.content[contentLocale]!.title }}</button><button type="button" @click="addStage">＋ {{ messages.add_stage }}</button></aside>
                <section v-if="activeStage" class="editor-stage">
                    <div class="studio-card"><div class="section-heading"><h2>{{ messages.stage_settings }}</h2><div class="button-row"><button type="button" :disabled="activeStageIndex === 0" :aria-label="messages.move_up" @click="move(document.stages, activeStageIndex, -1)">↑</button><button type="button" :disabled="activeStageIndex === document.stages.length - 1" :aria-label="messages.move_down" @click="move(document.stages, activeStageIndex, 1)">↓</button><button type="button" @click="copyActiveStage">{{ messages.copy_stage }}</button><button type="button" :disabled="document.stages.length <= 1" @click="deleteStage">{{ messages.remove }}</button></div></div><label>{{ messages.stage_title }}<input v-model="activeStage.content[contentLocale]!.title" :data-editor-path="'/stages/' + activeStageIndex + '/content/' + pointerSegment(contentLocale) + '/title'" /></label><label>{{ messages.notes }}<textarea v-model="activeStage.content[contentLocale]!.notes" rows="2" /></label><label>{{ messages.duration }}<input type="number" min="1" step="1" :value="activeStage.config.durationSeconds" @input="duration" /></label><p class="field-hint">{{ messages.duration_hint }}</p><label class="checkbox-field"><input v-model="activeStage.config.sequentialTasks" type="checkbox" />{{ messages.stage_sequential_tasks }}</label><label class="checkbox-field"><input v-model="activeStage.config.closeOnTimer" type="checkbox" />{{ messages.stage_close_on_timer }}</label><label>{{ messages.stage_answer_seconds }}<input type="number" min="1" max="3600" :value="activeStage.config.answerSeconds" @input="answerDuration" /></label><label>{{ messages.stage_theme }}<select :value="activeStage.config.theme ?? 'green'" @change="activeStage.config.theme = ($event.target as HTMLSelectElement).value as 'green' | 'terracotta' | 'slate' | 'lavender' | 'ocean' | 'berry' | 'cobalt' | 'plum' | 'copper' | 'indigo' | 'rose'"><option v-for="theme in ['green','terracotta','slate','lavender','ocean','berry','cobalt','plum','copper','indigo','rose']" :key="theme" :value="theme">{{ messages['theme_' + theme] }}</option></select></label><label>{{ messages.stage_layout }}<select v-model="activeStage.config.layout"><option v-for="layout in ['vertical','two-columns','material-above-task']" :key="layout" :value="layout">{{ messages['layout_' + layout.replaceAll('-', '_')] }}</option></select></label></div>
                    <div v-for="(block, index) in activeStage.blocks" :key="block.id" :data-block-id="block.id" class="studio-card block-edit"><div class="section-heading"><h3>{{ index + 1 }}. {{ labelFor(block.type) }}</h3><div class="button-row"><button type="button" :disabled="index === 0" :aria-label="messages.move_up" @click="move(activeStage.blocks, index, -1)">↑</button><button type="button" :disabled="index === activeStage.blocks.length - 1" :aria-label="messages.move_down" @click="move(activeStage.blocks, index, 1)">↓</button><button type="button" @click="activeStage.blocks.splice(index + 1, 0, copyBlock(block))">{{ messages.copy_block }}</button><button type="button" :disabled="activeStage.blocks.length <= 1" @click="activeStage.blocks.splice(index, 1)">{{ messages.remove }}</button></div></div><button type="button" :disabled="busy || !actionsAllowed" @click="prepareTemplate(block.id)">{{ messages.save_to_library }}</button><p v-if="dirty" class="field-hint">{{ messages.save_before_template }}</p><BlockEditor editor-draft :path-base="'/stages/' + activeStageIndex + '/blocks/' + index" :block="block" :locale="contentLocale" :locales="document.locales" :media="media" :messages="messages" /></div>
                    <div class="add-blocks"><span>{{ messages.add_block }}</span><button v-for="type in types" :key="type" type="button" :disabled="type === 'core.image' && !media.some(item => !item.archived)" @click="addBlock(type)">＋ {{ labelFor(type) }}</button><button type="button" :aria-expanded="pickerOpen" @click="pickerOpen = !pickerOpen">{{ messages.insert_template }}</button></div>
                </section>
            </div>
        </fieldset>
        <div class="editor-toolbar"><button type="button" :disabled="busy || save.status === 'blocked' || !history.past.length" @click="restore('undo')">{{ messages.undo }}</button><button type="button" :disabled="busy || save.status === 'blocked' || !history.future.length" @click="restore('redo')">{{ messages.redo }}</button><button type="button" @click="preview = !preview" :aria-expanded="preview">{{ messages.preview }}</button><button type="submit" class="primary" :disabled="busy || conflict || save.flight || !!save.pending">{{ messages.save }}</button><button type="button" :disabled="busy || !actionsAllowed || !releaseLocales.includes(document.defaultLocale)" @click="action('release')">{{ messages.release }}</button><button type="button" :disabled="busy || !readyToRun" @click="action('rehearsal')">{{ messages.rehearsal }}</button><button type="button" :disabled="busy || !readyToRun" @click="action('start')">{{ messages.start_session }}</button></div>
    </form>
    <details v-if="document && lesson" class="studio-card release-translations"><summary>{{ messages.release_languages }}</summary><label v-for="locale in lesson.readiness.readyLocales" :key="locale" class="checkbox-field"><input v-model="releaseLocales" type="checkbox" :value="locale" :disabled="locale === document.defaultLocale" />{{ locale }}</label><p v-if="!readyToRun" class="field-hint">{{ messages.ready_translation_required }}</p></details>
    <p v-if="dirty" class="field-hint">{{ messages.save_before_rehearsal }}</p>


    <TemplatePicker v-if="pickerOpen && document" :locales="document.locales" :messages="messages" @insert="insertTemplate" @close="pickerOpen = false" />
    <form v-if="templateBlockId" class="studio-card save-template-form" @submit.prevent="saveTemplate"><div class="section-heading"><h2>{{ messages.save_to_library }}</h2><button type="button" :disabled="templateBusy" @click="templateBlockId = ''">{{ messages.close }}</button></div><fieldset :disabled="templateBusy" class="editor-fields"><MetadataFields v-model="templateMetadata" :messages="messages" /><button class="primary" :disabled="templateBusy || !actionsAllowed">{{ messages.save_to_library }}</button></fieldset></form>
    <EditorPreview v-if="preview && document && lesson && activeStage" :lesson-id="lessonId" :document="document" :revision="save.revision" :stage-id="activeStage.id" :locale="contentLocale" :media="media" :blocked="save.flight || !!save.pending || conflict || save.status === 'blocked'" :messages="messages" />
</template>
