<script setup lang="ts">
import type { Block, Media, Messages } from './types';
import InteractiveBlockEditor from './InteractiveBlockEditor.vue';
import MediaPicker from './MediaPicker.vue';
const props = defineProps<{ block: Block; locale: string; locales: string[]; media: Media[]; messages: Messages }>();
const emit = defineEmits<{ edited: [] }>();
function notes(event: Event) { if ((event.target as HTMLInputElement).checked) props.block.teacherNotes = Object.fromEntries(props.locales.map(locale => [locale, ''])); else delete props.block.teacherNotes; emit('edited'); }
</script>
<template>
    <template v-if="block.type === 'core.text'">
        <template v-if="block.schemaVersion === 2"><label>{{ messages.block_title }}<input v-model="block.content[locale]!.title" /></label><label>{{ messages.presentation }}<select v-model="block.config.presentation"><option v-for="kind in ['paragraphs','list','quote']" :key="kind" :value="kind">{{ messages[kind] }}</option></select></label></template>
        <label>{{ messages.text }}<textarea v-model="block.content[locale]!.text" rows="5" required /></label><label v-if="block.schemaVersion === 2">{{ messages.text_source }}<input v-model="block.content[locale]!.source" /></label>
    </template>
    <template v-else-if="block.type === 'core.image'">
        <MediaPicker :media="media" :current="block.media.image" :messages="messages" @select="block.media = { image: { assetId: $event.assetId, versionId: $event.versionId } }; $emit('edited')" />
        <p class="field-hint">{{ messages.media_notice }}</p><label>{{ messages.alt }}<input v-model="block.content[locale]!.alt" required /></label><label>{{ messages.caption }}<input v-model="block.content[locale]!.caption" /></label><label>{{ messages.image_fit }}<select v-model="block.config.fit"><option value="contain">{{ messages.contain }}</option><option value="cover">{{ messages.cover }}</option></select></label>
    </template>
    <template v-else-if="block.type === 'core.prompt'"><label>{{ messages.text }}<textarea v-model="block.content[locale]!.text" rows="3" required /></label><label>{{ messages.prompt_kind }}<select v-model="block.config.kind"><option v-for="kind in ['discussion','instruction','reflection']" :key="kind" :value="kind">{{ messages[kind] }}</option></select></label><label>{{ messages.prompt_target }}<select v-model="block.config.target"><option v-for="target in ['class','pair','group']" :key="target" :value="target">{{ messages['target_'+target] }}</option></select></label></template>
    <InteractiveBlockEditor v-else :block="block" :locale="locale" :locales="locales" :messages="messages" @edited="$emit('edited')" />
    <details class="block-teacher-notes"><summary>{{ messages.block_notes }}</summary><label class="checkbox-field"><input type="checkbox" :checked="!!block.teacherNotes" @change="notes" />{{ messages.add_teacher_notes }}</label><label v-if="block.teacherNotes">{{ messages.block_notes }}<textarea v-model="block.teacherNotes[locale]" maxlength="5000" rows="3" /></label><p class="field-hint">{{ messages.private_notes_hint }}</p></details>
</template>
