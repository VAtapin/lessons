import { ApiError } from './api';
import type { Block, LessonDocument, Metadata, Messages } from './types';
export type TermKind = 'age' | 'topic' | 'audience' | 'format';
export interface TaxonomyTerm { id: string; kind: TermKind; key: string; label?: string; labels?: Record<string, string>; active: boolean; revision: number }
export interface PublicationMetadata { translations: Record<string, { title: string; description: string }>; age: string[]; topic: string[]; audience: string[]; format: string[]; durationMinutes: number; cover?: { assetId: string; versionId: string } }
export interface Submission { id: string; lessonId: string; versionId: string; slug: string; revision: number; status: 'pending' | 'returned' | 'approved'; reason: string | null; metadata: PublicationMetadata; catalogSlug: string | null; submittedAt: string; reviewedAt: string | null }
export interface AdminCatalogEntry { slug: string; versionId: string; revision: number; status: string; metadata: PublicationMetadata }
export interface CommonTemplate { scope?: 'universal' | 'lesson'; id: string; templateId: string; revision: number; visible: boolean; versionId: string; locales: string[]; type: string; tags: string[]; attribution: Metadata; versions: { id: string; versionNo: number; locales: string[]; attribution: Metadata }[]; title: string; description: string; labels?: Record<string, { title: string; description: string }>; block?: Block; defaultLocale?: string }
export function plainCopy<T>(value: T): T { return JSON.parse(JSON.stringify(value)) as T; }
export function publicationMetadata(document: LessonDocument): PublicationMetadata {
    return { translations: Object.fromEntries(document.locales.map(locale => [locale, { title: document.content[locale]!.title, description: '' }])), age: [], topic: [], audience: [], format: [], durationMinutes: Math.max(1, Math.ceil(document.stages.reduce((total, stage) => total + (stage.config.durationSeconds ?? 0), 0) / 60)) };
}
export function submissionPayload(lessonId: string, versionId: string, revision: number, slug: string, metadata: PublicationMetadata) {
    return { lessonId, versionId, expectedLessonRevision: revision, slug: slug.trim(), metadata: plainCopy(metadata) };
}
export function reviewPayload(revision: number, decision: 'approve' | 'return', reason: string) {
    if (decision === 'return' && !reason.trim()) throw new Error('reason_required');
    return { expectedRevision: revision, decision, ...(decision === 'return' ? { reason: reason.trim() } : {}) };
}
export function adminError(error: unknown, messages: Messages): string {
    if (!(error instanceof ApiError)) return messages.admin_error;
    if (error.status === 401) return messages.admin_login_required;
    if (error.status === 403) return messages[error.code === 'verification_required' ? 'admin_verification_required' : 'admin_access_denied'];
    if (error.status === 409) return messages.admin_conflict;
    if (error.status === 422) return messages.admin_invalid;
    return messages.admin_error;
}
