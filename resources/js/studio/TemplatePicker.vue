<script setup lang="ts">
import { blockTypes, blockLabel } from './interactive';
import { computed, onMounted, ref } from 'vue';
import { api, errorMessage } from './api';
import { compatibleLocales } from './library';
import type { Block, Messages, TemplateDetail, TemplateSummary } from './types';
const props = defineProps<{ locales: string[]; messages: Messages }>();
const emit = defineEmits<{ insert: [block: Block]; close: [] }>();
const templates = ref<TemplateSummary[]>([]);
const selected = ref<TemplateDetail>();
const versionId = ref('');
const query = ref('');
const type = ref('');
const tag = ref('');
const error = ref('');
const busy = ref(false);
const version = computed(() => selected.value?.versions.find(item => item.id === versionId.value));
async function search() {
    busy.value = true; error.value = '';
    try { templates.value = (await api<{ templates: TemplateSummary[] }>(`/api/studio/templates?${new URLSearchParams({ q: query.value, type: type.value, tag: tag.value, archived: '0' })}`)).templates; }
    catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
async function select(id: string) {
    busy.value = true; error.value = '';
    try { selected.value = (await api<{ template: TemplateDetail }>(`/api/studio/templates/${id}`)).template; versionId.value = selected.value.currentVersionId; }
    catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
async function insert() {
    if (!selected.value || !version.value || !compatibleLocales(version.value.locales, props.locales)) return;
    busy.value = true; error.value = '';
    try { const response = await api<{ block: Block }>(`/api/studio/templates/${selected.value.id}/versions/${version.value.id}/instantiate`, 'POST', { locales: props.locales }); emit('insert', response.block); }
    catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
onMounted(search);
</script>
<template>
    <section class="studio-card template-picker"><div class="section-heading"><h2>{{ messages.insert_template }}</h2><button type="button" @click="$emit('close')">{{ messages.close }}</button></div><p v-if="error" role="alert" class="error-banner">{{ error }}</p><div class="library-search"><label>{{ messages.search }}<input v-model="query" type="search" @keyup.enter.prevent="search" /></label><label>{{ messages.tag_filter }}<input v-model="tag" @keyup.enter.prevent="search" /></label><label>{{ messages.block_type }}<select v-model="type"><option value="">{{ messages.all_types }}</option><option v-for="blockType in blockTypes" :key="blockType" :value="blockType">{{ blockLabel(blockType, messages) }}</option></select></label><button type="button" :disabled="busy" @click="search">{{ messages.search }}</button></div><p v-if="!busy && !templates.length" class="field-hint">{{ messages.no_templates }}</p><div class="template-choices"><button v-for="item in templates" :key="item.id" type="button" :disabled="busy" :aria-pressed="selected?.id === item.id" @click="select(item.id)">{{ item.title }} · {{ item.locales.join(', ') }}</button></div><div v-if="selected" class="template-selection"><h3>{{ selected.title }}</h3><p class="field-hint">{{ messages.independent_template_hint }}</p><label>{{ messages.version }}<select v-model="versionId"><option v-for="item in selected.versions" :key="item.id" :value="item.id">{{ messages.version }} {{ item.versionNo }} · {{ item.locales.join(', ') }}</option></select></label><p v-if="version && !compatibleLocales(version.locales, locales)" role="status" class="error-banner">{{ messages.template_locale_missing }} {{ locales.join(', ') }}</p><button type="button" class="primary" :disabled="busy || !version || !compatibleLocales(version.locales, locales)" @click="insert">{{ messages.insert_template }}</button></div></section>
</template>
