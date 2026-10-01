<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { api } from '../studio/api';
import { filterOptions, filterQuery, readFilters, taxonomyOptions } from './filters';
import PublicIcon from './PublicIcon.vue';
import type { CatalogMessages, CatalogTerm, CatalogTermKind } from './types';
const props = defineProps<{ locale: string; messages: CatalogMessages; full?: boolean; terms?: CatalogTerm[] | null; taxonomyFailed?: boolean; taxonomyLoading?: boolean }>();
const ownTerms = ref<CatalogTerm[] | null>(null), ownFailed = ref(false), ownLoading = ref(props.terms === undefined);
const terms = computed(() => props.terms === undefined ? ownTerms.value : props.terms);
const loading = computed(() => props.taxonomyLoading || ownLoading.value);
const failed = computed(() => props.taxonomyFailed || ownFailed.value);
const filters = reactive(readFilters(window.location.search));
const groups: CatalogTermKind[] = ['age', 'audience', 'topic', 'format'];
const icons = ['people', 'people', 'book', 'file'];
const options = computed(() => taxonomyOptions(terms.value, props.messages));
function values(key: CatalogTermKind) {
    return key === 'audience' && !props.full
        ? options.value[key].filter(term => !['children', 'adults'].includes(term.key) || filters.audience === term.key)
        : options.value[key];
}
watch(terms, value => Object.assign(filters, readFilters(window.location.search, value)), { immediate: true });
let controller: AbortController | undefined;
async function loadTaxonomy() {
    controller?.abort(); controller = new AbortController(); ownLoading.value = true; ownFailed.value = false;
    try { ownTerms.value = (await api<{ terms: CatalogTerm[] }>(`/api/catalog/taxonomy?locale=${encodeURIComponent(props.locale)}`, 'GET', undefined, controller.signal)).terms; }
    catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') return;
        ownFailed.value = true; ownTerms.value = null;
    } finally { ownLoading.value = false; }
}
function submit() {
    if (loading.value) return;
    const query = filterQuery(readFilters(filterQuery(filters), terms.value));
    window.location.assign(`/${props.locale}/catalog${query ? `?${query}` : ''}`);
}
onMounted(() => { if (props.terms === undefined) void loadTaxonomy(); });
onBeforeUnmount(() => controller?.abort());
</script>
<template>
    <form id="find-materials" class="material-picker" @submit.prevent="submit">
        <h2>{{ messages.find_title }}</h2>
        <p v-if="failed" role="status">{{ messages.load_error }} <button v-if="terms === null && props.terms === undefined" type="button" @click="loadTaxonomy">{{ messages.retry }}</button></p>
        <div class="picker-groups">
            <fieldset v-for="(key, index) in groups" :key="key" :disabled="loading">
                <legend><PublicIcon :name="icons[index]!" />{{ messages[`filter_${key}`] }}</legend>
                <div class="filter-chips"><button v-for="term in values(key)" :key="term.key" type="button" :aria-pressed="filters[key] === term.key" @click="filters[key] = filters[key] === term.key ? '' : term.key">{{ term.label }}</button></div>
            </fieldset>
        </div>
        <div class="picker-search">
            <label><span>{{ messages.search_label }}</span><input v-model="filters.q" type="search" maxlength="120" :placeholder="messages.search_placeholder" :disabled="loading" /></label>
            <label v-if="full" class="duration-select"><span>{{ messages.filter_duration }}</span><select v-model="filters.duration" :disabled="loading"><option value="">{{ messages.any_duration }}</option><option v-for="value in filterOptions.duration" :key="value" :value="value">{{ messages[`duration_${value}`] }}</option></select></label>
            <button class="public-button" type="submit" :disabled="loading">{{ messages.show_materials }}<PublicIcon name="arrow" /></button>
            <a v-if="full" :href="`/${locale}/catalog`" class="picker-reset">{{ messages.reset_filters }}</a>
        </div>
    </form>
</template>
