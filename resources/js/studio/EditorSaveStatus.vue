<script setup lang="ts">
import type { EditorIssue, Messages } from './types';
import type { SaveStatus } from './editor-save';
defineProps<{ status: SaveStatus; pending?: boolean; issues: EditorIssue[]; messages: Messages }>();
defineEmits<{ retry: []; focus: [issue: EditorIssue] }>();
</script>
<template>
    <div class="editor-save-status" :class="status" role="status"><strong>{{ messages['editor_' + status.replaceAll('-', '_')] }}</strong><p v-if="['offline','retry-required'].includes(status)">{{ messages.editor_retry_hint }}</p><button v-if="pending && ['offline','retry-required'].includes(status)" type="button" @click="$emit('retry')">{{ messages.retry_save }}</button><p v-if="status === 'conflict'">{{ messages.editor_conflict_hint }}</p><ul v-if="issues.length"><li v-for="issue in issues" :key="issue.path"><button type="button" @click="$emit('focus', issue)">{{ issue.locale }} · {{ messages['issue_' + issue.code] ?? messages.issue_invalid_field }}</button></li></ul></div>
</template>
