/** Public projection never sends answers; a paused or disconnected student waits. */
export function canAnswerPublicSession(mode: 'student' | 'projector', status: string | undefined, connected: boolean, busy: boolean): boolean {
    return mode === 'student' && status === 'running' && connected && !busy;
}
