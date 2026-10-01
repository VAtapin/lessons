<script setup lang="ts">
import VersionBrowser from './VersionBrowser.vue';
import WorkspaceOverview from './WorkspaceOverview.vue';
import StudioIcon from './StudioIcon.vue';
import { accountState } from './identity';
import { nextTick, onMounted, ref } from 'vue';
import { api, ApiError, errorMessage } from './api';
import { newDocument } from './document';
import type { Lesson, LessonSummary, Messages } from './types';
const props = defineProps<{ locale: string; messages: Messages }>();
const view = new URLSearchParams(location.search).get('view');
const versionLesson = ref('');
const lessons = ref<LessonSummary[]>([]);
const busy = ref(true);
const error = ref('');
const notice = ref('');
const trash = view === 'trash';
const deleting = ref('');
const purging = ref<{ id: string; expectedRevision: number; title: string }[]>([]);
const purgeAll = ref(false);
const purgeConfirmation = ref<HTMLElement>();
async function confirmPurge(lesson?: LessonSummary) {
    if (busy.value || !trash) return;
    purgeAll.value = !lesson;
    purging.value = (lesson ? [lesson] : lessons.value.slice(0, 100)).map(item => ({ id: item.id, expectedRevision: item.revision, title: item.title || props.messages.untitled_material }));
    await nextTick(); purgeConfirmation.value?.focus();
}
async function purge() {
    if (busy.value || !trash || !purging.value.length || purging.value.length > 100) return;
    busy.value = true; error.value = ''; notice.value = '';
    const snapshot = purging.value.map(({ id, expectedRevision }) => ({ id, expectedRevision }));
    try {
        const result = await api<{ deletedIds: string[] }>(purgeAll.value ? '/api/studio/lessons/trash/purge' : `/api/studio/lessons/${snapshot[0]!.id}/purge`, 'POST', purgeAll.value ? { lessons: snapshot } : { expectedRevision: snapshot[0]!.expectedRevision });
        lessons.value = lessons.value.filter(item => !result.deletedIds.includes(item.id));
        purging.value = []; notice.value = props.messages.lesson_purged;
    } catch (problem) {
        if (problem instanceof ApiError && problem.status === 409) await load();
        error.value = errorMessage(problem, props.messages);
    } finally { busy.value = false; }
}
async function load() {
    busy.value = true; error.value = ''; deleting.value = ''; versionLesson.value = ''; purging.value = [];
    try { lessons.value = (await api<{ lessons: LessonSummary[] }>(`/api/studio/lessons?archived=${trash ? '1' : '0'}`)).lessons; }
    catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
onMounted(load);
async function create() {
    busy.value = true; error.value = '';
    try {
        const { lesson } = await api<{ lesson: Lesson }>('/api/studio/lessons', 'POST', { document: newDocument(props.locale, props.messages) });
        window.location.assign(`/${props.locale}/studio/lessons/${lesson.id}`);
    } catch (problem) { error.value = errorMessage(problem, props.messages); busy.value = false; }
}
async function favorite(lesson: LessonSummary) { busy.value = true; error.value = ''; try { const result = await api<{ favorite: boolean }>(`/api/studio/lessons/${lesson.id}/favorite`, 'POST', { favorite: !lesson.favorite }); lesson.favorite = result.favorite; } catch (problem) { error.value = errorMessage(problem, props.messages); } finally { busy.value = false; } }
async function archive(lesson: LessonSummary, archived: boolean) {
    if (busy.value) return;
    busy.value = true; error.value = ''; notice.value = '';
    try {
        await api<{ lesson: LessonSummary }>(`/api/studio/lessons/${lesson.id}/archive`, 'POST', { expectedRevision: lesson.revision, archived });
        lessons.value = lessons.value.filter(item => item.id !== lesson.id);
        if (versionLesson.value === lesson.id) versionLesson.value = '';
        deleting.value = '';
        notice.value = props.messages[archived ? 'lesson_removed' : 'lesson_restored'];
    } catch (problem) {
        if (problem instanceof ApiError && problem.code === 'revision_conflict') await load();
        error.value = errorMessage(problem, props.messages);
    } finally { busy.value = false; }
}
</script>
<template>
    <div class="page-heading"><div><p class="eyebrow">{{ messages.workspace_caption }}</p><h1>{{ trash ? messages.lesson_trash : view === 'overview' ? messages.workspace_overview : view === 'constructor' ? messages.constructor : messages.workspace_lessons }}</h1></div><button class="primary" :disabled="busy" @click="create"><StudioIcon name="edit" />{{ messages.new_material }}</button></div>
    <nav class="workspace-tabs" :aria-label="messages.workspace_caption"><a :href="`/${locale}/studio?view=overview`" :aria-current="view === 'overview' ? 'page' : undefined">{{ messages.workspace_overview }}</a><a :href="`/${locale}/studio`" :aria-current="!['overview', 'constructor', 'trash'].includes(view ?? '') ? 'page' : undefined">{{ messages.workspace_lessons }}</a><a :href="`/${locale}/studio?view=constructor`" :aria-current="view === 'constructor' ? 'page' : undefined">{{ messages.constructor }}</a><a :href="`/${locale}/studio?view=trash`" :aria-current="trash ? 'page' : undefined">{{ messages.lesson_trash }}</a></nav>
    <p class="info-banner">{{ accountState?.user ? messages.account_workspace_notice : messages.guest_notice }}</p>
    <p v-if="error" class="error-banner" role="alert">{{ error }}</p>
    <p v-if="notice" class="success-banner" role="status">{{ notice }} <a :href="`/${locale}/studio${trash ? '' : '?view=trash'}`">{{ trash ? messages.workspace_lessons : messages.lesson_trash }} →</a></p>
    <p v-if="busy" role="status">{{ messages.loading }}</p>
    <WorkspaceOverview v-if="view === 'overview'" :lessons="lessons" :locale="locale" :messages="messages" />
    <template v-else>
    <p v-if="trash" class="info-banner">{{ messages.lesson_trash_hint }}</p>
    <button v-if="trash && lessons.length" :disabled="busy" @click="confirmPurge()">{{ messages.empty_trash }}</button>
    <div v-if="purging.length" ref="purgeConfirmation" class="info-banner" role="alert" tabindex="-1">
        <p>{{ messages.lesson_purge_confirm }}</p>
        <p v-if="purgeAll">{{ messages.trash_batch_hint }} {{ purging.length }}</p>
        <ul><li v-for="item in purging" :key="item.id">{{ item.title }}</li></ul>
        <div class="button-row"><button :disabled="busy" @click="purge">{{ messages.purge_permanently }}</button><button :disabled="busy" @click="purging = []">{{ messages.cancel }}</button></div>
    </div>
    <p v-if="view === 'constructor'" class="constructor-intro">{{ messages.workspace_constructor_hint }}</p>
    <div v-if="!lessons.length && !busy && !error" class="studio-card empty-state"><StudioIcon name="edit" /><h2>{{ trash ? messages.lesson_trash_empty : messages.empty_title }}</h2><p>{{ trash ? messages.lesson_trash_hint : messages.empty_description }}</p><button v-if="!trash" class="primary" :disabled="busy" @click="create">{{ messages.new_material }}</button></div>
    <div v-else class="materials-grid"><article v-for="lesson in lessons" :key="lesson.id" class="studio-card material-card"><span class="status-pill">{{ trash ? messages.lesson_deleted : messages[lesson.status] }}</span><h2 v-if="trash">{{ lesson.title || messages.untitled_material }}</h2><a v-else :href="`/${locale}/studio/lessons/${lesson.id}`"><h2>{{ lesson.title || messages.untitled_material }}</h2></a><p>{{ messages.revision }} {{ lesson.revision }}</p><p v-if="trash && lesson.archivedAt">{{ messages.lesson_deleted_at }}: <time :datetime="lesson.archivedAt">{{ new Date(lesson.archivedAt).toLocaleString(locale) }}</time></p><div class="action-row"><template v-if="trash"><button class="primary" :disabled="busy" @click="archive(lesson, false)">{{ messages.restore }}</button><button :disabled="busy" @click="confirmPurge(lesson)">{{ messages.purge_permanently }}</button></template><template v-else><button :aria-pressed="!!lesson.favorite" :disabled="busy" @click="favorite(lesson)">{{ lesson.favorite ? '★' : '☆' }} {{ messages.favorite }}</button><button :aria-expanded="versionLesson === lesson.id" :disabled="busy" @click="versionLesson = versionLesson === lesson.id ? '' : lesson.id">{{ messages.saved_versions }}</button><a class="button-link" :href="`/${locale}/studio/lessons/${lesson.id}`">{{ messages.open_editor }} →</a><button :disabled="busy" :aria-expanded="deleting === lesson.id" :aria-label="messages.delete_lesson + ': ' + (lesson.title || messages.untitled_material)" @click="deleting = deleting === lesson.id ? '' : lesson.id">{{ messages.delete_lesson }}</button></template></div><div v-if="deleting === lesson.id" class="info-banner" role="alert"><p>{{ messages.lesson_delete_confirm }}</p><div class="button-row"><button :disabled="busy" @click="archive(lesson, true)">{{ messages.lesson_move_to_trash }}</button><button :disabled="busy" @click="deleting = ''">{{ messages.cancel }}</button></div></div></article></div>
    <VersionBrowser v-if="versionLesson && !trash" :key="versionLesson" :lesson-id="versionLesson" :messages="messages" />
    </template>
</template>
