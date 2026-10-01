<script setup lang="ts">
import { pointerSegment } from './editor-document';
import { computed } from 'vue';
import { newId, move } from './document';
import type { Block, Messages, AnswerValue } from './types';
const props = defineProps<{ block: Block; locale: string; locales: string[]; messages: Messages; editorDraft?: boolean; pathBase?: string }>();
const field = (key: string) => props.pathBase ? `${props.pathBase}/content/${pointerSegment(props.locale)}/${key}` : undefined;
const emit = defineEmits<{ edited: [] }>();
const content = computed(() => props.block.content[props.locale]!);
type Collection = 'options' | 'items' | 'left' | 'right' | 'roles';
const groups = computed<Collection[]>(() => props.block.type === 'core.matching' ? ['left', 'right'] : props.block.type === 'core.sequence' ? ['items'] : props.block.type === 'core.roles' ? ['roles'] : content.value.options ? ['options'] : []);
function id(item: { optionId?: string; itemId?: string; roleId?: string }): string { return item.optionId ?? item.itemId ?? item.roleId!; }
function addOne(group: Collection) {
    const key = group === 'options' ? 'optionId' : group === 'roles' ? 'roleId' : 'itemId';
    const identifier = newId();
    for (const locale of props.locales) (props.block.content[locale]![group] as unknown as Record<string, string>[]).push({ [key]: identifier, text: props.editorDraft && locale !== props.locale ? '' : props.messages.template_option });
    if (group === 'roles') props.block.config.capacities![identifier] = 1;
    props.block.solution = null; emit('edited');
}
function add(group: Collection) { if (props.block.type === 'core.matching') { addOne('left'); addOne('right'); } else addOne(group); }
function remove(group: Collection, identifier: string) {
    const index = content.value[group]!.findIndex(item => id(item) === identifier);
    const otherGroup = group === 'left' ? 'right' : 'left';
    const pairedId = props.block.type === 'core.matching' ? id(content.value[otherGroup]![index]!) : '';
    for (const locale of props.locales) {
        const items = props.block.content[locale]![group]!;
        items.splice(items.findIndex(item => id(item) === identifier), 1);
        if (pairedId) { const other = props.block.content[locale]![otherGroup]!; other.splice(other.findIndex(item => id(item) === pairedId), 1); }
    }
    if (props.block.type === 'core.multiple-choice') { props.block.config.maxSelections = Math.min(props.block.config.maxSelections!, content.value.options!.length); props.block.config.minSelections = Math.min(props.block.config.minSelections!, props.block.config.maxSelections); }
    if (group === 'roles') delete props.block.config.capacities![identifier];
    props.block.solution = null; emit('edited');
}
function toggleSolution() { props.block.solution = props.block.solution ? null : props.block.type === 'core.multiple-choice' ? { optionIds: [] } : props.block.type === 'core.sequence' ? { itemIds: content.value.items!.map(item => item.itemId) } : { pairs: content.value.left!.map(item => ({ leftId: item.itemId, rightId: '' })) }; emit('edited'); }
function selectSolution(event: Event) { const optionId = (event.target as HTMLSelectElement).value; props.block.solution = optionId ? { optionId } : null; emit('edited'); }
const solution = computed(() => props.block.solution as AnswerValue);
</script>
<template>
    <label>{{ block.type === 'core.roles' || block.type === 'core.signals' ? messages.text : messages.question }}<textarea v-if="block.type === 'core.roles' || block.type === 'core.signals'" v-model="content.text" :data-editor-path="field('text')" :required="!editorDraft" rows="2" /><textarea v-else v-model="content.question" :data-editor-path="field('question')" :required="!editorDraft" rows="2" /></label>
    <fieldset v-for="group in groups" :key="group" class="editor-collection"><legend>{{ messages[group] }}</legend><div v-for="(item, index) in content[group]" :key="id(item)" class="option-edit"><label>{{ index + 1 }}<input v-model="item.text" :data-editor-path="field(group + '/' + index + '/text')" :required="!editorDraft" /></label><label v-if="group === 'roles'">{{ messages.capacity }}<input v-model.number="block.config.capacities![id(item)]" :data-editor-path="pathBase ? pathBase + '/config/capacities/' + pointerSegment(id(item)) : undefined" type="number" min="1" max="100" required /></label><button type="button" :aria-label="messages.remove_option" :disabled="content[group]!.length <= (group === 'roles' ? 1 : 2)" @click="remove(group, id(item))">×</button></div><button type="button" :disabled="content[group]!.length >= 12" @click="add(group)">＋ {{ messages.add_option }}</button></fieldset>
    <template v-if="block.type === 'core.single-choice'"><label>{{ messages.solution }}<select :value="block.solution?.optionId ?? ''" @change="selectSolution"><option value="">{{ messages.no_solution }}</option><option v-for="option in content.options" :key="option.optionId" :value="option.optionId">{{ option.text }}</option></select></label></template>
    <template v-if="['core.multiple-choice', 'core.sequence', 'core.matching'].includes(block.type)"><label class="checkbox-field"><input type="checkbox" :checked="!!block.solution" @change="toggleSolution" />{{ messages.solution }}</label><fieldset v-if="block.solution"><template v-if="block.type === 'core.multiple-choice'"><label v-for="option in content.options" :key="option.optionId" class="checkbox-field"><input v-model="solution.optionIds" type="checkbox" :value="option.optionId" />{{ option.text }}</label></template><div v-if="block.type === 'core.sequence'" v-for="(identifier, index) in solution.itemIds" :key="identifier" class="sequence-row"><span>{{ content.items!.find(item => item.itemId === identifier)?.text }}</span><button type="button" :disabled="index === 0" :aria-label="messages.move_up" @click="move(solution.itemIds!, index, -1)">↑</button><button type="button" :disabled="index === solution.itemIds!.length - 1" :aria-label="messages.move_down" @click="move(solution.itemIds!, index, 1)">↓</button></div><template v-if="block.type === 'core.matching'"><label v-for="pair in solution.pairs" :key="pair.leftId">{{ content.left!.find(item => item.itemId === pair.leftId)?.text }}<select v-model="pair.rightId" required><option value="">{{ messages.choose_match }}</option><option v-for="item in content.right" :key="item.itemId" :value="item.itemId">{{ item.text }}</option></select></label></template></fieldset></template>
    <div v-if="block.type === 'core.multiple-choice'" class="action-row"><label>{{ messages.min_selections }}<input v-model.number="block.config.minSelections" :data-editor-path="pathBase ? pathBase + '/config/minSelections' : undefined" type="number" min="1" :max="content.options!.length" required /></label><label>{{ messages.max_selections }}<input v-model.number="block.config.maxSelections" :data-editor-path="pathBase ? pathBase + '/config/maxSelections' : undefined" type="number" :min="block.config.minSelections" :max="content.options!.length" required /></label></div>
    <label v-if="block.type === 'core.free-response'">{{ messages.max_length }}<input v-model.number="block.config.maxLength" :data-editor-path="pathBase ? pathBase + '/config/maxLength' : undefined" type="number" min="1" max="1000" required /></label>
    <label v-if="!['core.roles', 'core.signals'].includes(block.type)" class="checkbox-field"><input v-model="block.config.allowRepeat" type="checkbox" />{{ messages.allow_repeat }}</label>
</template>
