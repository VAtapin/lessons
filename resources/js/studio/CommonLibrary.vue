<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from './api';
import { adminError, type CommonTemplate } from './admin';
import { compatibleLocales } from './library';
import BlockRenderer from './BlockRenderer.vue';
import PreviewDialog from './PreviewDialog.vue';
import type { Block, Messages, ProjectedBlock } from './types';
import '../../css/admin.css';
const props = defineProps<{ locale: string; messages: Messages; locales?: string[] }>();
const emit = defineEmits<{ insert: [block: Block] }>();
const templates = ref<CommonTemplate[]>([]), selected = ref<CommonTemplate | null>(null), preview = ref<ProjectedBlock | null>(null), query = ref(''), versionId = ref(''), error = ref(''), loading = ref(false), busy = ref(false);
const previewOpen = ref(false), previewTitle = ref('');
let previewRequest = 0;
const matches = computed(() => templates.value.filter(item => `${item.title} ${item.description} ${item.tags.join(' ')}`.toLocaleLowerCase(props.locale).includes(query.value.trim().toLocaleLowerCase(props.locale))));
const canInsert = computed(() => !!props.locales?.length && !!selected.value && compatibleLocales(selected.value.versions.find(version => version.id === versionId.value)?.locales ?? [], props.locales));
async function load() { loading.value = true; error.value = ''; try { templates.value = (await api<{ templates: CommonTemplate[] }>(`/api/catalog/templates?locale=${props.locale}`)).templates; } catch (problem) { error.value = adminError(problem, props.messages); } finally { loading.value = false; } }
async function open(item: CommonTemplate) { const request = ++previewRequest; previewOpen.value = true; previewTitle.value = item.title; busy.value = true; error.value = ''; preview.value = null; selected.value = null; try { const response = await api<{ template: CommonTemplate; preview: ProjectedBlock }>(`/api/catalog/templates/${encodeURIComponent(item.id)}?locale=${props.locale}`); if (request !== previewRequest) return; selected.value = response.template; preview.value = response.preview; versionId.value = response.template.versionId; } catch (problem) { if (request === previewRequest) error.value = adminError(problem, props.messages); } finally { if (request === previewRequest) busy.value = false; } }
function closePreview() { previewRequest++; previewOpen.value = false; selected.value = null; preview.value = null; busy.value = false; }
async function insert() { if (!selected.value || !canInsert.value || busy.value) return; busy.value = true; error.value = ''; try { const response = await api<{ block: Block }>(`/api/catalog/templates/${encodeURIComponent(selected.value.id)}/instantiate`, 'POST', { versionId: versionId.value, locales: [...props.locales!] }); emit('insert', response.block); } catch (problem) { error.value = adminError(problem, props.messages); } finally { busy.value = false; } }
onMounted(load);
</script>
<template>
    <section class="admin-module common-library"><h2>{{ messages.admin_common_library }}</h2><p>{{ messages.admin_common_intro }}</p><label>{{ messages.admin_search }}<input v-model="query" type="search" /></label><p v-if="loading" role="status">{{ messages.admin_loading }}</p><div v-if="error && !previewOpen" class="admin-alert" role="alert"><p>{{ error }}</p><button @click="load">{{ messages.admin_retry }}</button></div><p v-if="!loading && !matches.length">{{ messages.admin_no_templates }}</p><div class="admin-common-grid"><article v-for="item in matches" :key="item.id"><h3>{{ item.title }}</h3><p>{{ item.description }}</p><p class="admin-tags">{{ item.tags.join(' · ') }}</p><small>{{ item.locales.map(language => language.toUpperCase()).join(' / ') }}</small><button type="button" aria-haspopup="dialog" :disabled="busy" @click="open(item)">{{ messages.admin_preview }}</button></article></div>
        <PreviewDialog v-if="previewOpen" :title="selected?.title ?? previewTitle" :close-label="messages.admin_close" @close="closePreview">
            <p v-if="busy && !selected" role="status">{{ messages.admin_loading }}</p><p v-if="error" class="admin-alert" role="alert">{{ error }}</p>
            <template v-if="selected"><BlockRenderer v-if="preview" :block="preview" :messages="messages" /><label>{{ messages.admin_version }}<select v-model="versionId" :disabled="busy"><option v-for="version in selected.versions" :key="version.id" :value="version.id">{{ version.versionNo }} · {{ version.locales.join(' / ') }}</option></select></label><p>{{ messages.admin_preview_current }}</p><p v-if="locales?.length && !canInsert" class="admin-notice">{{ messages.admin_incompatible }}</p><button v-if="locales?.length" type="button" :disabled="!canInsert || busy" @click="insert">{{ busy ? messages.admin_saving : messages.admin_insert }}</button><p v-else>{{ messages.admin_open_editor_insert }}</p></template>
        </PreviewDialog>
    </section>
</template>
