<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { api, ApiError } from '../studio/api';
import CatalogFilters from './CatalogFilters.vue';
import PublicIcon from './PublicIcon.vue';
import StageRenderer from '../studio/StageRenderer.vue';
import type { ProjectedStage } from '../studio/types';
import { catalogPageQuery, taxonomyOptions } from './filters';
import type { CatalogEntry, CatalogList, CatalogMessages, CatalogTerm } from './types';
const props = defineProps<{ locale: string; messages: CatalogMessages; studioMessages?: CatalogMessages; slug?: string }>();
const loading = ref(true), failed = ref(false), missing = ref(false), action = ref(''), actionFailed = ref(false);
const entries = ref<CatalogEntry[]>([]), entry = ref<CatalogEntry | null>(null);
const pagination = ref<CatalogList['pagination']>({ page: 1, total: 0, perPage: 12, lastPage: 1 });
const previewStages = ref<ProjectedStage[]>([]), previewIndex = ref(0);
const terms = ref<CatalogTerm[] | null>(null), taxonomyFailed = ref(false), taxonomyLoading = ref(true);
let controller: AbortController | undefined;
async function load() {
    controller?.abort(); controller = new AbortController(); loading.value = true; failed.value = false; missing.value = false;
    try {
        taxonomyLoading.value = true; taxonomyFailed.value = false;
        try {
            terms.value = (await api<{ terms: CatalogTerm[] }>(`/api/catalog/taxonomy?locale=${encodeURIComponent(props.locale)}`, 'GET', undefined, controller.signal)).terms;
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') throw error;
            terms.value = null; taxonomyFailed.value = true;
        } finally { taxonomyLoading.value = false; }
        if (props.slug) {
            const response = await api<{ entry: CatalogEntry; preview: { stages: ProjectedStage[] } }>(`/api/catalog/${encodeURIComponent(props.slug)}?locale=${props.locale}`, 'GET', undefined, controller.signal);
            entry.value = response.entry;
            previewStages.value = response.preview.stages;
        } else {
            const query = catalogPageQuery(window.location.search, terms.value);
            query.set('locale', props.locale);
            const response = await api<CatalogList>(`/api/catalog?${query}`, 'GET', undefined, controller.signal);
            entries.value = response.entries; pagination.value = response.pagination;
        }
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') return;
        missing.value = error instanceof ApiError && error.status === 404; failed.value = !missing.value;
    } finally { loading.value = false; }
}
async function useLesson(mode: 'use' | 'start') {
    if (action.value || !entry.value) return;
    action.value = mode; actionFailed.value = false;
    try {
        const result = await api<{ lesson: { id: string }; session?: { id: string } }>(`/api/catalog/${encodeURIComponent(entry.value.slug)}/${mode}`, 'POST', { locale: props.locale });
        const target = mode === 'start' && result.session ? `/${props.locale}/teach/${result.session.id}` : `/${props.locale}/studio/lessons/${result.lesson.id}`;
        window.location.assign(target);
    } catch { actionFailed.value = true; action.value = ''; }
}
function pageLink(page: number): string { const query = catalogPageQuery(window.location.search, terms.value); query.set('page', String(page)); return `/${props.locale}/catalog?${query}`; }
function topicLabel(key: string): string { return taxonomyOptions(terms.value, props.messages).topic.find(term => term.key === key)?.label ?? props.messages[`topic_${key}`] ?? key; }
onMounted(load); onBeforeUnmount(() => controller?.abort());
</script>
<template>
    <div class="catalog-content public-content">
        <nav class="catalog-breadcrumb" :aria-label="messages.home"><a :href="`/${locale}`">{{ messages.home }}</a><span aria-hidden="true">/</span><a v-if="slug" :href="`/${locale}/catalog`">{{ messages.catalog_title }}</a><span v-else>{{ messages.catalog_title }}</span></nav>
        <template v-if="!slug"><h1>{{ messages.catalog_title }}</h1><p class="catalog-intro">{{ messages.catalog_intro }}</p><CatalogFilters :locale="locale" :messages="messages" :terms="terms" :taxonomy-failed="taxonomyFailed" :taxonomy-loading="taxonomyLoading" full /><button v-if="taxonomyFailed" class="public-button secondary" @click="load">{{ messages.retry }}</button></template>
        <div v-if="loading" class="catalog-status" role="status">{{ messages.loading }}</div>
        <p v-if="slug && taxonomyFailed" role="status">{{ messages.load_error }} <button type="button" @click="load">{{ messages.retry }}</button></p>
        <div v-else-if="failed" class="catalog-status" role="alert"><p>{{ messages.load_error }}</p><button class="public-button" @click="load">{{ messages.retry }}</button></div>
        <div v-else-if="missing" class="catalog-status"><h1>{{ messages.not_found }}</h1><a class="public-button" :href="`/${locale}/catalog`">{{ messages.back_catalog }}</a></div>
        <article v-else-if="entry" class="catalog-detail">
            <div class="lesson-overview"><img v-if="entry.coverUrl" :src="entry.coverUrl" alt="" class="lesson-cover" /><div><p class="hero-eyebrow">{{ messages.format_lesson }} · {{ entry.durationMinutes }} {{ messages.minutes }} · {{ entry.locales.map(l => l.toUpperCase()).join(' / ') }}</p><h1>{{ entry.title }}</h1><p>{{ entry.description }}</p><div class="lesson-tags"><span v-for="topic in entry.topic" :key="topic">{{ topicLabel(topic) }}</span></div><div class="public-actions"><button class="public-button" :disabled="!!action" @click="useLesson('start')">{{ action === 'start' ? messages.action_pending : messages.start_lesson }}<PublicIcon name="arrow" /></button><button class="public-button secondary" :disabled="!!action" @click="useLesson('use')">{{ action === 'use' ? messages.action_pending : messages.use_lesson }}</button></div><p class="lesson-hint">{{ messages.start_hint }}</p><p class="lesson-hint">{{ messages.copy_hint }}</p><p v-if="actionFailed" role="alert">{{ messages.action_error }}</p></div></div>
            <div v-if="entry.details" class="lesson-facts"><section><h2>{{ messages.lesson_goals }}</h2><ul><li v-for="goal in entry.details.goals" :key="goal">{{ goal }}</li></ul></section><section><h2>{{ messages.lesson_materials }}</h2><ul><li v-for="material in entry.details.materials" :key="material">{{ material }}</li></ul><p>{{ entry.details.devices }}</p><p>{{ entry.details.conditions }}</p></section></div>
            <section class="lesson-stage-list"><h2>{{ messages.lesson_content }}</h2><ol><li v-for="(stage, index) in entry.stages" :key="index"><span>{{ index + 1 }}</span><h3>{{ stage.title }}</h3><small v-if="stage.durationSeconds">{{ Math.ceil(stage.durationSeconds / 60) }} {{ messages.minutes }}</small></li></ol></section>
            <section v-if="previewStages.length"><h2>{{ messages.preview_title }}</h2><label class="preview-controls">{{ messages.preview_stage }}<select v-model="previewIndex"><option v-for="(stage, index) in previewStages" :key="stage.id" :value="index">{{ index + 1 }}. {{ stage.content.title }}</option></select></label><div class="catalog-preview"><StageRenderer v-if="previewStages[previewIndex]" :stage="previewStages[previewIndex]!" :messages="studioMessages || messages" /></div></section>
        </article>
        <template v-else><p class="catalog-result-count" role="status">{{ messages.results }} {{ pagination.total }}</p><div v-if="!entries.length" class="catalog-status"><h2>{{ messages.empty_title }}</h2><p>{{ messages.empty_text }}</p><a class="public-button secondary" :href="`/${locale}/catalog`">{{ messages.back_catalog }}</a></div><div v-else class="catalog-results"><article v-for="lesson in entries" :key="lesson.slug" class="catalog-card"><a :href="`/${locale}/catalog/${encodeURIComponent(lesson.slug)}`"><img v-if="lesson.coverUrl" :src="lesson.coverUrl" alt="" loading="lazy" /><div><p class="hero-eyebrow">{{ lesson.durationMinutes }} {{ messages.minutes }} · {{ lesson.locales.map(l => l.toUpperCase()).join(' / ') }}</p><h2>{{ lesson.title }}</h2><p>{{ lesson.description }}</p><span class="card-detail-link">{{ messages.details }}<PublicIcon name="arrow" /></span></div></a></article></div><nav v-if="pagination.lastPage > 1" class="catalog-pagination" :aria-label="messages.page"><a v-if="pagination.page > 1" :href="pageLink(pagination.page - 1)">{{ messages.prev_page }}</a><span>{{ messages.page }} {{ pagination.page }} / {{ pagination.lastPage }}</span><a v-if="pagination.page < pagination.lastPage" :href="pageLink(pagination.page + 1)">{{ messages.next_page }}</a></nav></template>
    </div>
</template>
