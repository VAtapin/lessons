import type { RuntimeState } from './types';

export const WAVE_DURATION_MS = 3600;
export interface WaveStorage { getItem(key: string): string | null; setItem(key: string, value: string): void }

/** One celebration per server ID. Mark it before playing, including a page refresh. */
export function createWavePlayback(storage?: WaveStorage) {
    const seen = new Map<string, string>();
    return (state: RuntimeState): number => {
        if (state.status === 'finished' || !state.wave) return 0;
        const remaining = Date.parse(state.wave.expiresAt) - Date.parse(state.serverNow);
        if (!Number.isFinite(remaining) || remaining <= 0) return 0;
        const key = `wave:${state.id}`;
        let previous = seen.get(key);
        try { previous = storage?.getItem(key) ?? previous; } catch { /* In-memory guard remains available. */ }
        if (previous === state.wave.id) return 0;
        seen.set(key, state.wave.id);
        try { storage?.setItem(key, state.wave.id); } catch { /* Storage restrictions must not prevent a celebration. */ }
        return Math.min(WAVE_DURATION_MS, remaining);
    };
}
