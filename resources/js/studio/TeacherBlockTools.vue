<script setup lang="ts">
import { computed, reactive } from 'vue';
import { blockLabel, isInteractive } from './interactive';
import BlockRenderer from './BlockRenderer.vue';
import type { Messages, ProjectedStage, TeacherState } from './types';
const props = defineProps<{ session: TeacherState; stage: ProjectedStage; messages: Messages; disabled: boolean; moderationDisabled?: boolean }>();
const moderationDisabled = computed(() => props.moderationDisabled ?? props.disabled);
const emit = defineEmits<{ command: [action: string, payload: Record<string, unknown>] }>();
const blocks = computed(() => props.stage.blocks.filter(block => isInteractive(block.type) || (block.type === 'core.presentation' && !['scene', 'summary', 'closing'].includes(block.config.kind ?? ''))));
const projected = (blockId: string) => props.session.publicStage.blocks.find(block => block.id === blockId);
const roleDrafts = reactive<Record<string, string>>({});
const status = (blockId: string) => props.session.blockStates.find(item => item.blockId === blockId)?.status ?? 'prepared';
const tasks = computed(() => blocks.value.filter(block => block.type !== 'core.presentation'));
function canOpen(blockId: string) {
    if (!props.stage.config.sequentialTasks) return true;
    const index = tasks.value.findIndex(block => block.id === blockId);
    return tasks.value.slice(0, index).every(block => status(block.id) === 'revealed');
}
function openLabel(blockId: string) {
    return props.stage.config.sequentialTasks && tasks.value.findIndex(block => block.id === blockId) > 0 && status(blockId) === 'prepared'
        ? props.messages.next_task : props.messages.open_answers;
}
function assign(blockId: string, participantId: string) { const key = `${blockId}:${participantId}`; if (!(key in roleDrafts)) return; emit('command', 'role.assign', { blockId, participantId, roleId: roleDrafts[key] || null }); }
</script>
<template>
    <section v-if="blocks.length" class="studio-card block-tools"><h2>{{ messages.block_controls }}</h2><div v-for="block in blocks" :key="block.id" class="block-tool"><strong>{{ block.config.kind === 'picture-count' ? block.content.title : block.content.question ?? block.content.text ?? blockLabel(block.type, messages) }}</strong><span class="status-pill">{{ block.config.kind === 'picture-count' ? messages.picture_count_teacher : messages['block_' + status(block.id)] }}</span><div v-if="block.type !== 'core.presentation'" class="action-row"><button v-if="['prepared', 'closed'].includes(status(block.id))" :disabled="disabled || !canOpen(block.id)" @click="$emit('command', 'block.open', { blockId: block.id })">{{ openLabel(block.id) }}</button><button v-if="status(block.id) === 'open'" :disabled="disabled" @click="$emit('command', 'block.close', { blockId: block.id })">{{ messages.close_answers }}</button><button v-if="['open', 'closed'].includes(status(block.id))" :disabled="disabled" @click="$emit('command', 'block.review', { blockId: block.id })">{{ block.type === 'core.sequence' ? (block.content.submitLabel ?? messages.review_together) : messages.review_together }}</button></div>
        <BlockRenderer v-if="['core.roles', 'core.sequence', 'core.single-choice', 'core.presentation'].includes(block.type) && projected(block.id)" :block="projected(block.id)!" :messages="messages" presenter conducting :disabled="disabled" @command="(action, payload) => $emit('command', action, payload)" /><details v-if="block.type === 'core.roles'"><summary>{{ messages.assign_roles }}</summary><div v-for="participant in session.participants" :key="participant.id" class="role-assignment"><label>{{ participant.name }}<select :value="roleDrafts[block.id + ':' + participant.id] ?? session.answers.find(answer => answer.blockId === block.id && answer.participantId === participant.id)?.value.roleId ?? ''" :disabled="moderationDisabled || status(block.id) !== 'open'" @change="roleDrafts[block.id + ':' + participant.id] = ($event.target as HTMLSelectElement).value"><option value="">{{ messages.no_role }}</option><option v-for="role in block.content.roles" :key="role.roleId" :value="role.roleId">{{ role.text }}</option></select></label><button :disabled="moderationDisabled || status(block.id) !== 'open' || !((block.id + ':' + participant.id) in roleDrafts)" @click="assign(block.id, participant.id)">{{ messages.assign_role }}</button></div></details>
        <div v-if="block.type === 'core.signals'" class="signal-summary"><p>{{ messages.ready }}: {{ session.answers.filter(answer => answer.blockId === block.id && answer.value.ready).length }} / {{ session.participants.length }}</p><div v-for="answer in session.answers.filter(answer => answer.blockId === block.id && answer.value.question && !answer.acknowledged)" :key="answer.id" class="action-row"><span>{{ session.participants.find(participant => participant.id === answer.participantId)?.name }}</span><button :disabled="moderationDisabled" @click="$emit('command', 'signal.ack', { blockId: block.id, participantId: answer.participantId })">{{ messages.acknowledge_question }}</button></div></div>
    </div></section>
</template>
