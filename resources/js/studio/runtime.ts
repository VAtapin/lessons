import type { RuntimeState } from './types';

export interface Command { commandId: string; expectedRevision: number; action: string; payload: Record<string, unknown> }
export function command(revision: number, action: string, payload: Record<string, unknown> = {}): Command {
    return { commandId: crypto.randomUUID(), expectedRevision: revision, action, payload };
}
export function recoverCommand(serialized: string | null): Command | undefined {
    if (!serialized) return;
    try {
        const value = JSON.parse(serialized);
        if (!value || !/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value.commandId)
            || !Number.isInteger(value.expectedRevision) || value.expectedRevision < 1
            || !['begin', 'pause', 'resume', 'finish', 'stage', 'timer.start', 'timer.pause', 'timer.resume', 'timer.clear', 'message.set', 'message.clear', 'wave'].includes(value.action)
            || !value.payload || typeof value.payload !== 'object' || Array.isArray(value.payload)) return;
        return value as Command;
    } catch { return; }
}
export function remainingSeconds(state: RuntimeState, elapsedMilliseconds: number): number {
    if (state.timer.status !== 'running' || !state.timer.endsAt) return state.timer.remainingSeconds;
    return Math.max(0, Math.ceil((Date.parse(state.timer.endsAt) - Date.parse(state.serverNow) - Math.max(0, elapsedMilliseconds)) / 1000));
}
export function formatTimer(seconds: number): string {
    return `${Math.floor(seconds / 60).toString().padStart(2, '0')}:${(seconds % 60).toString().padStart(2, '0')}`;
}
export function waveIsFresh(state: RuntimeState, seenId: string | null): boolean {
    return waveIsActive(state) && state.wave!.id !== seenId;
}
export function waveIsActive(state: RuntimeState): boolean {
    return state.status !== 'finished' && !!state.wave && Date.parse(state.wave.expiresAt) > Date.parse(state.serverNow);
}
export const heartbeatTimeout = 6500;
export function connectedControl(instance: string | null, lastHeartbeat: number, now: number): boolean {
    return !!instance && lastHeartbeat > 0 && now - lastHeartbeat < heartbeatTimeout;
}
export function acceptProjection(started: number, generation: number, busy: boolean, previousRevision: number | undefined, incomingRevision: number): boolean {
    return started === generation && !busy && (previousRevision === undefined || incomingRevision >= previousRevision);
}
export function matchesControlMessage(expected: string | null, value: unknown): value is { instance: string; type: 'ready' | 'heartbeat' | 'return' | 'closed' | 'restore' | 'returnAck' } {
    if (!expected || !value || typeof value !== 'object') return false;
    const candidate = value as { instance?: unknown; type?: unknown };
    return candidate.instance === expected && ['ready', 'heartbeat', 'return', 'closed', 'restore', 'returnAck'].includes(String(candidate.type));
}
export function controlReturnConfirmed(expected: string | null, value: unknown, returning: boolean): boolean {
    return matchesControlMessage(expected, value) && (value.type === 'restore' || (returning && value.type === 'returnAck'));
}
