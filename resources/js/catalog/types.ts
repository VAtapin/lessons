export interface CatalogEntry {
    slug: string; title: string; description: string; locales: string[];
    age: string[]; topic: string[]; audience: string[]; format: string[];
    durationMinutes: number; coverUrl: string | null; versionId: string;
    stages?: { title: string; durationSeconds: number | null }[];
    details?: { goals: string[]; materials: string[]; devices: string; conditions: string };
}
export interface CatalogList {
    entries: CatalogEntry[];
    pagination: { page: number; total: number; perPage: number; lastPage: number };
}
export type CatalogMessages = Record<string, string>;
