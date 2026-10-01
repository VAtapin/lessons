<script setup lang="ts">
import BlockRenderer from './BlockRenderer.vue';
import type { AnswerValue, Messages, OwnAnswer, ProjectedStage } from './types';
defineProps<{ stage: ProjectedStage; messages: Messages; interactive?: boolean; answers?: OwnAnswer[]; busy?: boolean }>();
defineEmits<{ answer: [blockId: string, value: AnswerValue] }>();
</script>
<template><section :class="['stage-renderer', 'layout-' + (stage.config.layout ?? 'vertical') ]"><h2>{{ stage.content.title }}</h2><div class="stage-blocks"><BlockRenderer v-for="block in stage.blocks" :key="block.id" :class="['stage-block', { 'material-block': ['core.text', 'core.image'].includes(block.type), 'task-block': !['core.text', 'core.image'].includes(block.type) }]" :block="block" :messages="messages" :interactive="interactive" :answer="answers?.find(answer => answer.blockId === block.id)" :disabled="busy" @answer="(blockId, value) => $emit('answer', blockId, value)" /></div></section></template>
