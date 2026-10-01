<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api, errorMessage } from './api';
import StudioIcon from './StudioIcon.vue';
import { openWorkspaceSessions } from './workspace';
import type { HistorySummary, LessonSummary, Messages } from './types';
const props = defineProps<{ locale: string; messages: Messages; lessons: LessonSummary[] }>();
const sessions = ref<HistorySummary[]>([]);
const error = ref('');
const loading = ref(true);
onMounted(async () => {
    try { sessions.value = openWorkspaceSessions((await api<{ sessions: HistorySummary[] }>('/api/studio/sessions')).sessions); }
    catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { loading.value = false; }
});
</script>
<template>
    <section class="workspace-welcome studio-card"><p class="eyebrow">{{ messages.workspace_caption }}</p><h2>{{ messages.overview_welcome }}</h2><p>{{ messages.overview_intro }}</p><a class="button-link primary-link" :href="`/${locale}/studio?view=constructor`"><StudioIcon name="edit" />{{ messages.new_material }}</a></section>
    <div class="overview-cards">
        <a class="studio-card overview-card" :href="`/${locale}/studio`"><StudioIcon name="lessons" /><h2>{{ messages.workspace_lessons }}</h2><strong class="overview-count">{{ lessons.length }}</strong><p>{{ messages.overview_lessons_hint }}</p></a>
        <a class="studio-card overview-card" :href="`/${locale}/library`"><StudioIcon name="library" /><h2>{{ messages.block_library }}</h2><p>{{ messages.overview_library_hint }}</p><span>{{ messages.overview_open }} →</span></a>
        <a class="studio-card overview-card" :href="`/${locale}/media`"><StudioIcon name="media" /><h2>{{ messages.media_library }}</h2><p>{{ messages.overview_media_hint }}</p><span>{{ messages.overview_open }} →</span></a>
    </div>
    <div class="overview-recent">
        <section class="studio-card"><div class="section-heading"><h2>{{ messages.overview_continue }}</h2><a :href="`/${locale}/studio`">{{ messages.workspace_lessons }} →</a></div><ul v-if="lessons.length" class="workspace-link-list"><li v-for="lesson in lessons.slice(0, 4)" :key="lesson.id"><a :href="`/${locale}/studio/lessons/${lesson.id}`"><StudioIcon name="edit" /><strong>{{ lesson.title || messages.untitled_material }}</strong><span class="status-pill">{{ messages[lesson.status] }}</span></a></li></ul><p v-else class="overview-empty">{{ messages.empty_description }}</p></section>
        <section class="studio-card"><div class="section-heading"><h2>{{ messages.overview_sessions }}</h2><a :href="`/${locale}/history`">{{ messages.history }} →</a></div><p v-if="loading" role="status" class="overview-empty">{{ messages.loading }}</p><p v-else-if="error" role="alert" class="error-banner">{{ error }}</p><ul v-else-if="sessions.length" class="workspace-link-list"><li v-for="session in sessions" :key="session.id"><a :href="`/${locale}/teach/${session.id}`"><StudioIcon name="screen" /><strong>{{ session.title }}</strong><span class="status-pill">{{ messages['session_' + session.status] }}</span></a></li></ul><p v-else class="overview-empty">{{ messages.overview_no_sessions }}</p></section>
    </div>
</template>
