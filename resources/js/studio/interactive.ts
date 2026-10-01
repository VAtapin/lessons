import type { AnswerValue, Block, BlockContent, BlockType, Messages, OwnAnswer } from './types';
export const blockTypes: BlockType[] = ['core.text', 'core.image', 'core.prompt', 'core.single-choice', 'core.multiple-choice', 'core.poll', 'core.free-response', 'core.sequence', 'core.matching', 'core.roles', 'core.signals'];
export function blockLabel(type: BlockType, messages: Messages): string { return messages[type.replace('core.', '').replaceAll('-', '_')] ?? type; }
export function isInteractive(type: BlockType): boolean { return !['core.text', 'core.image', 'core.prompt'].includes(type); }
export function ownValue(answer?: OwnAnswer): AnswerValue { return answer?.value ?? (answer?.optionId ? { optionId: answer.optionId } : {}); }
export function sameValue(first: AnswerValue, second: AnswerValue): boolean {
    const normalized = (value: AnswerValue) => value.optionIds ? { optionIds: [...value.optionIds].sort() } : value.pairs ? { pairs: [...value.pairs].sort((a, b) => a.leftId < b.leftId ? -1 : a.leftId > b.leftId ? 1 : 0).map(pair => ({ leftId: pair.leftId, rightId: pair.rightId })) } : value;
    return JSON.stringify(normalized(first)) === JSON.stringify(normalized(second));
}
export function reconcileAnswer(value: AnswerValue, dirty: boolean, incoming?: OwnAnswer): { value: AnswerValue; dirty: boolean } {
    if (incoming && (!dirty || sameValue(ownValue(incoming), value))) return { value: JSON.parse(JSON.stringify(ownValue(incoming))), dirty: false };
    return { value, dirty };
}
export function valueText(content: BlockContent, value: AnswerValue, messages: Messages): string {
    const option = (id: string) => content.options?.find(item => item.optionId === id)?.text ?? id;
    if (value.optionId) return option(value.optionId);
    if (value.optionIds) return value.optionIds.map(option).join('; ');
    if (value.text !== undefined) return value.text;
    if (value.itemIds) return value.itemIds.map(id => content.items?.find(item => item.itemId === id)?.text ?? id).join(' → ');
    if (value.pairs) return value.pairs.map(pair => `${content.left?.find(item => item.itemId === pair.leftId)?.text ?? pair.leftId} — ${content.right?.find(item => item.itemId === pair.rightId)?.text ?? pair.rightId}`).join('; ');
    if ('roleId' in value) return content.roles?.find(item => item.roleId === value.roleId)?.text ?? messages.no_role;
    if ('ready' in value) return `${messages.ready}: ${messages[value.ready ? 'yes' : 'no']}; ${messages.question_signal}: ${messages[value.question ? 'yes' : 'no']}`;
    return '';
}
export function defaultAnswer(block: Pick<Block, 'type'> & { content: BlockContent }): AnswerValue {
    switch (block.type) {
        case 'core.multiple-choice': return { optionIds: [] };
        case 'core.free-response': return { text: '' };
        case 'core.sequence': return { itemIds: block.content.items?.map(item => item.itemId) ?? [] };
        case 'core.matching': return { pairs: block.content.left?.map(item => ({ leftId: item.itemId, rightId: '' })) ?? [] };
        case 'core.roles': return { roleId: null };
        case 'core.signals': return { ready: false, question: false };
        default: return {};
    }
}
export function responseTextValid(text: string, maxLength: number): boolean { return !!text.trim() && Array.from(text).length <= maxLength; }
export function answerComplete(block: Pick<Block, 'type' | 'config'> & { content: BlockContent }, value: AnswerValue): boolean {
    if (['core.single-choice', 'core.poll'].includes(block.type)) return !!block.content.options?.some(option => option.optionId === value.optionId);
    if (block.type === 'core.multiple-choice') { const ids = value.optionIds ?? []; return ids.every(id => block.content.options?.some(option => option.optionId === id)) && new Set(ids).size === ids.length && ids.length >= (block.config.minSelections ?? 1) && ids.length <= (block.config.maxSelections ?? 2); }
    if (block.type === 'core.free-response') return responseTextValid(value.text ?? '', block.config.maxLength ?? 500);
    if (block.type === 'core.sequence') { const ids = value.itemIds ?? []; return ids.every(id => block.content.items?.some(item => item.itemId === id)) && ids.length === block.content.items?.length && new Set(ids).size === ids.length; }
    if (block.type === 'core.matching') { const pairs = value.pairs ?? []; return pairs.length === block.content.left?.length && pairs.every(pair => block.content.left?.some(item => item.itemId === pair.leftId) && block.content.right?.some(item => item.itemId === pair.rightId)) && new Set(pairs.map(pair => pair.leftId)).size === pairs.length && new Set(pairs.map(pair => pair.rightId)).size === pairs.length; }
    return true;
}
