import type { TeacherActorState, TeacherCapability } from './types';
export interface TeacherCommand { commandId: string; expectedRevision: number; controlEpoch: number; action: string; payload: Record<string, unknown> }
export interface TeacherPending { actor: { kind: 'owner' | 'grant'; expiresAt?: string }; command: TeacherCommand }
const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
export function requiredCapability(action: string): TeacherCapability {
    if (action === 'finish') return 'finish';
    if (action.startsWith('invite.') || action.startsWith('grant.') || action.startsWith('presenter.')) return 'manageCollaboration';
    return ['answer.moderate', 'answer.publish', 'answer.unpublish', 'answer.reply', 'role.assign', 'signal.ack'].includes(action) ? 'moderate' : 'present';
}
export function canCommand(actor: TeacherActorState | undefined, action: string): boolean { return !!actor?.capabilities.includes(requiredCapability(action)); }
export function captureTeacherCommand(actor: TeacherActorState, revision: number, epoch: number, action: string, payload: Record<string, unknown> = {}): TeacherPending {
    return { actor: { kind: actor.kind, ...(actor.expiresAt ? { expiresAt: actor.expiresAt } : {}) }, command: { commandId: crypto.randomUUID(), expectedRevision: revision, controlEpoch: epoch, action, payload: structuredClone(payload) } };
}
export function sameTeacherAuthority(pending: TeacherPending, actor: TeacherActorState | undefined, epoch: number): boolean {
    return !!actor && pending.actor.kind === actor.kind && pending.actor.expiresAt === actor.expiresAt && pending.command.controlEpoch === epoch;
}
export function recoverTeacherCommand(serialized: string | null, actor: TeacherActorState, epoch: number): TeacherPending | undefined {
    if (!serialized || actor.kind !== 'owner') return;
    try {
        const value = JSON.parse(serialized) as TeacherPending;
        const body = value?.command;
        if (!body || !value.actor || !uuid.test(body.commandId) || !Number.isInteger(body.expectedRevision) || body.expectedRevision < 1
            || !Number.isInteger(body.controlEpoch) || body.controlEpoch < 0 || typeof body.action !== 'string'
            || !body.payload || typeof body.payload !== 'object' || Array.isArray(body.payload)
            || !sameTeacherAuthority(value, actor, epoch) || !canCommand(actor, body.action)) return;
        return value;
    } catch { return; }
}
export function controlChannel(sessionId: string, scope: 'owner' | 'grant'): string { return `lesson-control:${scope}:${sessionId}`; }
export function invitationToken(fragment: string): string | undefined {
    const token = new URLSearchParams(fragment.replace(/^#/, '')).get('token');
    return token && uuid.test(token) ? token : undefined;
}
/** Only known same-origin platform URLs may change interface language. */
export function localInterfaceUrl(value: string | undefined, locale: string, origin: string): string | undefined {
    if (!value) return;
    try {
        const url = new URL(value, origin);
        if (!['ru', 'de'].includes(locale) || url.origin !== origin || !/^\/(ru|de)\/(join|project|conduct|teacher-invitations)(\/|$)/.test(url.pathname)) return value;
        url.pathname = url.pathname.replace(/^\/(ru|de)\//, `/${locale}/`);
        return url.href;
    } catch { return value; }
}
