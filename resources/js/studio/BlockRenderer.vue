<script setup lang="ts">
import type { Messages, ProjectedBlock } from './types';
defineProps<{ block: ProjectedBlock; messages: Messages; interactive?: boolean; selected?: string; disabled?: boolean }>();
defineEmits<{ answer: [blockId: string, optionId: string] }>();
const imageUrl = (block: ProjectedBlock) => block.resources?.image ?? '';
</script>

<template>
    <article class="render-block">
        <p v-if="block.type === 'core.text'" class="plain-text">{{ block.content.text }}</p>
        <figure v-else-if="block.type === 'core.image'">
            <img :src="imageUrl(block)" :alt="block.content.alt" :class="['block-image', block.config.fit === 'cover' ? 'cover' : '']" />
            <figcaption v-if="block.content.caption">{{ block.content.caption }}</figcaption>
        </figure>
        <fieldset v-else-if="block.type === 'core.single-choice'" class="choice-block" :disabled="disabled">
            <legend>{{ block.content.question }}</legend>
            <label v-for="option in block.content.options" :key="option.optionId" :class="['choice-option', { selected: selected === option.optionId }]">
                <input v-if="interactive" type="radio" :name="`answer-${block.id}`" :value="option.optionId" :checked="selected === option.optionId" @change="$emit('answer', block.id, option.optionId)" />
                <span v-else class="option-dot" aria-hidden="true"></span>
                <span>{{ option.text }}</span>
            </label>
            <small v-if="interactive && selected" class="answer-confirmed">{{ messages.answer_saved }}</small>
        </fieldset>
    </article>
</template>
