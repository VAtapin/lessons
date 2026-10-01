export const filterOptions = {
    age: ['5-7', '8-10', '11-14', '15+', 'adults'],
    audience: ['school', 'sunday-school', 'family', 'group', 'children', 'adults'],
    topic: ['bible', 'parables', 'holidays', 'mercy', 'family', 'prayer'],
    format: ['lesson', 'presentation', 'game', 'worksheet', 'interactive', 'notes', 'questions'],
    duration: ['short', 'standard', 'long'],
} as const;
export type FilterKey = keyof typeof filterOptions;
export type CatalogFilters = Record<FilterKey | 'q', string>;
export function readFilters(search: string): CatalogFilters {
    const params = new URLSearchParams(search);
    const result: CatalogFilters = { q: params.get('q')?.slice(0, 120) ?? '', age: '', audience: '', topic: '', format: '', duration: '' };
    for (const key of Object.keys(filterOptions) as FilterKey[]) {
        const value = params.get(key) ?? '';
        if ((filterOptions[key] as readonly string[]).includes(value)) result[key] = value;
    }
    return result;
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
