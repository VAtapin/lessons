<script setup lang="ts">
import BlockRenderer from './BlockRenderer.vue';
import type { Messages, ProjectedStage } from './types';
defineProps<{ stage: ProjectedStage; messages: Messages; interactive?: boolean; answers?: { blockId: string; optionId: string }[]; busy?: boolean }>();
defineEmits<{ answer: [blockId: string, optionId: string] }>();
</script>
<template>
    <section class="stage-renderer">
        <h2>{{ stage.content.title }}</h2>
        <BlockRenderer v-for="block in stage.blocks" :key="block.id" :block="block" :messages="messages" :interactive="interactive" :selected="answers?.find(answer => answer.blockId === block.id)?.optionId" :disabled="busy || (!!answers?.some(answer => answer.blockId === block.id) && !block.config.allowRepeat)" @answer="(blockId, optionId) => $emit('answer', blockId, optionId)" />
    </section>
</template>
