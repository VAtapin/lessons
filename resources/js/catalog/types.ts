import type { ProjectedDocumentation } from '../studio/types';
export interface CatalogEntry {
    slug: string; title: string; description: string; locales: string[];
    age: string[]; topic: string[]; audience: string[]; format: string[];
    durationMinutes: number; coverUrl: string | null; versionId: string;
    coverWidth?: number | null; coverHeight?: number | null;
    stages?: { title: string; durationSeconds: number | null }[];
    details?: { goals: string[]; materials: string[]; devices: string; conditions: string };
    documentation?: ProjectedDocumentation | null;
}
export interface CatalogList {
    entries: CatalogEntry[];
    pagination: { page: number; total: number; perPage: number; lastPage: number };
}
export type CatalogMessages = Record<string, string>;
export type CatalogTermKind = 'age' | 'topic' | 'audience' | 'format';
export interface CatalogTerm {
    id: string; kind: CatalogTermKind; key: string; label: string; active: boolean; revision: number;
}
