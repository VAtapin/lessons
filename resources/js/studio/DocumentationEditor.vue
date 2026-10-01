<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { LessonDocument, Messages, ProjectedDocumentation } from './types';
import { youtubeVideoId } from './documentation';
import LessonDocumentation from './LessonDocumentation.vue';
const props = defineProps<{ document: LessonDocument; locale: string; messages: Messages }>();
const videoInput = ref(''), invalidVideo = ref(false);
watch(() => props.document.documentation?.video?.id, id => { videoInput.value = id ? 'https://www.youtube.com/watch?v=' + id : ''; invalidVideo.value = false; }, { immediate: true });
function ensure() { return props.document.documentation ??= { schemaVersion: 1, content: {}, files: [] }; }
const plan = computed({
    get: () => props.document.documentation?.content[props.locale]?.plan ?? '',
    set: value => { const documentation = ensure(); if (value.trim()) documentation.content[props.locale] = { plan: value }; else delete documentation.content[props.locale]; },
});
function updateVideo() {
    const value = videoInput.value.trim();
    const id = value ? youtubeVideoId(value) : undefined;
    invalidVideo.value = !!value && !id;
    if (invalidVideo.value) return;
    const documentation = ensure();
    if (id) documentation.video = { id, locale: documentation.video?.locale ?? props.locale };
    else delete documentation.video;
}
const projected = computed<ProjectedDocumentation>(() => ({ plan: plan.value || null, files: props.document.documentation?.files ?? [], video: props.document.documentation?.video ?? null }));
</script>

<template>
    <details class="studio-card documentation-editor">
        <summary>{{ messages.documentation_title }}</summary>
        <p class="field-hint">{{ messages.documentation_editor_hint }}</p>
        <label>{{ messages.documentation_read_plan }} · {{ locale.toUpperCase() }}<textarea v-model="plan" rows="10" maxlength="30000" /></label>
        <label>{{ messages.documentation_video }}<input v-model="videoInput" type="text" @change="updateVideo" /></label>
        <p v-if="invalidVideo" role="alert">{{ messages.documentation_video_invalid }}</p>
        <label v-if="document.documentation?.video">{{ messages.documentation_video_language }}<select v-model="document.documentation.video.locale"><option v-for="language in ['ru', 'de']" :key="language" :value="language">{{ language.toUpperCase() }}</option></select></label>
        <LessonDocumentation v-if="document.documentation" :documentation="projected" :messages="messages" />
    </details>
</template>
