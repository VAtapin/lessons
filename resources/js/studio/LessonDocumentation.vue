<script setup lang="ts">
import { computed } from 'vue';
import type { Messages, ProjectedDocumentation } from './types';
const props = defineProps<{ documentation: ProjectedDocumentation; messages: Messages }>();
const paragraphs = computed(() => props.documentation.plan?.split(/\n\s*\n/) ?? []);
</script>

<template>
    <section class="lesson-documentation">
        <h2>{{ messages.documentation_title }}</h2>
        <p class="documentation-hint">{{ messages.documentation_hint }}</p>
        <div class="documentation-files">
            <a v-for="file in documentation.files" :key="file.fileId" :href="file.url || '/lesson-files/' + encodeURIComponent(file.fileId)" class="button-link" download>
                {{ file.label ?? messages['documentation_' + file.kind] }} · {{ file.locale.toUpperCase() }} ↓
            </a>
            <a v-if="documentation.video" :href="'https://www.youtube.com/watch?v=' + documentation.video.id" class="button-link" target="_blank" rel="noopener noreferrer">
                {{ messages.documentation_video }} · {{ documentation.video.locale.toUpperCase() }} ↗
            </a>
        </div>
        <p v-if="documentation.video" class="documentation-hint">{{ messages.documentation_video_external }}</p>
        <details v-if="documentation.plan" class="documentation-plan" open>
            <summary>{{ messages.documentation_read_plan }}</summary>
            <!-- planHtml is rendered and sanitized by DocumentationFiles on the server. -->
            <div v-if="documentation.planHtml" class="documentation-plan-text" v-html="documentation.planHtml" />
            <div v-else class="documentation-plan-text"><p v-for="(paragraph, index) in paragraphs" :key="index">{{ paragraph }}</p></div>
        </details>
        <p v-else>{{ messages.documentation_plan_unavailable }}</p>
    </section>
</template>

<style scoped>
.lesson-documentation { margin-block: 24px; padding: 22px; border: 1px solid #dfcbaa; border-radius: 12px; background: #fffaf0e8; color: #4b2b18; }
.lesson-documentation h2 { margin-bottom: 8px; }
.documentation-files { display: flex; flex-wrap: wrap; gap: 10px; margin-block: 16px; }
.documentation-files a { display: inline-flex; gap: 8px; align-items: center; padding: 9px 14px; border: 1px solid #b96525; border-radius: 7px; text-decoration: none; color: inherit; }
.documentation-hint { font: .85rem/1.5 system-ui, sans-serif; color: #756249; }
.documentation-plan summary { cursor: pointer; font-weight: 600; padding-block: 8px; }
@media(max-width: 550px) { .lesson-documentation { padding: 14px; } }
</style>
