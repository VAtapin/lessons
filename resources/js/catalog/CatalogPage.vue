<script setup lang="ts">
import { nextTick, onMounted, onBeforeUnmount, ref } from 'vue';
import { api, ApiError } from '../studio/api';
import CatalogFilters from './CatalogFilters.vue';
import PublicIcon from './PublicIcon.vue';
import ActiveSessionList from '../studio/ActiveSessionList.vue';
import { catalogStartDecision, type ActiveSessionPage } from './start-session';
import StageRenderer from '../studio/StageRenderer.vue';
import LessonDocumentation from '../studio/LessonDocumentation.vue';
import type { ProjectedStage } from '../studio/types';
import { catalogPageQuery, taxonomyOptions } from './filters';
import type { CatalogEntry, CatalogList, CatalogMessages, CatalogTerm } from './types';
const props = defineProps<{ locale: string; messages: CatalogMessages; studioMessages?: CatalogMessages; slug?: string; initial?: CatalogList | { entry: CatalogEntry; preview: { stages: ProjectedStage[] } } }>();
const initialList = props.initial && 'entries' in props.initial ? props.initial : null;
const initialDetail = props.initial && 'entry' in props.initial ? props.initial : null;
const loading = ref(!props.initial), failed = ref(false), missing = ref(false), action = ref(''), actionFailed = ref(false);
const entries = ref<CatalogEntry[]>(initialList?.entries || []), entry = ref<CatalogEntry | null>(initialDetail?.entry || null);
const pagination = ref<CatalogList['pagination']>(initialList?.pagination || { page: 1, total: 0, perPage: 12, lastPage: 1 });
const previewStages = ref<ProjectedStage[]>(initialDetail?.preview.stages || []), previewIndex = ref(selectedStage(initialDetail?.preview.stages.length ?? 1));
const terms = ref<CatalogTerm[] | null>(null), taxonomyFailed = ref(false), taxonomyLoading = ref(true);
const activeSessions = ref<ActiveSessionPage | null>(null);
const moreSessionsLoading = ref(false);
let controller: AbortController | undefined;
async function load(initialLoad = false) {
    controller?.abort(); controller = new AbortController(); loading.value = !(initialLoad && props.initial); failed.value = false; missing.value = false;
    try {
        taxonomyLoading.value = true; taxonomyFailed.value = false;
        try {
            terms.value = (await api<{ terms: CatalogTerm[] }>(`/api/catalog/taxonomy?locale=${encodeURIComponent(props.locale)}`, 'GET', undefined, controller.signal)).terms;
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') throw error;
            terms.value = null; taxonomyFailed.value = true;
        } finally { taxonomyLoading.value = false; }
        if (initialLoad && props.initial) return;
        if (props.slug) {
            const response = await api<{ entry: CatalogEntry; preview: { stages: ProjectedStage[] } }>(`/api/catalog/${encodeURIComponent(props.slug)}?locale=${props.locale}`, 'GET', undefined, controller.signal);
            entry.value = response.entry;
            previewStages.value = response.preview.stages; previewIndex.value = selectedStage(response.preview.stages.length);
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
async function useLesson(mode: 'use' | 'start', explicitlyNew = false) {
    if (action.value || !entry.value) return;
    action.value = mode; actionFailed.value = false;
    try {
        const slug = entry.value.slug;
        const create = () => api<{ lesson: { id: string }; session?: { id: string } }>(`/api/catalog/${encodeURIComponent(slug)}/${mode}`, 'POST', { locale: props.locale });
        let result;
        if (mode === 'start' && !explicitlyNew) {
            const decision = await catalogStartDecision(() => api<ActiveSessionPage>('/api/studio/sessions?status=active'), create);
            if (decision.kind === 'resume') {
                activeSessions.value = decision.page; action.value = '';
                await nextTick();
                const heading = document.getElementById('active-sessions-heading');
                heading?.focus({ preventScroll: true }); heading?.scrollIntoView({ block: 'start' });
                return;
            }
            result = decision.result;
        } else result = await create();
        const target = mode === 'start' && result.session ? `/${props.locale}/teach/${result.session.id}` : `/${props.locale}/studio/lessons/${result.lesson.id}`;
        window.location.assign(target);
    } catch { actionFailed.value = true; action.value = ''; }
}
async function moreActiveSessions() {
    if (!activeSessions.value?.nextCursor || moreSessionsLoading.value) return;
    moreSessionsLoading.value = true; actionFailed.value = false;
    try { const page = await api<ActiveSessionPage>(`/api/studio/sessions?${new URLSearchParams({ status: 'active', cursor: activeSessions.value.nextCursor })}`); activeSessions.value = { sessions: [...new Map([...activeSessions.value.sessions, ...page.sessions].map(session => [session.id, session])).values()], nextCursor: page.nextCursor }; }
    catch { actionFailed.value = true; } finally { moreSessionsLoading.value = false; }
}
function selectedStage(count: number): number { return Math.max(0, Math.min(count - 1, Math.floor(Number(new URLSearchParams(window.location.search).get('stage')) || 0))); }
function pageLink(page: number): string { const query = catalogPageQuery(window.location.search, terms.value); query.set('page', String(page)); return `/${props.locale}/catalog?${query}`; }
function topicLabel(key: string): string { return taxonomyOptions(terms.value, props.messages).topic.find(term => term.key === key)?.label ?? props.messages[`topic_${key}`] ?? key; }
onMounted(() => load(true)); onBeforeUnmount(() => controller?.abort());
</script>
<template>
    <div class="catalog-content public-content">
        <nav class="catalog-breadcrumb" :aria-label="messages.home"><a :href="`/${locale}`">{{ messages.home }}</a><span aria-hidden="true">/</span><a v-if="slug" :href="`/${locale}/catalog`">{{ messages.catalog_title }}</a><span v-else>{{ messages.catalog_title }}</span></nav>
        <template v-if="!slug"><h1>{{ messages.catalog_title }}</h1><p class="catalog-intro">{{ messages.catalog_intro }}</p><CatalogFilters :locale="locale" :messages="messages" :terms="terms" :taxonomy-failed="taxonomyFailed" :taxonomy-loading="taxonomyLoading" full /><button v-if="taxonomyFailed" class="public-button secondary" @click="load()">{{ messages.retry }}</button></template>
        <div v-if="loading" class="catalog-status" role="status">{{ messages.loading }}</div>
        <p v-if="slug && taxonomyFailed" role="status">{{ messages.load_error }} <button type="button" @click="load()">{{ messages.retry }}</button></p>
        <div v-else-if="failed" class="catalog-status" role="alert"><p>{{ messages.load_error }}</p><button class="public-button" @click="load()">{{ messages.retry }}</button></div>
        <div v-else-if="missing" class="catalog-status"><h1>{{ messages.not_found }}</h1><a class="public-button" :href="`/${locale}/catalog`">{{ messages.back_catalog }}</a></div>
        <article v-else-if="entry" class="catalog-detail">
            <div class="lesson-overview"><img v-if="entry.coverUrl" :src="entry.coverUrl" :alt="entry.title" :width="entry.coverWidth ?? undefined" :height="entry.coverHeight ?? undefined" fetchpriority="high" class="lesson-cover" /><div><p class="hero-eyebrow">{{ messages.format_lesson }} · {{ entry.durationMinutes }} {{ messages.minutes }} · {{ entry.locales.map(l => l.toUpperCase()).join(' / ') }}</p><h1>{{ entry.title }}</h1><p>{{ entry.description }}</p><div class="lesson-tags"><span v-for="topic in entry.topic" :key="topic">{{ topicLabel(topic) }}</span></div><div class="public-actions"><button class="public-button" :disabled="!!action" @click="useLesson('start')">{{ action === 'start' ? messages.action_pending : messages.start_lesson }}<PublicIcon name="arrow" /></button><button class="public-button secondary" :disabled="!!action" @click="useLesson('use')">{{ action === 'use' ? messages.action_pending : messages.use_lesson }}</button></div><p class="lesson-hint">{{ messages.start_hint }}</p><p class="lesson-hint">{{ messages.copy_hint }}</p><p v-if="actionFailed" role="alert">{{ messages.action_error }}</p></div></div>
            <section v-if="activeSessions" class="catalog-status" aria-labelledby="active-sessions-heading"><h2 id="active-sessions-heading" tabindex="-1">{{ messages.active_sessions_title }}</h2><p>{{ messages.active_sessions_hint }}</p><ActiveSessionList :sessions="activeSessions.sessions" :locale="locale" :messages="studioMessages || messages" /><button v-if="activeSessions.nextCursor" class="public-button secondary" :disabled="moreSessionsLoading" @click="moreActiveSessions">{{ messages.load_more }}</button><div class="public-actions"><button class="public-button" :disabled="!!action || moreSessionsLoading" @click="useLesson('start', true)">{{ messages.start_new_session }}</button><button class="public-button secondary" :disabled="!!action" @click="activeSessions = null">{{ messages.cancel }}</button></div></section>
            <div v-if="entry.details" class="lesson-facts"><section><h2>{{ messages.lesson_goals }}</h2><ul><li v-for="goal in entry.details.goals" :key="goal">{{ goal }}</li></ul></section><section><h2>{{ messages.lesson_materials }}</h2><ul><li v-for="material in entry.details.materials" :key="material">{{ material }}</li></ul><p>{{ entry.details.devices }}</p><p>{{ entry.details.conditions }}</p></section></div>
            <section class="lesson-stage-list"><h2>{{ messages.lesson_content }}</h2><ol><li v-for="(stage, index) in entry.stages" :key="index"><span>{{ index + 1 }}</span><h3>{{ stage.title }}</h3><small v-if="stage.durationSeconds">{{ Math.ceil(stage.durationSeconds / 60) }} {{ messages.minutes }}</small></li></ol></section>
            <LessonDocumentation v-if="entry.documentation" :documentation="entry.documentation" :messages="studioMessages || messages" />
            <section v-if="previewStages.length" id="lesson-preview"><h2>{{ messages.preview_title }}</h2><label class="preview-controls">{{ messages.preview_stage }}<select v-model="previewIndex"><option v-for="(stage, index) in previewStages" :key="stage.id" :value="index">{{ index + 1 }}. {{ stage.content.title }}</option></select></label><div class="catalog-preview"><StageRenderer v-if="previewStages[previewIndex]" :stage="previewStages[previewIndex]!" :messages="studioMessages || messages" /></div></section>
        </article>
        <template v-else><p class="catalog-result-count" role="status">{{ messages.results }} {{ pagination.total }}</p><div v-if="!entries.length" class="catalog-status"><h2>{{ messages.empty_title }}</h2><p>{{ messages.empty_text }}</p><a class="public-button secondary" :href="`/${locale}/catalog`">{{ messages.back_catalog }}</a></div><div v-else class="catalog-results"><article v-for="lesson in entries" :key="lesson.materialId ?? lesson.slug" class="catalog-card"><template v-if="lesson.downloads"><img v-if="lesson.coverUrl" :src="lesson.coverUrl" alt="" :width="lesson.coverWidth ?? undefined" :height="lesson.coverHeight ?? undefined" loading="lazy" /><div class="catalog-material"><p class="hero-eyebrow">{{ messages['format_' + lesson.format[0]] }} · {{ lesson.locales.map(l => l.toUpperCase()).join(' / ') }}</p><h2>{{ lesson.title }}</h2><div class="public-actions"><a v-for="file in lesson.downloads" :key="file.url" :href="file.url" class="public-button" download>{{ messages.download_material }} {{ file.extension }}</a></div><a class="card-detail-link" :href="`/${locale}/catalog/${encodeURIComponent(lesson.slug)}`">{{ messages.related_lesson }}<PublicIcon name="arrow" /></a></div></template><a v-else :href="`/${locale}/catalog/${encodeURIComponent(lesson.slug)}${lesson.stageIndex !== undefined ? '?stage=' + lesson.stageIndex + '#lesson-preview' : ''}`"><img v-if="lesson.coverUrl" :src="lesson.coverUrl" :alt="lesson.title" :width="lesson.coverWidth ?? undefined" :height="lesson.coverHeight ?? undefined" loading="lazy" /><div><p class="hero-eyebrow">{{ lesson.durationMinutes }} {{ messages.minutes }} · {{ lesson.locales.map(l => l.toUpperCase()).join(' / ') }}</p><h2>{{ lesson.title }}</h2><p v-if="lesson.lessonTitle">{{ lesson.lessonTitle }}</p><p>{{ lesson.description }}</p><span class="card-detail-link">{{ lesson.materialId ? messages.related_lesson : messages.details }}<PublicIcon name="arrow" /></span></div></a></article></div><nav v-if="pagination.lastPage > 1" class="catalog-pagination" :aria-label="messages.page"><a v-if="pagination.page > 1" :href="pageLink(pagination.page - 1)">{{ messages.prev_page }}</a><span>{{ messages.page }} {{ pagination.page }} / {{ pagination.lastPage }}</span><a v-if="pagination.page < pagination.lastPage" :href="pageLink(pagination.page + 1)">{{ messages.next_page }}</a></nav></template>
    </div>
</template>
