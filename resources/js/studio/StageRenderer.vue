<script setup lang="ts">
import BlockRenderer from './BlockRenderer.vue';
import '../../css/conducting-interactions.css';
import { computed, ref } from 'vue';
import { focusStageBlocks } from './conducting-focus';
import type { AnswerValue, Messages, OwnAnswer, ProjectedStage } from './types';
const props = defineProps<{ stage: ProjectedStage; messages: Messages; interactive?: boolean; answers?: OwnAnswer[]; busy?: boolean; focus?: boolean; presenter?: boolean; prepared?: boolean }>();
const focused = computed(() => focusStageBlocks(props.stage));
const wholeIllustration = ref(false);
defineEmits<{ answer: [blockId: string, value: AnswerValue]; command: [action: string, payload: Record<string, unknown>] }>();
</script>
<template>
    <section v-if="focus" :class="['stage-renderer', 'focus-stage', { 'focus-stage-split': focused.split, 'focus-stage-cover': focused.cover, 'focus-media-first': focused.mediaFirst }]"><div class="focus-stage-copy"><h2>{{ stage.content.title }}</h2><div class="stage-blocks"><BlockRenderer v-for="block in focused.copy" :key="block.id" class="stage-block" :block="block" :messages="messages" :conducting="focus" :interactive="interactive" :presenter="presenter" :answer="answers?.find(answer => answer.blockId === block.id)" :disabled="busy || (prepared && block.type !== 'core.signals')" @answer="(blockId, value) => $emit('answer', blockId, value)" @command="(action, payload) => $emit('command', action, payload)" /></div></div><div v-if="focused.illustration" :class="['focus-stage-visual', { 'show-whole-illustration': wholeIllustration }]"><BlockRenderer :block="focused.illustration" :messages="messages" /><button type="button" class="focus-image-fit" :aria-pressed="wholeIllustration" @click="wholeIllustration = !wholeIllustration">{{ messages[wholeIllustration ? 'focus_image_fill' : 'focus_image_whole'] }}</button></div></section>
    <section v-else :class="['stage-renderer', 'layout-' + (stage.config.layout ?? 'vertical') ]"><h2>{{ stage.content.title }}</h2><div class="stage-blocks"><BlockRenderer v-for="block in stage.blocks" :key="block.id" :class="['stage-block', { 'material-block': ['core.text', 'core.image'].includes(block.type), 'task-block': !['core.text', 'core.image'].includes(block.type) }]" :block="block" :messages="messages" :conducting="focus" :interactive="interactive" :presenter="presenter" :answer="answers?.find(answer => answer.blockId === block.id)" :disabled="busy || (prepared && block.type !== 'core.signals')" @answer="(blockId, value) => $emit('answer', blockId, value)" @command="(action, payload) => $emit('command', action, payload)" /></div></section>
</template>
