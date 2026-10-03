<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { api } from './api';
import { adminError, type CommonTemplate } from './admin';
import { compatibleLocales } from './library';
import { blockTypes, blockLabel } from './interactive';
import BlockRenderer from './BlockRenderer.vue';
import PreviewDialog from './PreviewDialog.vue';
import type { Block, Messages, ProjectedBlock } from './types';
import '../../css/admin.css';
const props = defineProps<{ locale: string; messages: Messages; locales?: string[] }>();
const emit = defineEmits<{ insert: [block: Block] }>();
const templates = ref<CommonTemplate[]>([]), selected = ref<CommonTemplate | null>(null), preview = ref<ProjectedBlock | null>(null), query = ref(''), versionId = ref(''), error = ref(''), loading = ref(false), busy = ref(false);
const libraryRoot = ref<HTMLElement | null>(null);
const previewOpen = ref(false), previewTitle = ref('');
let previewRequest = 0, listRequest = 0;
const scope = ref('universal'), type = ref(''), tag = ref('');
const pagination = ref({ page: 1, lastPage: 1, total: 0 });
const canInsert = computed(() => !!props.locales?.length && !!selected.value && compatibleLocales(selected.value.versions.find(version => version.id === versionId.value)?.locales ?? [], props.locales));
async function load(page = 1) {
    const request = ++listRequest;
    loading.value = true; error.value = '';
    try {
        const params = new URLSearchParams({ locale: props.locale, scope: scope.value, page: String(page) });
        for (const [key, value] of Object.entries({ q: query.value.trim(), type: type.value, tag: tag.value.trim() })) if (value) params.set(key, value);
        const response = await api<{ templates: CommonTemplate[]; pagination: typeof pagination.value }>(`/api/catalog/templates?${params}`);
        if (request !== listRequest) return;
        templates.value = response.templates; pagination.value = response.pagination ?? { page: 1, lastPage: 1, total: response.templates.length };
        if (page > 1) { await nextTick(); libraryRoot.value?.scrollIntoView?.({ block: 'start' }); }
    } catch (problem) { if (request === listRequest) error.value = adminError(problem, props.messages); }
    finally { if (request === listRequest) loading.value = false; }
}
async function open(item: CommonTemplate) { const request = ++previewRequest; previewOpen.value = true; previewTitle.value = item.title; busy.value = true; error.value = ''; preview.value = null; selected.value = null; try { const response = await api<{ template: CommonTemplate; preview: ProjectedBlock }>(`/api/catalog/templates/${encodeURIComponent(item.id)}?locale=${props.locale}`); if (request !== previewRequest) return; selected.value = response.template; preview.value = response.preview; versionId.value = response.template.versionId; } catch (problem) { if (request === previewRequest) error.value = adminError(problem, props.messages); } finally { if (request === previewRequest) busy.value = false; } }
function closePreview() { previewRequest++; previewOpen.value = false; selected.value = null; preview.value = null; busy.value = false; }
async function insert() { if (!selected.value || !canInsert.value || busy.value) return; busy.value = true; error.value = ''; try { const response = await api<{ block: Block }>(`/api/catalog/templates/${encodeURIComponent(selected.value.id)}/instantiate`, 'POST', { versionId: versionId.value, locales: [...props.locales!] }); emit('insert', response.block); } catch (problem) { error.value = adminError(problem, props.messages); } finally { busy.value = false; } }
onMounted(() => load());
</script>
<template>
    <section ref="libraryRoot" class="admin-module common-library"><h2>{{ messages.admin_common_library }}</h2><p>{{ messages.admin_common_intro }}</p><form class="library-search" @submit.prevent="load(1)"><label>{{ messages.admin_search }}<input v-model="query" type="search" maxlength="200" /></label><label>{{ messages.library_scope }}<select v-model="scope"><option value="universal">{{ messages.library_universal }}</option><option value="lesson">{{ messages.library_lesson }}</option><option value="all">{{ messages.library_all }}</option></select></label><label>{{ messages.block_type }}<select v-model="type"><option value="">{{ messages.all_types }}</option><option v-for="item in blockTypes" :key="item" :value="item">{{ blockLabel(item, messages) }}</option></select></label><label>{{ messages.tag_filter }}<input v-model="tag" maxlength="50" /></label><button :disabled="loading">{{ messages.search }}</button></form><p class="info-banner">{{ messages.library_scope_hint }}</p><p role="status">{{ messages.library_total }}: {{ pagination.total }}</p><p v-if="loading" role="status">{{ messages.admin_loading }}</p><div v-if="error && !previewOpen" class="admin-alert" role="alert"><p>{{ error }}</p><button @click="load(pagination.page)">{{ messages.admin_retry }}</button></div><p v-if="!loading && !templates.length">{{ messages.admin_no_templates }}</p><div class="admin-common-grid"><article v-for="item in templates" :key="item.id"><small>{{ item.scope === 'lesson' ? messages.library_lesson : messages.library_universal }}</small><h3>{{ item.title }}</h3><p>{{ item.description }}</p><p class="admin-tags">{{ item.tags.join(' · ') }}</p><small>{{ item.locales.map(language => language.toUpperCase()).join(' / ') }}</small><button type="button" aria-haspopup="dialog" :disabled="busy" @click="open(item)">{{ messages.admin_preview }}</button></article></div>
        <nav v-if="pagination.lastPage > 1" class="pagination" :aria-label="messages.library_pages"><button type="button" :disabled="loading || pagination.page <= 1" @click="load(pagination.page - 1)">{{ messages.previous }}</button><span>{{ pagination.page }} / {{ pagination.lastPage }}</span><button type="button" :disabled="loading || pagination.page >= pagination.lastPage" @click="load(pagination.page + 1)">{{ messages.next }}</button></nav>
        <PreviewDialog v-if="previewOpen" :title="selected?.title ?? previewTitle" :close-label="messages.admin_close" @close="closePreview">
            <p v-if="busy && !selected" role="status">{{ messages.admin_loading }}</p><p v-if="error" class="admin-alert" role="alert">{{ error }}</p>
            <template v-if="selected"><BlockRenderer v-if="preview" :block="preview" :messages="messages" /><label>{{ messages.admin_version }}<select v-model="versionId" :disabled="busy"><option v-for="version in selected.versions" :key="version.id" :value="version.id">{{ version.versionNo }} · {{ version.locales.join(' / ') }}</option></select></label><p>{{ messages.admin_preview_current }}</p><p v-if="locales?.length && !canInsert" class="admin-notice">{{ messages.admin_incompatible }}</p><button v-if="locales?.length" type="button" :disabled="!canInsert || busy" @click="insert">{{ busy ? messages.admin_saving : messages.admin_insert }}</button><p v-else>{{ messages.admin_open_editor_insert }}</p></template>
        </PreviewDialog>
    </section>
</template>
