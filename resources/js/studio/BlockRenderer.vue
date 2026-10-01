<script setup lang="ts">
import InteractiveBlock from './InteractiveBlock.vue';
import PresentationBlock from './PresentationBlock.vue';
import type { AnswerValue, Messages, OwnAnswer, ProjectedBlock } from './types';
defineProps<{ block: ProjectedBlock; messages: Messages; interactive?: boolean; answer?: OwnAnswer; disabled?: boolean; conducting?: boolean; presenter?: boolean }>();
defineEmits<{ answer: [blockId: string, value: AnswerValue]; command: [action: string, payload: Record<string, unknown>] }>();
</script>
<template>
    <article class="render-block">
        <template v-if="block.type === 'core.text'"><h3 v-if="block.content.title">{{ block.content.title }}</h3><ul v-if="block.config.presentation === 'list'" class="text-list"><li v-for="(line, index) in block.content.text?.split('\n').filter(line => line.trim())" :key="index">{{ line }}</li></ul><blockquote v-else-if="block.config.presentation === 'quote'" class="plain-text">{{ block.content.text }}</blockquote><p v-else class="plain-text">{{ block.content.text }}</p><small v-if="block.content.source" class="text-source">{{ block.content.source }}</small></template>
        <figure v-else-if="block.type === 'core.image'"><img :src="block.resources?.image ?? ''" :alt="block.content.alt" :class="['block-image', block.config.fit === 'cover' ? 'cover' : '']" /><figcaption v-if="block.content.caption">{{ block.content.caption }}</figcaption></figure>
        <div v-else-if="block.type === 'core.prompt'" class="prompt-block"><p class="eyebrow">{{ messages[block.config.kind ?? 'discussion'] }} · {{ messages['target_' + (block.config.target ?? 'class')] }}</p><p class="plain-text">{{ block.content.text }}</p></div>
        <PresentationBlock v-else-if="block.type === 'core.presentation'" :conducting="conducting" :block="block" :messages="messages" :presenter="presenter" :disabled="disabled" @command="(action, payload) => $emit('command', action, payload)" /><InteractiveBlock v-else :block="block" :messages="messages" :interactive="interactive" :presenter="presenter" :answer="answer" :disabled="disabled" :conducting="conducting" @answer="(blockId, value) => $emit('answer', blockId, value)" @command="(action, payload) => $emit('command', action, payload)" />
    </article>
</template>
