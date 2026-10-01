import type { Media, Metadata } from './types';
export const rightsBases = ['self_created', 'permission', 'public_domain', 'licensed', 'ai_generated'] as const;
export function emptyMetadata(): Metadata { return { title: '', tags: [], author: '', source: '', rightsBasis: 'unspecified', usageRights: '' }; }
export function metadataOf(value: Metadata): Metadata { return { title: value.title, tags: [...value.tags], author: value.author, source: value.source, rightsBasis: value.rightsBasis, usageRights: value.usageRights }; }
export function parseTags(value: string): string[] { return [...new Set(value.split(',').map(tag => tag.trim()).filter(Boolean))]; }
export function compatibleLocales(available: string[], target: string[]): boolean { return target.length > 0 && target.every(locale => available.includes(locale)); }
export function selectableMedia(media: Media[], current?: { assetId: string; versionId: string }): Media[] {
    return media.filter(item => !item.archived || (item.assetId === current?.assetId && item.versionId === current.versionId));
}
export function fileProblem(file: Pick<File, 'size' | 'type'>, maxFileBytes: number): string | undefined {
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) return 'invalid_media';
    if (file.size < 1 || file.size > maxFileBytes) return 'file_too_large';
}
export function formatBytes(bytes: number): string { return `${(bytes / 1024 / 1024).toFixed(1)} MiB`; }
export function imageResources(media: Media[], assetId?: string, versionId?: string): { image?: string } {
    const item = media.find(item => item.assetId === assetId && item.versionId === versionId);
    return item ? { image: item.url } : {};
}
