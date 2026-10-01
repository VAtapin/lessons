import type { TeacherAnswer } from './types';
export interface ModerationDraft { text: string; revision: number; dirty: boolean }
export function initialModeration(answer: TeacherAnswer): ModerationDraft { return { text: answer.moderation?.displayText ?? answer.value.text ?? '', revision: answer.revision, dirty: false }; }
export function reconcileModeration(draft: ModerationDraft, answer: TeacherAnswer): ModerationDraft {
    if (!draft.dirty || (answer.revision > draft.revision && answer.moderation?.status === 'approved' && answer.moderation.displayText === draft.text)) return initialModeration(answer);
    return draft;
}
export function moderationPayload(answerId: number, draft: ModerationDraft, status: 'approved' | 'rejected'): Record<string, unknown> { return { answerId, expectedAnswerRevision: draft.revision, status, ...(status === 'approved' ? { displayText: draft.text } : {}) }; }
