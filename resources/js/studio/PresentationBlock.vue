<script setup lang="ts">
import type { Messages, ProjectedBlock } from './types';
defineProps<{ block: ProjectedBlock; messages: Messages; presenter?: boolean; disabled?: boolean; conducting?: boolean }>();
defineEmits<{ command: [action: string, payload: Record<string, unknown>] }>();
</script>
<template>
    <div class="presentation-block">
        <template v-if="block.config.kind === 'reveal'">
            <button v-if="presenter" type="button" class="presentation-toggle" :disabled="disabled" :aria-expanded="!!block.runtime?.presentation?.visible" @click="$emit('command', 'presentation.toggle', { blockId: block.id })">{{ messages[block.runtime?.presentation?.visible ? 'presentation_hide' : 'presentation_show'] }}</button>
            <p v-if="!conducting || block.runtime?.presentation?.visible" class="presentation-revealed plain-text">{{ block.content.text }}</p>
        </template>
        <template v-else-if="block.config.kind === 'discussion'">
            <div v-if="presenter" class="discussion-modes"><button v-for="mode in block.content.modes" :key="mode.modeId" type="button" :class="{ selected: block.runtime?.presentation?.modeId === mode.modeId }" :aria-pressed="block.runtime?.presentation?.modeId === mode.modeId" :disabled="disabled" @click="$emit('command', 'presentation.mode', { blockId: block.id, modeId: mode.modeId })">{{ mode.label ?? mode.text }}</button><button type="button" :disabled="disabled" @click="$emit('command', 'timer.start', { seconds: 60 })">{{ messages.discussion_minute }}</button></div>
            <p class="presentation-revealed plain-text">{{ block.content.text }}</p>
            <p v-if="block.runtime?.presentation?.modeId" class="discussion-question plain-text">{{ block.content.modes?.find(mode => mode.modeId === block.runtime?.presentation?.modeId)?.text }}</p>
        </template>
        <template v-else-if="block.config.kind === 'response-board'">
            <p v-if="block.content.text" class="plain-text">{{ block.content.text }}</p>
            <div class="response-board"><template v-for="entry in block.runtime?.board" :key="entry.answerId"><button v-if="presenter" type="button" :class="['response-bubble', { discussed: entry.discussed }]" :aria-pressed="entry.discussed" :disabled="disabled" @click="$emit('command', 'board.toggle', { blockId: block.id, answerId: entry.answerId })"><span>{{ entry.text }}</span><small>{{ messages[entry.discussed ? 'board_discussed' : 'board_discuss'] }}</small></button><blockquote v-else :class="['response-bubble', { discussed: entry.discussed }]">{{ entry.text }}</blockquote></template></div>
            <p v-if="!block.runtime?.board?.length" class="field-hint">{{ messages.board_waiting }}</p>
        </template>
    </div>
</template>
