<script setup lang="ts">
import BlockRenderer from './BlockRenderer.vue';
import type { AnswerValue, Messages, OwnAnswer, ProjectedStage } from './types';
defineProps<{ stage: ProjectedStage; messages: Messages; interactive?: boolean; answers?: OwnAnswer[]; busy?: boolean }>();
defineEmits<{ answer: [blockId: string, value: AnswerValue] }>();
</script>
<template><section class="stage-renderer"><h2>{{ stage.content.title }}</h2><BlockRenderer v-for="block in stage.blocks" :key="block.id" :block="block" :messages="messages" :interactive="interactive" :answer="answers?.find(answer => answer.blockId === block.id)" :disabled="busy" @answer="(blockId, value) => $emit('answer', blockId, value)" /></section></template>
