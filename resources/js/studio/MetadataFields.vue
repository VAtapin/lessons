<script setup lang="ts">
import { computed } from 'vue';
import { parseTags, rightsBases } from './library';
import type { Messages, Metadata, RightsBasis } from './types';
const props = defineProps<{ modelValue: Metadata; messages: Messages }>();
const emit = defineEmits<{ 'update:modelValue': [value: Metadata] }>();
function update(key: keyof Metadata, value: string | string[]) { emit('update:modelValue', { ...props.modelValue, [key]: value }); }
const tags = computed(() => props.modelValue.tags.join(', '));
</script>
<template>
    <div class="metadata-fields">
        <label>{{ messages.library_title }}<input :value="modelValue.title" required maxlength="200" @input="update('title', ($event.target as HTMLInputElement).value)" /></label>
        <label>{{ messages.tags }}<input :value="tags" @change="update('tags', parseTags(($event.target as HTMLInputElement).value))" /><small>{{ messages.tags_hint }}</small></label>
        <label>{{ messages.author }}<input :value="modelValue.author" required maxlength="500" @input="update('author', ($event.target as HTMLInputElement).value)" /></label>
        <label>{{ messages.source }}<textarea :value="modelValue.source" required maxlength="2000" rows="2" @input="update('source', ($event.target as HTMLTextAreaElement).value)" /></label>
        <label>{{ messages.rights_basis }}<select :value="modelValue.rightsBasis" @change="update('rightsBasis', ($event.target as HTMLSelectElement).value as RightsBasis)"><option v-for="basis in rightsBases" :key="basis" :value="basis">{{ messages['rights_' + basis] }}</option></select></label>
        <label>{{ messages.usage_rights }}<textarea :value="modelValue.usageRights" required maxlength="2000" rows="2" @input="update('usageRights', ($event.target as HTMLTextAreaElement).value)" /></label>
        <p class="field-hint">{{ messages.rights_hint }}</p>
    </div>
</template>
