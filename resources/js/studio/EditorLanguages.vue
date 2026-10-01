<script setup lang="ts">
import { ref } from 'vue';
import type { EditorIssue, LessonDocument, Messages, Readiness } from './types';
defineProps<{ document: LessonDocument; selected: string; readiness?: Readiness; stale?: boolean; messages: Messages }>();
defineEmits<{ select: [locale: string]; add: [locale: string]; remove: [locale: string]; focus: [issue: EditorIssue] }>();
const newLocale = ref('');
const removing = ref('');
</script>
<template>
    <section class="studio-card editor-languages"><h2>{{ messages.content_languages }}</h2><div class="language-chips"><button v-for="locale in document.locales" :key="locale" type="button" :aria-pressed="selected === locale" @click="$emit('select', locale)">{{ locale }} · {{ stale ? messages.readiness_pending : messages['translation_' + (readiness?.locales.find(item => item.locale === locale)?.status ?? 'draft')] }}</button></div><p v-if="stale" class="field-hint">{{ messages.readiness_pending_hint }}</p><div class="action-row"><label>{{ messages.add_content_language }}<input v-model="newLocale" maxlength="35" placeholder="de / en" /></label><button type="button" :disabled="!newLocale || document.locales.includes(newLocale)" @click="$emit('add', newLocale); newLocale = ''">{{ messages.add }}</button><button type="button" :disabled="selected === document.defaultLocale || document.locales.length <= 1" @click="removing = selected">{{ messages.remove_language }}</button></div><div v-if="removing" class="info-banner"><p>{{ messages.remove_language_confirm }} {{ removing }}</p><button type="button" @click="$emit('remove', removing); removing = ''">{{ messages.remove }}</button><button type="button" @click="removing = ''">{{ messages.cancel }}</button></div><p class="field-hint">{{ messages.translation_languages_hint }}</p><details v-if="readiness && !stale && readiness.locales.some(item => item.issues.length)"><summary>{{ messages.translation_issues }}</summary><ul><li v-for="issue in readiness.locales.flatMap(item => item.issues)" :key="issue.path"><button type="button" @click="$emit('focus', issue)">{{ issue.locale }} · {{ messages['issue_' + issue.code] ?? messages.issue_required_text }}</button></li></ul></details>
    </section>
</template>
