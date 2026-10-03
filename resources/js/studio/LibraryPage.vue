<script setup lang="ts">
import { blockTypes, blockLabel } from './interactive';
import { computed, onMounted, ref } from 'vue';
import { api, } from './api';
import { metadataOf } from './library';
import BlockEditor from './BlockEditor.vue';
import CommonLibrary from './CommonLibrary.vue';
import BlockRenderer from './BlockRenderer.vue';
import MetadataFields from './MetadataFields.vue';
import PreviewDialog from './PreviewDialog.vue';
import { libraryError } from './library-api';
import { imageResources } from './library';
import type { Block, Media, MediaResponse, Messages, Metadata, ProjectedBlock, TemplateDetail, TemplateSummary, TemplateVersion } from './types';
const props = defineProps<{ locale: string; messages: Messages }>();
const libraryTab = ref('personal');
const templates = ref<TemplateSummary[]>([]);
const selected = ref<TemplateDetail>();
const metadata = ref<Metadata>();
const block = ref<Block>();
const versionId = ref('');
const editLocale = ref('');
const defaultLocale = ref('');
const media = ref<Media[]>([]);
const query = ref('');
const tag = ref('');
const type = ref('');
const localeFilter = ref('');
const archived = ref(false);
const busy = ref(false);
const error = ref('');
const notice = ref('');
const edited = ref(false);
const previewOpen = ref(false);
const version = computed(() => selected.value?.versions.find(item => item.id === versionId.value));
const projected = computed<ProjectedBlock | undefined>(() => block.value && editLocale.value ? { ...block.value, content: block.value.content[editLocale.value]!, solution: undefined, teacherNotes: undefined, origin: undefined, resources: imageResources(media.value, block.value.media.image?.assetId, block.value.media.image?.versionId) } : undefined);
const pagination = ref({ page: 1, lastPage: 1, total: 0 });
async function search(page = 1) {
    busy.value = true; error.value = '';
    try { const response = await api<{ templates: TemplateSummary[]; pagination: typeof pagination.value }>(`/api/studio/templates?${new URLSearchParams({ page: String(page), q: query.value, tag: tag.value, type: type.value, locale: localeFilter.value, archived: archived.value ? '1' : '0' })}`); templates.value = response.templates; pagination.value = response.pagination ?? { page: 1, lastPage: 1, total: response.templates.length }; }
    catch (problem) { error.value = libraryError(problem, props.messages); }
    finally { busy.value = false; }
}
function adoptVersion(value: TemplateVersion) { block.value = structuredClone(value.block); editLocale.value = value.defaultLocale; defaultLocale.value = value.defaultLocale; edited.value = false; }
function adopt(value: TemplateDetail) { selected.value = value; metadata.value = metadataOf(value); versionId.value = value.currentVersionId; const item = value.versions.find(item => item.id === value.currentVersionId); if (item) adoptVersion(item); }
async function select(id: string, discard = false) {
    if (edited.value && !discard) { error.value = props.messages.unsaved_library_edits; return; }
    busy.value = true; error.value = ''; notice.value = '';
    try { adopt((await api<{ template: TemplateDetail }>(`/api/studio/templates/${id}`)).template); }
    catch (problem) { error.value = libraryError(problem, props.messages); }
    finally { busy.value = false; }
}
function changeVersion(event: Event) {
    const id = (event.target as HTMLSelectElement).value;
    if (edited.value) { (event.target as HTMLSelectElement).value = versionId.value; return; }
    const value = selected.value?.versions.find(item => item.id === id);
    if (value) { versionId.value = id; metadata.value = metadataOf(selected.value!); adoptVersion(value); }
}
async function save() {
    if (!selected.value || !metadata.value || !block.value || !version.value) return;
    busy.value = true; error.value = ''; notice.value = '';
    try { adopt((await api<{ template: TemplateDetail }>(`/api/studio/templates/${selected.value.id}`, 'PUT', { expectedRevision: selected.value.revision, locales: version.value.locales, defaultLocale: defaultLocale.value, block: block.value, ...metadata.value })).template); notice.value = props.messages.template_saved; await search(); }
    catch (problem) { error.value = libraryError(problem, props.messages); }
    finally { busy.value = false; }
}
async function archive() {
    if (!selected.value || edited.value) return;
    busy.value = true; error.value = '';
    try { adopt((await api<{ template: TemplateDetail }>(`/api/studio/templates/${selected.value.id}/archive`, 'POST', { expectedRevision: selected.value.revision, archived: !selected.value.archived })).template); await search(); }
    catch (problem) { error.value = libraryError(problem, props.messages); }
    finally { busy.value = false; }
}
onMounted(async () => { await search(); try { const results = await Promise.all([api<MediaResponse>('/api/studio/media?archived=0'), api<MediaResponse>('/api/studio/media?archived=1')]); media.value = [...new Map(results.flatMap(result => result.media).map(item => [item.versionId, item])).values()]; } catch (problem) { error.value = libraryError(problem, props.messages); } });
</script>
<template>
    <nav class="admin-tabs" :aria-label="messages.block_library"><button :aria-current="libraryTab === 'personal' ? 'page' : undefined" @click="libraryTab = 'personal'">{{ messages.admin_personal_library }}</button><button :aria-current="libraryTab === 'common' ? 'page' : undefined" @click="libraryTab = 'common'">{{ messages.admin_common_library }}</button></nav>
    <CommonLibrary v-if="libraryTab === 'common'" :locale="locale" :messages="messages" />
    <div v-show="libraryTab === 'personal'">
    <div class="page-heading"><div><p class="eyebrow">{{ messages.library }}</p><h1>{{ messages.block_library }}</h1></div><a class="button-link" :href="`/${locale}/studio`">{{ messages.my_materials }}</a></div><p class="info-banner">{{ messages.library_intro }}</p><p v-if="error" role="alert" class="error-banner">{{ error }}</p><p v-if="notice" role="status" class="success-banner">{{ notice }}</p>
    <form class="studio-card library-search" @submit.prevent="search(1)"><label>{{ messages.search }}<input v-model="query" type="search" /></label><label>{{ messages.tag_filter }}<input v-model="tag" /></label><label>{{ messages.block_type }}<select v-model="type"><option value="">{{ messages.all_types }}</option><option v-for="blockType in blockTypes" :key="blockType" :value="blockType">{{ blockLabel(blockType, messages) }}</option></select></label><label>{{ messages.content_language }}<input v-model="localeFilter" maxlength="35" :placeholder="messages.all_languages" /></label><label class="checkbox-field"><input v-model="archived" type="checkbox" />{{ messages.show_archive }}</label><button :disabled="busy">{{ messages.search }}</button></form>
    <nav v-if="pagination.lastPage > 1" class="pagination" :aria-label="messages.library_pages"><button type="button" :disabled="busy || pagination.page <= 1" @click="search(pagination.page - 1)">{{ messages.previous }}</button><span>{{ pagination.page }} / {{ pagination.lastPage }}</span><button type="button" :disabled="busy || pagination.page >= pagination.lastPage" @click="search(pagination.page + 1)">{{ messages.next }}</button></nav><div class="library-layout"><section class="library-cards"><p v-if="busy && !templates.length" role="status">{{ messages.loading }}</p><p v-if="!busy && !templates.length" class="studio-card">{{ messages.no_templates }}</p><button v-for="item in templates" :key="item.id" class="studio-card library-item" :aria-pressed="selected?.id === item.id" :disabled="busy || edited" @click="select(item.id)"><strong>{{ item.title }}</strong><span>{{ blockLabel(item.type, messages) }} · {{ item.locales.join(', ') }}</span><small>{{ item.tags.join(' · ') }}</small><span v-if="item.archived" class="status-pill">{{ messages.archived }}</span></button></section>
        <section v-if="selected && metadata && block && version" class="studio-card library-detail"><div class="section-heading"><h2>{{ selected.title }}</h2><button :disabled="busy || edited" @click="archive">{{ selected.archived ? messages.restore : messages.archive }}</button></div><p class="field-hint">{{ messages.independent_template_hint }}</p><label>{{ messages.version }}<select :value="versionId" :disabled="busy || edited" @change="changeVersion"><option v-for="item in selected.versions" :key="item.id" :value="item.id">{{ messages.version }} {{ item.versionNo }}</option></select></label><form @submit.prevent="save" @input="edited = true" @change="edited = true"><fieldset :disabled="busy" class="editor-fields"><MetadataFields v-model="metadata" :messages="messages" /><div class="template-language-fields"><label>{{ messages.content_language }}<select v-model="editLocale"><option v-for="language in version.locales" :key="language">{{ language }}</option></select></label><label>{{ messages.default_language }}<select v-model="defaultLocale"><option v-for="language in version.locales" :key="language">{{ language }}</option></select></label></div><BlockEditor :block="block" :locale="editLocale" :locales="version.locales" :media="media" :messages="messages" @edited="edited = true" /><button class="primary" :disabled="busy">{{ messages.save_template_version }}</button><button v-if="edited" type="button" :disabled="busy" @click="select(selected!.id, true)">{{ messages.discard_library_edits }}</button><p v-if="edited" class="field-hint">{{ messages.unsaved_library_edits }}</p></fieldset></form><button type="button" class="library-preview" aria-haspopup="dialog" @click="previewOpen = true">{{ messages.preview }}</button><PreviewDialog v-if="previewOpen" :title="selected.title" :close-label="messages.close" @close="previewOpen = false"><BlockRenderer v-if="projected" :block="projected" :messages="messages" /></PreviewDialog><details class="library-usages"><summary>{{ messages.usages }} ({{ selected.usages.length }})</summary><p v-if="!selected.usages.length" class="field-hint">{{ messages.no_usages }}</p><ul><li v-for="usage in selected.usages" :key="usage.lessonVersionId + ':' + usage.blockId"><a :href="`/${locale}/studio/lessons/${usage.lessonId}`">{{ usage.title }}</a> · {{ messages[usage.status ?? 'draft'] }}</li></ul></details></section>
    </div>
    </div>
</template>
