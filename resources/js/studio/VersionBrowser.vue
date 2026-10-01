<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api, errorMessage } from './api';
import { projectStage } from './document';
import StageRenderer from './StageRenderer.vue';
import type { AuthoringVersion, LessonDocument, Media, MediaResponse, Messages, Readiness } from './types';
const props = defineProps<{ lessonId: string; messages: Messages }>();
const versions = ref<AuthoringVersion[]>([]);
const selected = ref('');
const snapshot = ref<LessonDocument>();
const working = ref<LessonDocument>();
const readiness = ref<Readiness>();
const view = ref('snapshot');
const document = computed(() => view.value === 'editor' ? working.value : snapshot.value);
watch(document, value => { if (value) language.value = value.defaultLocale; });
const media = ref<Media[]>([]);
const language = ref('');
const busy = ref(false);
const error = ref('');
async function loadVersion() {
    busy.value = true; error.value = '';
    try { const version = (await api<{ version: { document: LessonDocument; editorDocument?: LessonDocument | null; readiness?: Readiness } }>(`/api/studio/lessons/${props.lessonId}/versions/${selected.value}`)).version; snapshot.value = version.document; working.value = version.editorDocument ?? undefined; readiness.value = version.readiness; view.value = 'snapshot'; }
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
    <section class="studio-card version-browser"><h2>{{ messages.saved_versions }}</h2><p class="field-hint">{{ messages.version_readonly_hint }}</p><p v-if="error" class="error-banner" role="alert">{{ error }}</p><label>{{ messages.version }}<select v-model="selected" :disabled="busy" @change="loadVersion"><option v-for="(version, index) in versions" :key="version.id" :value="version.id">{{ versions.length - index }} · {{ messages[version.status] }} · {{ version.createdAt }} {{ version.current ? '· ' + messages.current_version : '' }}</option></select></label><label v-if="working">{{ messages.version_view }}<select v-model="view"><option value="snapshot">{{ messages.strict_snapshot }}</option><option value="editor">{{ messages.working_translations }}</option></select></label><p v-if="view === 'editor' && readiness" class="field-hint">{{ messages.working_translations_hint }}</p><label v-if="document">{{ messages.content_language }}<select v-model="language"><option v-for="locale in document.locales" :key="locale">{{ locale }}</option></select></label><details v-if="document" v-for="(stage, index) in document.stages" :key="stage.id"><summary>{{ index + 1 }}. {{ stage.content[language]?.title }}</summary><StageRenderer :stage="projectStage(stage, language, media)" :messages="messages" /></details><p v-if="busy" role="status">{{ messages.loading }}</p>
    </section>
</template>
