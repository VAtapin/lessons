<script setup lang="ts">
import type { Messages, ProjectedBlock } from './types';
import { ref } from 'vue';
defineProps<{ block?: ProjectedBlock | null; messages: Messages; returnUrl: string; student?: boolean }>();
const closeRequested = ref(false);
function closeWindow() {
    closeRequested.value = true;
    window.close();
}
</script>
<template>
    <section class="finished-lesson" aria-labelledby="finished-lesson-title">
        <span class="finished-star" aria-hidden="true">✦</span>
        <p class="eyebrow">{{ block?.content.eyebrow ?? messages.session_finished }}</p>
        <h1 id="finished-lesson-title">{{ block?.content.title ?? messages.session_finished }}</h1>
        <p class="finished-thanks plain-text">{{ block?.content.text ?? messages.lesson_finished_thanks }}</p>
        <blockquote v-if="block?.content.quote" class="summary-quote">{{ block.content.quote }}</blockquote>
        <small v-if="block?.content.source">{{ block.content.source }}</small>
        <button v-if="student" type="button" class="button-link finished-return" @click="closeWindow">{{ messages.lesson_close_window }}</button>
        <a v-else class="button-link finished-return" :href="returnUrl">{{ block?.content.label ?? messages.focus_return }}</a>
        <p v-if="student && closeRequested" role="status">{{ messages.lesson_close_window_hint }}</p>
    </section>
</template>
