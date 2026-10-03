import type { CatalogMessages, CatalogTerm, CatalogTermKind } from './types';
export const filterOptions = {
    age: ['5-7', '8-10', '11-14', '15+', 'adults'],
    audience: ['school', 'sunday-school', 'family', 'group', 'children', 'adults'],
    topic: ['bible', 'parables', 'holidays', 'mercy', 'family', 'prayer'],
    format: ['lesson', 'presentation', 'game', 'worksheet', 'interactive', 'notes'],
    duration: ['short', 'standard', 'long'],
} as const;
export type FilterKey = keyof typeof filterOptions;
export type CatalogFilters = Record<FilterKey | 'q', string>;
export function taxonomyOptions(terms: CatalogTerm[] | null, messages: CatalogMessages): Record<CatalogTermKind, { key: string; label: string }[]> {
    const result = {} as Record<CatalogTermKind, { key: string; label: string }[]>;
    for (const kind of ['age', 'audience', 'topic', 'format'] as const) {
        const seeds: readonly string[] = filterOptions[kind];
        const available = terms === null
            ? seeds.map(key => ({ key, label: messages[`${kind}_${key}`] ?? key }))
            : terms.filter(term => term.kind === kind && term.active && /^[a-z0-9+-]{1,80}$/.test(term.key) && typeof term.label === 'string');
        result[kind] = [...available].sort((a, b) => {
            const left = seeds.indexOf(a.key), right = seeds.indexOf(b.key);
            return (left < 0 ? seeds.length : left) - (right < 0 ? seeds.length : right);
        }).map(term => ({ key: term.key, label: term.label }));
    }
    return result;
}
export function readFilters(search: string, terms: CatalogTerm[] | null = null): CatalogFilters {
    const params = new URLSearchParams(search);
    const result: CatalogFilters = { q: params.get('q')?.slice(0, 120) ?? '', age: '', audience: '', topic: '', format: '', duration: '' };
    for (const key of Object.keys(filterOptions) as FilterKey[]) {
        const value = params.get(key) ?? '';
        const available = key === 'duration' || terms === null
            ? filterOptions[key] as readonly string[]
            : taxonomyOptions(terms, {})[key].map(term => term.key);
        if (available.includes(value)) result[key] = value;
    }
    return result;
}
export function catalogPageQuery(search: string, terms: CatalogTerm[] | null = null): URLSearchParams {
    const query = new URLSearchParams(filterQuery(readFilters(search, terms)));
    const page = new URLSearchParams(search).get('page');
    if (page && /^[1-9]\d*$/.test(page) && Number.isSafeInteger(Number(page))) query.set('page', page);
    return query;
}
export function filterQuery(filters: CatalogFilters): string {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) if (value.trim()) params.set(key, value.trim());
    return params.toString();
}
export function catalogLink(locale: string, key?: FilterKey, value?: string): string {
    const params = new URLSearchParams();
    if (key && value) params.set(key, value);
    return `/${locale}/catalog${params.size ? `?${params}` : ''}`;
}
