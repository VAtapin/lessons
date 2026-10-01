import { cloneEditor } from './editor-save';
import { newId } from './document';
import type { Block, LessonDocument, Stage } from './types';
export function copyBlock(block: Block): Block {
    const copy = cloneEditor(block);
    return { id: newId(), type: copy.type, schemaVersion: copy.schemaVersion, content: copy.content, config: copy.config, media: copy.media, ...(copy.solution !== undefined ? { solution: copy.solution } : {}), ...(copy.teacherNotes !== undefined ? { teacherNotes: copy.teacherNotes } : {}), ...(copy.origin !== undefined ? { origin: copy.origin } : {}) };
}
export function copyStage(stage: Stage): Stage {
    const blocks = stage.blocks.map(copyBlock);
    const ids = new Map(stage.blocks.map((block, index) => [block.id, blocks[index]!.id]));
    for (const block of blocks) if (block.type === 'core.presentation') {
        if (block.config.reviewBlockId) block.config.reviewBlockId = ids.get(block.config.reviewBlockId) ?? block.config.reviewBlockId;
        if (block.config.sourceBlockIds) block.config.sourceBlockIds = block.config.sourceBlockIds.map(id => ids.get(id) ?? id);
    }
    return { id: newId(), content: cloneEditor(stage.content), config: cloneEditor(stage.config), blocks };
}
function blank<T>(value: T, key = ''): T { if (typeof value === 'string') return (['optionId', 'itemId', 'roleId', 'modeId'].includes(key) ? value : '') as T; if (Array.isArray(value)) return value.map(item => blank(item)) as T; if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([name, item]) => [name, blank(item, name)])) as T; return value; }
export function blankOtherTranslations<T extends { content: Record<string, unknown> }>(item: T, selected: string): T { for (const locale of Object.keys(item.content)) if (locale !== selected) item.content[locale] = blank(item.content[locale]); return item; }
export function addContentLocale(document: LessonDocument, locale: string, sourceLocale: string): boolean {
    if (!/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/.test(locale) || locale.length > 35 || document.locales.includes(locale)) return false;
    document.locales.push(locale); document.content[locale] = blank(document.content[sourceLocale]!);
    for (const stage of document.stages) { stage.content[locale] = blank(stage.content[sourceLocale]!); for (const block of stage.blocks) { block.content[locale] = blank(block.content[sourceLocale]!); if (block.teacherNotes) block.teacherNotes[locale] = ''; } }
    return true;
}
export function removeContentLocale(document: LessonDocument, locale: string): boolean {
    if (locale === document.defaultLocale || document.locales.length <= 1 || !document.locales.includes(locale)) return false;
    document.locales = document.locales.filter(item => item !== locale); delete document.content[locale];
    if (document.documentation) delete document.documentation.content[locale];
    for (const stage of document.stages) { delete stage.content[locale]; for (const block of stage.blocks) { delete block.content[locale]; if (block.teacherNotes) delete block.teacherNotes[locale]; } }
    return true;
}
export const pointerSegment = (value: string): string => value.replaceAll('~', '~0').replaceAll('/', '~1');
