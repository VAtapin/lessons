<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api, errorMessage } from './api';
import StudioIcon from './StudioIcon.vue';
import ActiveSessionList from './ActiveSessionList.vue';
import { openWorkspaceSessions } from './workspace';
import type { HistorySummary, LessonSummary, Messages } from './types';
const props = defineProps<{ locale: string; messages: Messages; lessons: LessonSummary[] }>();
const sessions = ref<HistorySummary[]>([]);
const error = ref('');
const loading = ref(true);
const nextCursor = ref<string | null>(null);
async function loadSessions(more = false) {
    loading.value = true; error.value = '';
    try { const query = new URLSearchParams({ status: 'active', ...(more && nextCursor.value ? { cursor: nextCursor.value } : {}) }); const result = await api<{ sessions: HistorySummary[]; nextCursor: string | null }>(`/api/studio/sessions?${query}`); sessions.value = openWorkspaceSessions(more ? [...new Map([...sessions.value, ...result.sessions].map(session => [session.id, session])).values()] : result.sessions); nextCursor.value = result.nextCursor; }
    catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { loading.value = false; }
}
onMounted(() => void loadSessions());
</script>
<template>
    <section class="workspace-welcome studio-card"><p class="eyebrow">{{ messages.workspace_caption }}</p><h2>{{ messages.overview_welcome }}</h2><p>{{ messages.overview_intro }}</p><a class="button-link primary-link" :href="`/${locale}/studio?view=constructor`"><StudioIcon name="edit" />{{ messages.new_material }}</a></section>
    <section class="studio-card"><div class="section-heading"><h2>{{ messages.overview_sessions }}</h2><a :href="`/${locale}/history`">{{ messages.history }} →</a></div><p v-if="loading" role="status">{{ messages.loading }}</p><p v-if="error" role="alert" class="error-banner">{{ error }}</p><ActiveSessionList v-if="sessions.length" :sessions="sessions" :locale="locale" :messages="messages" /><p v-else-if="!loading && !error" class="overview-empty">{{ messages.overview_no_sessions }}</p><button v-if="error" :disabled="loading" @click="loadSessions(!!nextCursor)">{{ messages.retry }}</button><button v-if="nextCursor" :disabled="loading" @click="loadSessions(true)">{{ messages.load_more }}</button></section>
    <div class="overview-cards">
        <a class="studio-card overview-card" :href="`/${locale}/studio`"><StudioIcon name="lessons" /><h2>{{ messages.workspace_lessons }}</h2><strong class="overview-count">{{ lessons.length }}</strong><p>{{ messages.overview_lessons_hint }}</p></a>
        <a class="studio-card overview-card" :href="`/${locale}/library`"><StudioIcon name="library" /><h2>{{ messages.block_library }}</h2><p>{{ messages.overview_library_hint }}</p><span>{{ messages.overview_open }} →</span></a>
        <a class="studio-card overview-card" :href="`/${locale}/media`"><StudioIcon name="media" /><h2>{{ messages.media_library }}</h2><p>{{ messages.overview_media_hint }}</p><span>{{ messages.overview_open }} →</span></a>
    </div>
    <div class="overview-recent">
        <section class="studio-card"><div class="section-heading"><h2>{{ messages.overview_continue }}</h2><a :href="`/${locale}/studio`">{{ messages.workspace_lessons }} →</a></div><ul v-if="lessons.length" class="workspace-link-list"><li v-for="lesson in lessons.slice(0, 4)" :key="lesson.id"><a :href="`/${locale}/studio/lessons/${lesson.id}`"><StudioIcon name="edit" /><strong>{{ lesson.title || messages.untitled_material }}</strong><span class="status-pill">{{ messages[lesson.status] }}</span></a></li></ul><p v-else class="overview-empty">{{ messages.empty_description }}</p></section>
    </div>
</template>
