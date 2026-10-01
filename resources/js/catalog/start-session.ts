import type { HistorySummary } from '../studio/types';

export interface ActiveSessionPage { sessions: HistorySummary[]; nextCursor: string | null }

/** A read must succeed before creating a new real session; existing sessions require a choice. */
export async function catalogStartDecision<T>(loadActive: () => Promise<ActiveSessionPage>, start: () => Promise<T>): Promise<{ kind: 'resume'; page: ActiveSessionPage } | { kind: 'started'; result: T }> {
    const page = await loadActive();
    return page.sessions.length ? { kind: 'resume', page } : { kind: 'started', result: await start() };
}
