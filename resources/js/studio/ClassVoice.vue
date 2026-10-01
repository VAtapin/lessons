<script setup lang="ts">
import { computed } from 'vue';
import type { Messages, ProjectedBlock } from './types';
const props = defineProps<{ block: ProjectedBlock; messages: Messages }>();
const options = computed(() => props.block.type === 'core.roles'
    ? props.block.content.roles?.filter(role => props.block.runtime?.mode === 'rehearsal' || props.block.runtime?.presentation?.revealedRoleIds?.includes(role.roleId)).map(role => ({ optionId: role.roleId, text: role.text }))
    : props.block.content.options);
</script>
<template>
    <section v-if="block.runtime?.summary && block.runtime.summary.totalAnswers > 0" class="class-voice" :aria-label="messages.class_voice">
        <small>{{ messages.class_voice }} · {{ block.runtime.summary.totalAnswers }}</small>
        <div class="class-voice-bars">
            <template v-if="block.type === 'core.signals'"><div v-for="key in (['ready', 'question'] as const)" :key="key" class="class-voice-bar"><span>{{ (key === 'ready' ? block.content.readyLabel : block.content.questionLabel) || messages[key === 'ready' ? 'ready' : 'question_signal'] }}</span><strong>{{ block.runtime.summary[key] ?? 0 }}</strong><i :style="{ width: `${100 * (block.runtime.summary[key] ?? 0) / block.runtime.summary.totalAnswers}%` }" /></div></template>
            <template v-else><div v-for="option in options" :key="option.optionId" class="class-voice-bar"><span>{{ option.text }}</span><strong>{{ block.runtime.summary.counts?.find(item => item.optionId === option.optionId)?.count ?? 0 }}</strong><i :style="{ width: `${100 * (block.runtime.summary.counts?.find(item => item.optionId === option.optionId)?.count ?? 0) / block.runtime.summary.totalAnswers}%` }" /></div></template>
        </div>
    </section>
</template>
