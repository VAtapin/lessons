import type { Block, BlockType, LessonDocument, Media, ProjectedStage, Stage, Messages } from './types';

export const newId = () => crypto.randomUUID();
export function newBlock(type: BlockType, locales: string[], messages: Messages, media?: Media): Block {
    const first = newId();
    const second = newId();
    const content = Object.fromEntries(locales.map(locale => [locale,
        type === 'core.text' ? { text: messages.template_text } : type === 'core.image' ? { alt: messages.template_alt, caption: '' } : {
            question: messages.template_question, options: [{ optionId: first, text: messages.template_option_first }, { optionId: second, text: messages.template_option_second }],
        },
    ]));
    return { id: newId(), type, schemaVersion: 1, content,
        config: type === 'core.text' ? { format: 'plain' } : type === 'core.image' ? { fit: 'contain' } : { allowRepeat: false },
        media: type === 'core.image' && media ? { image: { assetId: media.assetId, versionId: media.versionId } } : {}, solution: null, origin: null,
    };
}
export function newStage(locales: string[], messages: Messages): Stage {
    return { id: newId(), content: Object.fromEntries(locales.map(locale => [locale, { title: messages.template_stage, notes: '' }])), config: { layout: 'vertical' }, blocks: [newBlock('core.text', locales, messages)] };
}
export function newDocument(locale: string, messages: Messages): LessonDocument {
    return { id: newId(), schemaVersion: 1, defaultLocale: locale, locales: [locale], content: { [locale]: { title: messages.template_title } }, stages: [newStage([locale], messages)] };
}
export function projectStage(stage: Stage, locale: string): ProjectedStage {
    // Preview uses precisely the selected translation; text is always rendered by Vue interpolation.
    return { id: stage.id, config: stage.config, content: { title: stage.content[locale]!.title }, blocks: stage.blocks.map(block => ({ ...block, content: block.content[locale]!, solution: undefined, origin: undefined })) };
}
export function move<T>(items: T[], index: number, direction: number): void {
    const other = index + direction;
    if (other < 0 || other >= items.length) return;
    [items[index], items[other]] = [items[other]!, items[index]!];
}
