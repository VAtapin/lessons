<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, ApiError } from './api';
import { emptyMetadata, fileProblem, formatBytes, metadataOf } from './library';
import MetadataFields from './MetadataFields.vue';
import { libraryError } from './library-api';
import { multipart } from './library-api';
import type { Media, MediaAsset, MediaQuota, MediaResponse, Messages, Metadata } from './types';
const props = defineProps<{ locale: string; messages: Messages }>();
const assets = ref<MediaAsset[]>([]);
const media = ref<Media[]>([]);
const commonImages = computed(() => media.value.filter(item => item.labelKey && !item.archived));
const quota = ref<MediaQuota>();
const selected = ref<MediaAsset>();
const metadata = ref<Metadata>(emptyMetadata());
const uploadMetadata = ref<Metadata>(emptyMetadata());
const file = ref<File>();
const replacement = ref<File>();
const query = ref('');
const tag = ref('');
const archived = ref(false);
const busy = ref(false);
const error = ref('');
const notice = ref('');
const uploadOpen = ref(false);
const uploadKey = ref(0);
const replacementKey = ref(0);
const edited = ref(false);
async function search() {
    busy.value = true; error.value = '';
    try { const response = await api<MediaResponse>(`/api/studio/media?${new URLSearchParams({ q: query.value, tag: tag.value, archived: archived.value ? '1' : '0' })}`); assets.value = response.assets; media.value = response.media; quota.value = response.quota; }
    catch (problem) { error.value = libraryError(problem, props.messages); }
    finally { busy.value = false; }
}
function adopt(asset: MediaAsset) { selected.value = asset; metadata.value = metadataOf(asset); replacement.value = undefined; replacementKey.value++; edited.value = false; }
async function select(id: string, discard = false) {
    if (edited.value && !discard) { error.value = props.messages.unsaved_library_edits; return; }
    busy.value = true; error.value = ''; notice.value = '';
    try { adopt((await api<{ asset: MediaAsset }>(`/api/studio/media/${id}`)).asset); }
    catch (problem) { error.value = libraryError(problem, props.messages); }
    finally { busy.value = false; }
}
function pickFile(event: Event, replacing = false) { const picked = (event.target as HTMLInputElement).files?.[0]; if (replacing) replacement.value = picked; else file.value = picked; }
function validateFile(value: File) {
    const problem = fileProblem(value, quota.value?.maxFileBytes ?? 20 * 1024 * 1024);
    if (problem) throw new ApiError(problem, 422);
    if (quota.value && quota.value.usedBytes + value.size > quota.value.limitBytes) throw new ApiError('quota_exceeded', 422);
}
async function upload() {
    if (!file.value) return;
    busy.value = true; error.value = ''; notice.value = '';
    try {
        validateFile(file.value);
        const response = await multipart<{ asset: MediaAsset }>('/api/studio/media', { ...uploadMetadata.value, tags: JSON.stringify(uploadMetadata.value.tags) }, file.value);
        adopt(response.asset); file.value = undefined; uploadMetadata.value = emptyMetadata(); uploadKey.value++; uploadOpen.value = false; notice.value = props.messages.upload_complete; await search();
    } catch (problem) { error.value = libraryError(problem, props.messages); }
    finally { busy.value = false; }
}
async function replace() {
    if (!selected.value || !replacement.value) return;
    if (edited.value) { error.value = props.messages.save_metadata_before_version; return; }
    busy.value = true; error.value = ''; notice.value = '';
    try { validateFile(replacement.value); adopt((await multipart<{ asset: MediaAsset }>(`/api/studio/media/${selected.value.id}/versions`, { expectedRevision: String(selected.value.revision) }, replacement.value)).asset); notice.value = props.messages.image_version_saved; await search(); }
    catch (problem) { error.value = libraryError(problem, props.messages); }
    finally { busy.value = false; }
}
async function save() {
    if (!selected.value) return;
    busy.value = true; error.value = ''; notice.value = '';
    try { adopt((await api<{ asset: MediaAsset }>(`/api/studio/media/${selected.value.id}`, 'PUT', { expectedRevision: selected.value.revision, ...metadata.value })).asset); notice.value = props.messages.metadata_saved; await search(); }
    catch (problem) { error.value = libraryError(problem, props.messages); }
    finally { busy.value = false; }
}
async function archive() {
    if (!selected.value || edited.value) return;
    busy.value = true; error.value = '';
    try { adopt((await api<{ asset: MediaAsset }>(`/api/studio/media/${selected.value.id}/archive`, 'POST', { expectedRevision: selected.value.revision, archived: !selected.value.archived })).asset); await search(); }
    catch (problem) { error.value = libraryError(problem, props.messages); }
    finally { busy.value = false; }
}
onMounted(search);
</script>
<template>
    <div class="page-heading"><div><p class="eyebrow">{{ messages.media_library }}</p><h1>{{ messages.your_images }}</h1></div><button :aria-expanded="uploadOpen" aria-controls="image-upload" @click="uploadOpen = !uploadOpen">＋ {{ messages.upload_image }}</button></div><p class="info-banner">{{ messages.media_intro }}</p><p v-if="quota" class="quota-status" role="status">{{ messages.storage_used }} {{ formatBytes(quota.usedBytes) }} / {{ formatBytes(quota.limitBytes) }} · {{ messages.file_limit }} {{ formatBytes(quota.maxFileBytes) }}</p><p v-if="error" role="alert" class="error-banner">{{ error }}</p><p v-if="notice" role="status" class="success-banner">{{ notice }}</p>
    <form v-if="uploadOpen" id="image-upload" class="studio-card media-upload" @submit.prevent="upload"><h2>{{ messages.upload_image }}</h2><fieldset :disabled="busy" class="editor-fields"><label>{{ messages.image_file }}<input :key="uploadKey" type="file" accept="image/png,image/jpeg,image/webp" required @change="pickFile($event)" /></label><p class="field-hint">{{ messages.image_formats_hint }}</p><MetadataFields v-model="uploadMetadata" :messages="messages" /><button class="primary" :disabled="busy || !file">{{ busy ? messages.loading : messages.upload_image }}</button></fieldset></form>
    <form class="studio-card library-search" @submit.prevent="search"><label>{{ messages.search }}<input v-model="query" type="search" /></label><label>{{ messages.tag_filter }}<input v-model="tag" /></label><label class="checkbox-field"><input v-model="archived" type="checkbox" />{{ messages.show_archive }}</label><button :disabled="busy">{{ messages.search }}</button></form>
    <h2 class="media-section-heading">{{ messages.your_images }}</h2>
    <div class="library-layout"><section class="library-cards"><p v-if="busy && !assets.length" role="status">{{ messages.loading }}</p><p v-if="!busy && !assets.length" class="studio-card">{{ messages.no_images }}</p><button v-for="asset in assets" :key="asset.id" class="studio-card library-item media-item" :aria-pressed="selected?.id === asset.id" :disabled="busy || edited" @click="select(asset.id)"><img v-if="media.find(item => item.versionId === asset.currentVersionId)" :src="media.find(item => item.versionId === asset.currentVersionId)!.url" alt="" class="media-thumbnail" /><strong>{{ asset.title }}</strong><small>{{ asset.tags.join(' · ') }}</small><span v-if="asset.archived" class="status-pill">{{ messages.archived }}</span></button></section>
        <section v-if="selected" class="studio-card library-detail"><div class="section-heading"><h2>{{ selected.title }}</h2><button :disabled="busy || edited" @click="archive">{{ selected.archived ? messages.restore : messages.archive }}</button></div><form @submit.prevent="save" @input="edited = true" @change="edited = true"><fieldset :disabled="busy" class="editor-fields"><MetadataFields v-model="metadata" :messages="messages" /><button class="primary">{{ messages.save_metadata }}</button><button v-if="edited" type="button" :disabled="busy" @click="select(selected!.id, true)">{{ messages.discard_library_edits }}</button><p v-if="edited" class="field-hint">{{ messages.unsaved_library_edits }}</p></fieldset></form><form class="replace-image" @submit.prevent="replace"><h3>{{ messages.new_image_version }}</h3><p class="field-hint">{{ messages.image_version_hint }}</p><label>{{ messages.image_file }}<input :key="replacementKey" type="file" accept="image/png,image/jpeg,image/webp" required :disabled="busy || selected.archived" @change="pickFile($event, true)" /></label><button :disabled="busy || edited || !replacement || selected.archived">{{ messages.save_image_version }}</button></form><section class="media-version-history"><h3>{{ messages.version_history }}</h3><article v-for="version in selected.versions" :key="version.versionId" class="media-version"><img :src="version.url" :alt="selected.title" class="media-thumbnail" /><div><strong>{{ messages.version }} {{ version.versionNo }}</strong><span v-if="version.versionId === selected.currentVersionId" class="status-pill">{{ messages.current_version }}</span><p class="field-hint">{{ version.width }} × {{ version.height }} · {{ formatBytes(version.bytes ?? 0) }} · {{ version.mime }}</p><a :href="version.url" target="_blank" rel="noopener">{{ messages.open_image }} ↗</a></div></article></section><details class="library-usages"><summary>{{ messages.usages }} ({{ selected.usages.length }})</summary><p v-if="!selected.usages.length" class="field-hint">{{ messages.no_usages }}</p><ul><li v-for="(usage, index) in selected.usages" :key="index"><a :href="usage.lessonId ? `/${locale}/studio/lessons/${usage.lessonId}` : `/${locale}/library`">{{ usage.title }}</a><span v-if="usage.status"> · {{ messages[usage.status] }}</span></li></ul></details></section>
    </div>
    <section v-if="!archived && commonImages.length" class="builtin-gallery"><h2>{{ messages.common_illustrations }}</h2><p class="field-hint">{{ messages.common_illustrations_hint }}</p><div class="materials-grid"><article v-for="image in commonImages" :key="image.versionId" class="studio-card"><h3>{{ messages[image.labelKey!] }}</h3><img :src="image.url" :alt="messages[image.labelKey!]" class="media-thumbnail" loading="lazy" /><a :href="image.url" target="_blank" rel="noopener">{{ messages.open_image }} ↗</a></article></div></section>
</template>
