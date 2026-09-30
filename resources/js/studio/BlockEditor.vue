<script setup lang="ts">
import type { Block, Media, Messages } from './types';
import { newId } from './document';
import MediaPicker from './MediaPicker.vue';
const props = defineProps<{ block: Block; locale: string; locales: string[]; media: Media[]; messages: Messages }>();
const emit = defineEmits<{ edited: [] }>();
function addOption() {
    const optionId = newId();
    for (const locale of props.locales) props.block.content[locale]!.options!.push({ optionId, text: props.messages.template_option });
    emit('edited');
}
function removeOption(optionId: string) {
    for (const locale of props.locales) {
        const content = props.block.content[locale]!;
        content.options = content.options!.filter(option => option.optionId !== optionId);
    }
    if (props.block.solution?.optionId === optionId) props.block.solution = null;
    emit('edited');
}
function selectSolution(event: Event) {
    const optionId = (event.target as HTMLSelectElement).value;
    props.block.solution = optionId ? { optionId } : null;
}
</script>
<template>
    <label v-if="block.type === 'core.text'">{{ messages.text }}<textarea v-model="block.content[locale]!.text" rows="5" required /></label>
    <template v-else-if="block.type === 'core.image'">
        <MediaPicker :media="media" :current="block.media.image" :messages="messages" @select="block.media = { image: { assetId: $event.assetId, versionId: $event.versionId } }; $emit('edited')" />
        <p class="field-hint">{{ messages.media_notice }}</p>
        <label>{{ messages.alt }}<input v-model="block.content[locale]!.alt" required /></label>
        <label>{{ messages.caption }}<input v-model="block.content[locale]!.caption" /></label>
        <label>{{ messages.image_fit }}<select v-model="block.config.fit"><option value="contain">{{ messages.contain }}</option><option value="cover">{{ messages.cover }}</option></select></label>
    </template>
    <template v-else>
        <label>{{ messages.question }}<textarea v-model="block.content[locale]!.question" rows="2" required /></label>
        <div v-for="(option, index) in block.content[locale]!.options" :key="option.optionId" class="option-edit"><label>{{ messages.option }} {{ index + 1 }}<input v-model="option.text" required /></label><button type="button" class="icon-button" :aria-label="messages.remove_option" :disabled="block.content[locale]!.options!.length <= 2" @click="removeOption(option.optionId)">×</button></div>
        <button type="button" @click="addOption">＋ {{ messages.add_option }}</button>
        <label>{{ messages.solution }}<select :value="block.solution?.optionId ?? ''" @change="selectSolution"><option value="">{{ messages.no_solution }}</option><option v-for="option in block.content[locale]!.options" :key="option.optionId" :value="option.optionId">{{ option.text }}</option></select></label>
        <label class="checkbox-field"><input v-model="block.config.allowRepeat" type="checkbox" />{{ messages.allow_repeat }}</label>
    </template>
</template>
