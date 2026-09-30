<script setup lang="ts">
import { computed, ref } from 'vue';
import { selectableMedia } from './library';
import type { Media, Messages } from './types';
const props = defineProps<{ media: Media[]; current?: { assetId: string; versionId: string }; messages: Messages }>();
const emit = defineEmits<{ select: [media: Media] }>();
const query = ref('');
const choices = computed(() => selectableMedia(props.media, props.current).filter(item => `${item.title ?? ''} ${item.labelKey ? props.messages[item.labelKey] : ''}`.toLocaleLowerCase().includes(query.value.toLocaleLowerCase()) || item.versionId === props.current?.versionId));
const selected = computed(() => props.media.find(item => item.assetId === props.current?.assetId && item.versionId === props.current.versionId));
function select(event: Event) { const item = choices.value.find(item => item.versionId === (event.target as HTMLSelectElement).value); if (item) emit('select', item); }
</script>
<template>
    <div class="media-picker"><label>{{ messages.find_image }}<input v-model="query" type="search" @input.stop @change.stop /></label><label>{{ messages.image_version }}<select :value="current?.versionId ?? ''" required @change="select"><option disabled value="">{{ messages.select_image }}</option><option v-for="item in choices" :key="item.versionId" :value="item.versionId">{{ item.labelKey ? messages[item.labelKey] : item.title }} · {{ messages.version }} {{ item.versionNo ?? 1 }}{{ item.archived ? ' · ' + messages.archived : '' }}</option></select></label><img v-if="selected" :src="selected.url" alt="" class="media-thumbnail" /><p class="field-hint">{{ messages.pinned_image_hint }}</p></div>
</template>
