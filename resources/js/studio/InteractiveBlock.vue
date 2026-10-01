<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { answerComplete, defaultAnswer, ownValue, reconcileAnswer, valueText } from './interactive';
import { move } from './document';
import type { AnswerValue, Messages, OwnAnswer, ProjectedBlock } from './types';
const clone = (value: AnswerValue): AnswerValue => JSON.parse(JSON.stringify(value));
const props = defineProps<{ block: ProjectedBlock; messages: Messages; interactive?: boolean; answer?: OwnAnswer; disabled?: boolean }>();
const emit = defineEmits<{ answer: [blockId: string, value: AnswerValue] }>();
const draft = ref<AnswerValue>(props.answer ? clone(ownValue(props.answer)) : defaultAnswer(props.block));
const dirty = ref(false);
watch(() => props.answer, incoming => { const reconciled = reconcileAnswer(draft.value, dirty.value, incoming); draft.value = reconciled.value; dirty.value = reconciled.dirty; }, { deep: true });
const locked = computed(() => props.disabled || (!!props.block.runtime && props.block.runtime.status !== 'open') || (!!props.answer && !props.block.config.allowRepeat && !['core.roles', 'core.signals'].includes(props.block.type)));
const results = computed(() => props.block.runtime?.results);
function submit() { if (!locked.value && answerComplete(props.block, draft.value)) emit('answer', props.block.id, clone(draft.value)); }
function choose(optionId: string) { draft.value = { optionId }; dirty.value = true; submit(); }
function unavailable(roleId: string) { const capacity = props.block.runtime?.availability?.find(item => item.roleId === roleId); return !!capacity && capacity.used >= capacity.capacity && ownValue(props.answer).roleId !== roleId; }
</script>
<template>
    <form class="interactive-block" @submit.prevent="submit" @input="dirty = true" @change="dirty = true">
        <fieldset :disabled="interactive && locked"><legend>{{ block.content.question ?? block.content.text }}</legend>
            <p v-if="block.runtime" class="block-state" role="status">{{ messages['block_' + block.runtime.status] }}</p>
            <template v-if="block.content.options"><label v-for="option in block.content.options" :key="option.optionId" :class="['choice-option', { selected: draft.optionId === option.optionId || draft.optionIds?.includes(option.optionId) }]">
                <input v-if="interactive && block.type === 'core.multiple-choice'" v-model="draft.optionIds" type="checkbox" :value="option.optionId" />
                <input v-else-if="interactive" type="radio" :name="'answer-' + block.id" :checked="draft.optionId === option.optionId" @change="choose(option.optionId)" />
                <span v-else class="option-dot" aria-hidden="true"></span><span>{{ option.text }}</span><strong v-if="results?.counts">{{ results.counts.find(item => item.optionId === option.optionId)?.count ?? 0 }}</strong>
            </label><p v-if="interactive && block.type === 'core.multiple-choice'" class="field-hint">{{ messages.selection_range }} {{ block.config.minSelections }}–{{ block.config.maxSelections }}</p></template>
            <label v-if="interactive && block.type === 'core.free-response'">{{ messages.your_answer }}<textarea v-model="draft.text" required rows="4" /><small>{{ Array.from(draft.text ?? '').length }} / {{ block.config.maxLength }}</small></label>
            <template v-if="block.type === 'core.sequence'"><div v-for="(identifier, index) in draft.itemIds" :key="identifier" class="sequence-row"><span>{{ index + 1 }}. {{ block.content.items!.find(item => item.itemId === identifier)?.text }}</span><template v-if="interactive"><button type="button" :disabled="index === 0" :aria-label="messages.move_up" @click="move(draft.itemIds!, index, -1); dirty = true">↑</button><button type="button" :disabled="index === draft.itemIds!.length - 1" :aria-label="messages.move_down" @click="move(draft.itemIds!, index, 1); dirty = true">↓</button></template></div></template>
            <template v-if="block.type === 'core.matching'"><template v-if="interactive"><label v-for="pair in draft.pairs" :key="pair.leftId">{{ block.content.left!.find(item => item.itemId === pair.leftId)?.text }}<select v-model="pair.rightId" required><option value="">{{ messages.choose_match }}</option><option v-for="item in block.content.right" :key="item.itemId" :value="item.itemId" :disabled="draft.pairs!.some(other => other.leftId !== pair.leftId && other.rightId === item.itemId)">{{ item.text }}</option></select></label></template><div v-else class="matching-columns"><ul><li v-for="item in block.content.left" :key="item.itemId">{{ item.text }}</li></ul><ul><li v-for="item in block.content.right" :key="item.itemId">{{ item.text }}</li></ul></div></template>
            <template v-if="block.type === 'core.roles'"><label v-for="role in block.content.roles" :key="role.roleId" class="choice-option"><input v-if="interactive" v-model="draft.roleId" type="radio" :name="'role-' + block.id" :value="role.roleId" :disabled="unavailable(role.roleId)" /><span>{{ role.text }}</span><small v-if="block.runtime?.availability">{{ block.runtime.availability.find(item => item.roleId === role.roleId)?.used }} / {{ block.runtime.availability.find(item => item.roleId === role.roleId)?.capacity }}</small></label><label v-if="interactive" class="choice-option"><input v-model="draft.roleId" type="radio" :name="'role-' + block.id" :value="null" />{{ messages.no_role }}</label></template>
            <template v-if="interactive && block.type === 'core.signals'"><label class="checkbox-field"><input v-model="draft.ready" type="checkbox" />{{ messages.ready }}</label><label class="checkbox-field"><input v-model="draft.question" type="checkbox" />{{ messages.question_signal }}</label><p v-if="answer?.acknowledged" role="status">{{ messages.question_acknowledged }}</p></template>
            <button v-if="interactive && !['core.single-choice', 'core.poll'].includes(block.type)" class="primary" :disabled="locked || !answerComplete(block, draft)">{{ messages.submit_answer }}</button>
        </fieldset>
        <p v-if="answer" class="answer-confirmed" role="status">{{ messages.answer_saved }}<span v-if="answer.status !== 'submitted'"> · {{ messages['moderation_' + answer.status] }}</span><span v-if="answer.grade !== null"> · {{ messages[answer.grade ? 'answer_correct' : 'answer_incorrect'] }}</span></p>
        <div v-if="results" class="block-results"><h3>{{ messages.results }}</h3><p v-if="results.totalAnswers !== undefined">{{ messages.answers_received }}: {{ results.totalAnswers }}</p><p v-if="results.optionId || results.optionIds || results.itemIds || results.pairs" class="plain-text">{{ messages.revealed_answers }}: {{ valueText(block.content, results, messages) }}</p><blockquote v-for="(entry, index) in results.published" :key="index" class="plain-text">{{ entry.text }}</blockquote></div>
    </form>
</template>
