import type { HistorySummary } from './types';

/** Keep the history API's latest-first order and use the original session IDs. */
export function openWorkspaceSessions(sessions: HistorySummary[]): HistorySummary[] {
    return sessions.filter(session => session.status !== 'finished').slice(0, 3);
}
