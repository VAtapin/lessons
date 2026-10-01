<script setup lang="ts">
import { computed } from 'vue';
import { parseTags } from './library';
import type { Messages, Metadata } from './types';
const props = defineProps<{ modelValue: Metadata; messages: Messages; publicContext?: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: Metadata] }>();
function update(key: keyof Metadata, value: string | string[]) { emit('update:modelValue', { ...props.modelValue, [key]: value }); }
const tags = computed(() => props.modelValue.tags.join(', '));
</script>
<template>
    <div class="metadata-fields">
        <label>{{ messages.library_title }}<input :value="modelValue.title" required maxlength="200" @input="update('title', ($event.target as HTMLInputElement).value)" /></label>
        <label>{{ messages.tags }}<input :value="tags" @change="update('tags', parseTags(($event.target as HTMLInputElement).value))" /><small>{{ messages.tags_hint }}</small></label>
    </div>
</template>
