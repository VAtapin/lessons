<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api, errorMessage } from './api';
import { projectStage } from './document';
import StageRenderer from './StageRenderer.vue';
import type { AuthoringVersion, LessonDocument, Media, MediaResponse, Messages } from './types';
const props = defineProps<{ lessonId: string; messages: Messages }>();
const versions = ref<AuthoringVersion[]>([]);
const selected = ref('');
const document = ref<LessonDocument>();
const media = ref<Media[]>([]);
const language = ref('');
const busy = ref(false);
const error = ref('');
async function loadVersion() {
    busy.value = true; error.value = '';
    try { document.value = (await api<{ version: { document: LessonDocument } }>(`/api/studio/lessons/${props.lessonId}/versions/${selected.value}`)).version.document; language.value = document.value.defaultLocale; }
    catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
onMounted(async () => {
    busy.value = true;
    try { const responses = await Promise.all([api<{ versions: AuthoringVersion[] }>(`/api/studio/lessons/${props.lessonId}/versions`), api<MediaResponse>('/api/studio/media?archived=0'), api<MediaResponse>('/api/studio/media?archived=1')]); versions.value = responses[0].versions; media.value = [...responses[1].media, ...responses[2].media]; selected.value = versions.value.find(version => version.current)?.id ?? versions.value[0]?.id ?? ''; if (selected.value) await loadVersion(); }
    catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
});
</script>
<template>
    <section class="studio-card version-browser"><h2>{{ messages.saved_versions }}</h2><p class="field-hint">{{ messages.version_readonly_hint }}</p><p v-if="error" class="error-banner" role="alert">{{ error }}</p><label>{{ messages.version }}<select v-model="selected" :disabled="busy" @change="loadVersion"><option v-for="(version, index) in versions" :key="version.id" :value="version.id">{{ versions.length - index }} · {{ messages[version.status] }} · {{ version.createdAt }} {{ version.current ? '· ' + messages.current_version : '' }}</option></select></label><label v-if="document">{{ messages.content_language }}<select v-model="language"><option v-for="locale in document.locales" :key="locale">{{ locale }}</option></select></label><details v-if="document" v-for="(stage, index) in document.stages" :key="stage.id"><summary>{{ index + 1 }}. {{ stage.content[language]?.title }}</summary><StageRenderer :stage="projectStage(stage, language, media)" :messages="messages" /></details><p v-if="busy" role="status">{{ messages.loading }}</p>
    </section>
</template>
