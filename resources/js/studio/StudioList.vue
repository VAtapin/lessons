<script setup lang="ts">
import VersionBrowser from './VersionBrowser.vue';
import WorkspaceOverview from './WorkspaceOverview.vue';
import StudioIcon from './StudioIcon.vue';
import { accountState } from './identity';
import { onMounted, ref } from 'vue';
import { api, errorMessage } from './api';
import { newDocument } from './document';
import type { Lesson, LessonSummary, Messages } from './types';
const props = defineProps<{ locale: string; messages: Messages }>();
const view = new URLSearchParams(location.search).get('view');
const versionLesson = ref('');
const lessons = ref<LessonSummary[]>([]);
const busy = ref(true);
const error = ref('');
onMounted(async () => {
    try { lessons.value = (await api<{ lessons: LessonSummary[] }>('/api/studio/lessons')).lessons; }
    catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
});
async function create() {
    busy.value = true; error.value = '';
    try {
        const { lesson } = await api<{ lesson: Lesson }>('/api/studio/lessons', 'POST', { document: newDocument(props.locale, props.messages) });
        window.location.assign(`/${props.locale}/studio/lessons/${lesson.id}`);
    } catch (problem) { error.value = errorMessage(problem, props.messages); busy.value = false; }
}
async function favorite(lesson: LessonSummary) { busy.value = true; error.value = ''; try { const result = await api<{ favorite: boolean }>(`/api/studio/lessons/${lesson.id}/favorite`, 'POST', { favorite: !lesson.favorite }); lesson.favorite = result.favorite; } catch (problem) { error.value = errorMessage(problem, props.messages); } finally { busy.value = false; } }
</script>
<template>
    <div class="page-heading"><div><p class="eyebrow">{{ messages.workspace_caption }}</p><h1>{{ view === 'overview' ? messages.workspace_overview : view === 'constructor' ? messages.constructor : messages.workspace_lessons }}</h1></div><button class="primary" :disabled="busy" @click="create"><StudioIcon name="edit" />{{ messages.new_material }}</button></div>
    <nav class="workspace-tabs" :aria-label="messages.workspace_caption"><a :href="`/${locale}/studio?view=overview`" :aria-current="view === 'overview' ? 'page' : undefined">{{ messages.workspace_overview }}</a><a :href="`/${locale}/studio`" :aria-current="!['overview', 'constructor'].includes(view ?? '') ? 'page' : undefined">{{ messages.workspace_lessons }}</a><a :href="`/${locale}/studio?view=constructor`" :aria-current="view === 'constructor' ? 'page' : undefined">{{ messages.constructor }}</a></nav>
    <p class="info-banner">{{ accountState?.user ? messages.account_workspace_notice : messages.guest_notice }}</p>
    <p v-if="error" class="error-banner" role="alert">{{ error }}</p>
    <p v-if="busy" role="status">{{ messages.loading }}</p>
    <WorkspaceOverview v-else-if="view === 'overview'" :lessons="lessons" :locale="locale" :messages="messages" />
    <template v-else>
    <p v-if="view === 'constructor'" class="constructor-intro">{{ messages.workspace_constructor_hint }}</p>
    <div v-if="!lessons.length" class="studio-card empty-state"><StudioIcon name="edit" /><h2>{{ messages.empty_title }}</h2><p>{{ messages.empty_description }}</p><button class="primary" :disabled="busy" @click="create">{{ messages.new_material }}</button></div>
    <div v-else class="materials-grid"><article v-for="lesson in lessons" :key="lesson.id" class="studio-card material-card"><span class="status-pill">{{ messages[lesson.status] }}</span><a :href="`/${locale}/studio/lessons/${lesson.id}`"><h2>{{ lesson.title || messages.untitled_material }}</h2></a><p>{{ messages.revision }} {{ lesson.revision }}</p><div class="action-row"><button :aria-pressed="!!lesson.favorite" :disabled="busy" @click="favorite(lesson)">{{ lesson.favorite ? '★' : '☆' }} {{ messages.favorite }}</button><button :aria-expanded="versionLesson === lesson.id" @click="versionLesson = versionLesson === lesson.id ? '' : lesson.id">{{ messages.saved_versions }}</button><a class="button-link" :href="`/${locale}/studio/lessons/${lesson.id}`">{{ messages.open_editor }} →</a></div></article></div>
    <VersionBrowser v-if="versionLesson" :key="versionLesson" :lesson-id="versionLesson" :messages="messages" />
    </template>
</template>
