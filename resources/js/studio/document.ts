import { imageResources } from './library';
import type { Block, BlockType, LessonDocument, Media, ProjectedStage, Stage, Messages } from './types';

export const newId = () => crypto.randomUUID();
export function newBlock(type: BlockType, locales: string[], messages: Messages, media?: Media): Block {
    const first = newId(), second = newId(), rightFirst = newId(), rightSecond = newId();
    const options = [{ optionId: first, text: messages.template_option_first }, { optionId: second, text: messages.template_option_second }];
    const items = [{ itemId: first, text: messages.template_option_first }, { itemId: second, text: messages.template_option_second }];
    const content = Object.fromEntries(locales.map(locale => [locale,
        type === 'core.text' ? { title: '', text: messages.template_text, source: '' } : type === 'core.image' ? { alt: messages.template_alt, caption: '' } :
        type === 'core.prompt' || type === 'core.signals' ? { text: messages.template_text } : type === 'core.roles' ? { text: messages.template_text, roles: [{ roleId: first, text: messages.template_option_first }, { roleId: second, text: messages.template_option_second }] } :
        type === 'core.free-response' ? { question: messages.template_question } : type === 'core.sequence' ? { question: messages.template_question, items: structuredClone(items) } :
        type === 'core.matching' ? { question: messages.template_question, left: structuredClone(items), right: [{ itemId: rightFirst, text: messages.template_option_first }, { itemId: rightSecond, text: messages.template_option_second }] } : { question: messages.template_question, options: structuredClone(options) }
    ]));
    const config: Block['config'] = type === 'core.text' ? { presentation: 'paragraphs' } : type === 'core.image' ? { fit: 'contain' } : type === 'core.prompt' ? { kind: 'discussion', target: 'class' } :
        type === 'core.signals' ? {} : type === 'core.roles' ? { capacities: { [first]: 1, [second]: 1 } } : type === 'core.multiple-choice' ? { allowRepeat: false, minSelections: 1, maxSelections: 2 } : type === 'core.free-response' ? { allowRepeat: false, maxLength: 500 } : { allowRepeat: false };
    return { id: newId(), type, schemaVersion: type === 'core.text' ? 2 : 1, content, config,
        media: type === 'core.image' && media ? { image: { assetId: media.assetId, versionId: media.versionId } } : {}, solution: null, origin: null };
}
export function newStage(locales: string[], messages: Messages): Stage {
    return { id: newId(), content: Object.fromEntries(locales.map(locale => [locale, { title: messages.template_stage, notes: '' }])), config: { layout: 'vertical' }, blocks: [newBlock('core.text', locales, messages)] };
}
export function newDocument(locale: string, messages: Messages): LessonDocument {
    return { id: newId(), schemaVersion: 1, defaultLocale: locale, locales: [locale], content: { [locale]: { title: messages.template_title } }, stages: [newStage([locale], messages)] };
}
export function projectStage(stage: Stage, locale: string, media: Media[] = []): ProjectedStage {
    // Preview uses precisely the selected translation; text is always rendered by Vue interpolation.
    return { id: stage.id, config: stage.config, content: { title: stage.content[locale]!.title }, blocks: stage.blocks.map(block => ({ ...block, content: block.content[locale]!, solution: undefined, origin: undefined, teacherNotes: undefined, resources: imageResources(media, block.media.image?.assetId, block.media.image?.versionId) })) };
}
export function move<T>(items: T[], index: number, direction: number): void {
    const other = index + direction;
    if (other < 0 || other >= items.length) return;
    [items[index], items[other]] = [items[other]!, items[index]!];
}
