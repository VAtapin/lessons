/** Readiness/help works after joining; ordinary tasks wait for the teacher to begin. */
export function canAnswerPublicSession(mode: 'student' | 'projector', status: string | undefined, connected: boolean, busy: boolean, blockType?: string): boolean {
    return mode === 'student' && connected && !busy && (status === 'running' || (status === 'prepared' && blockType === 'core.signals'));
}
