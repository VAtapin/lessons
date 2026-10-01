<script setup lang="ts">
import type { Messages, ProjectedBlock } from './types';
defineProps<{ block?: ProjectedBlock | null; messages: Messages; returnUrl: string }>();
</script>
<template>
    <section class="finished-lesson" aria-labelledby="finished-lesson-title">
        <span class="finished-star" aria-hidden="true">✦</span>
        <p class="eyebrow">{{ block?.content.eyebrow ?? messages.session_finished }}</p>
        <h1 id="finished-lesson-title">{{ block?.content.title ?? messages.session_finished }}</h1>
        <p class="finished-thanks plain-text">{{ block?.content.text ?? messages.lesson_finished_thanks }}</p>
        <blockquote v-if="block?.content.quote" class="summary-quote">{{ block.content.quote }}</blockquote>
        <small v-if="block?.content.source">{{ block.content.source }}</small>
        <a class="button-link finished-return" :href="returnUrl">{{ block?.content.label ?? messages.focus_return }}</a>
    </section>
</template>
