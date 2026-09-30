<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api, errorMessage } from './api';
import { newDocument } from './document';
import type { Lesson, LessonSummary, Messages } from './types';
const props = defineProps<{ locale: string; messages: Messages }>();
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
</script>
<template>
    <div class="page-heading"><div><p class="eyebrow">{{ messages.workspace }}</p><h1>{{ messages.my_materials }}</h1></div><button class="primary" :disabled="busy" @click="create">＋ {{ messages.new_material }}</button></div>
    <p class="info-banner">{{ messages.guest_notice }}</p>
    <p v-if="error" class="error-banner" role="alert">{{ error }}</p>
    <p v-if="busy" role="status">{{ messages.loading }}</p>
    <div v-else-if="!lessons.length" class="studio-card empty-state"><h2>{{ messages.empty_title }}</h2><p>{{ messages.empty_description }}</p></div>
    <div v-else class="materials-grid"><a v-for="lesson in lessons" :key="lesson.id" class="studio-card material-card" :href="`/${locale}/studio/lessons/${lesson.id}`"><span class="status-pill">{{ messages[lesson.status] }}</span><h2>{{ lesson.title }}</h2><p>{{ messages.revision }} {{ lesson.revision }}</p><span>{{ messages.open_editor }} →</span></a></div>
</template>
