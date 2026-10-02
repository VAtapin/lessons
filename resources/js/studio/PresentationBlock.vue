<script setup lang="ts">
import { computed } from 'vue';
import type { AnswerValue, Messages, OwnAnswer, ProjectedBlock } from './types';
const props = defineProps<{ block: ProjectedBlock; messages: Messages; presenter?: boolean; interactive?: boolean; answer?: OwnAnswer; disabled?: boolean; conducting?: boolean }>();
const emit = defineEmits<{ command: [action: string, payload: Record<string, unknown>]; answer: [blockId: string, value: AnswerValue] }>();
const selectedModeId = computed(() => props.interactive && props.block.config.kind !== 'picture-count' ? props.answer?.value.modeId : props.block.config.kind === 'personal-choice' ? undefined : props.block.runtime?.presentation?.modeId);
const selectedMode = computed(() => props.block.content.modes?.find(mode => mode.modeId === selectedModeId.value) ?? (props.block.config.kind === 'picture-count' ? props.block.content.modes?.[0] : undefined));
function selectMode(modeId: string) {
    if (props.disabled) return;
    if (props.presenter) emit('command', 'presentation.mode', { blockId: props.block.id, modeId });
    else if (props.interactive && props.block.config.kind !== 'picture-count') emit('answer', props.block.id, { modeId });
}
</script>
<template>
    <div class="presentation-block">
        <template v-if="block.config.kind === 'scene'">
            <p v-if="block.content.eyebrow" class="scene-eyebrow eyebrow">{{ block.content.eyebrow }}</p>
            <h2 v-if="block.content.title" class="scene-title">{{ block.content.title }}</h2>
            <p v-if="block.content.text !== block.content.title" class="scene-description scene-text plain-text">{{ block.content.text }}</p>
            <blockquote v-if="block.content.quote" class="scene-quote">{{ block.content.quote }}</blockquote>
            <p v-if="block.content.label && block.config.scene !== 'cover'" class="scene-label">{{ block.content.label }}</p>
            <h3 v-if="block.content.subtitle" class="scene-subtitle">{{ block.content.subtitle }}</h3>
            <p v-if="block.content.feedback" class="scene-feedback">{{ block.content.feedback }}</p>
            <small v-if="block.content.source" class="scene-source">{{ block.content.source }}</small>
        </template>
        <template v-else-if="block.config.kind === 'picture-count'">
            <h2 class="scene-title">{{ selectedMode?.title ?? block.content.title }}</h2>
            <p class="scene-description">{{ selectedMode?.text }}</p>
            <div class="picture-count" :style="{ '--picture-columns': Math.min(selectedMode?.count ?? 0, 5) || 1 }"><img v-for="n in selectedMode?.count ?? 0" :key="n" :src="block.resources?.image" :alt="block.content.text" /></div>
            <div v-if="presenter" class="discussion-modes"><button v-for="mode in block.content.modes" :key="mode.modeId" type="button" :aria-pressed="selectedMode?.modeId === mode.modeId" :disabled="disabled" @click="selectMode(mode.modeId)">{{ mode.label ?? mode.text }}</button></div>
        </template>
        <template v-else-if="block.config.kind === 'summary'">
            <p v-if="block.content.eyebrow" class="summary-eyebrow eyebrow">{{ block.content.eyebrow }}</p>
            <h2 class="summary-heading">{{ block.content.title }}</h2>
            <p class="summary-lead">{{ block.content.text }}</p>
            <ol class="takeaway-path"><li v-for="(item, index) in block.content.items" :key="item.itemId"><span class="takeaway-index">{{ index + 1 }}</span><strong>{{ item.label }}</strong><p>{{ item.text }}</p></li></ol>
            <blockquote v-if="block.content.quote" class="summary-quote">{{ block.content.quote }}</blockquote>
            <small v-if="block.content.source" class="summary-source">{{ block.content.source }}</small>
            <p v-if="block.content.subtitle" class="summary-subtitle">{{ block.content.subtitle }}</p>
        </template>
        <template v-else-if="block.config.kind === 'reveal'">
            <button v-if="presenter" type="button" class="presentation-toggle" :disabled="disabled" :aria-expanded="!!block.runtime?.presentation?.visible" @click="$emit('command', 'presentation.toggle', { blockId: block.id })">{{ block.runtime?.presentation?.visible ? (block.content.hideLabel ?? messages.presentation_hide) : (block.content.label ?? messages.presentation_show) }}</button>
            <p v-if="!conducting || block.runtime?.presentation?.visible" class="presentation-revealed plain-text">{{ block.content.text }}</p>
            <blockquote v-if="block.content.quote && (!conducting || block.runtime?.presentation?.visible)" class="scene-quote">{{ block.content.quote }}</blockquote>
            <small v-if="block.content.source && (!conducting || block.runtime?.presentation?.visible)" class="scene-source">{{ block.content.source }}</small>
        </template>
        <template v-else-if="block.config.kind === 'personal-choice'">
            <p class="presentation-revealed plain-text">{{ block.content.text }}</p>
            <div v-if="interactive" class="discussion-modes"><button v-for="mode in block.content.modes" :key="mode.modeId" type="button" :class="{ selected: selectedModeId === mode.modeId }" :aria-pressed="selectedModeId === mode.modeId" :disabled="disabled" @click="selectMode(mode.modeId)">{{ mode.label ?? mode.text }}</button></div>
        </template>
        <template v-else-if="block.config.kind === 'discussion'">
            <div class="discussion-modes"><button v-for="mode in block.content.modes" :key="mode.modeId" type="button" :data-mode="mode.modeId" :class="{ selected: selectedModeId === mode.modeId }" :aria-pressed="selectedModeId === mode.modeId" :disabled="disabled || (!presenter && !interactive)" @click="selectMode(mode.modeId)">{{ mode.label ?? mode.text }}</button><button v-if="presenter" class="discussion-minute" type="button" :disabled="disabled" @click="$emit('command', 'timer.start', { seconds: 60 })">{{ messages.discussion_minute }}</button></div>
            <p v-if="block.content.text" class="presentation-revealed plain-text">{{ block.content.text }}</p>
            <div v-if="selectedMode" class="discussion-question"><h3 v-if="selectedMode.title">{{ selectedMode.title }}</h3><p class="plain-text">{{ selectedMode.text }}</p></div>
        </template>
        <template v-else-if="block.config.kind === 'response-board'">
            <p v-if="block.content.text" class="plain-text">{{ block.content.text }}</p>
            <div class="response-board"><template v-for="entry in block.runtime?.board" :key="entry.answerId"><button v-if="presenter" type="button" :class="['response-bubble', { discussed: entry.discussed }]" :aria-pressed="entry.discussed" :disabled="disabled" @click="$emit('command', 'board.toggle', { blockId: block.id, answerId: entry.answerId })"><span>{{ entry.text }}</span><small>{{ messages[entry.discussed ? 'board_discussed' : 'board_discuss'] }}</small></button><blockquote v-else :class="['response-bubble', { discussed: entry.discussed }]">{{ entry.text }}</blockquote></template></div>
            <p v-if="!block.runtime?.board?.length && block.content.emptyText !== block.content.text" class="field-hint">{{ block.content.emptyText ?? messages.board_waiting }}</p>
        </template>
    </div>
</template>
