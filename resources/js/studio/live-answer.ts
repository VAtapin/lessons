import type { TeacherAnswer, TeacherState } from './types';

/** Two existing, revision-checked commands; a failed/uncertain approval never publishes. */
export async function reviewAndPublish(answer: TeacherAnswer, stageId: string, read: () => TeacherState | undefined, send: (action: string, payload: Record<string, unknown>) => Promise<boolean | undefined>): Promise<void> {
    const initial = read();
    if (initial?.currentStageId !== stageId || !answer.moderation || answer.moderation.status === 'rejected') return;
    let revision = answer.revision;
    const text = answer.moderation.status === 'approved' ? answer.moderation.displayText : answer.value.text;
    if (!text) return;
    if (answer.moderation.status === 'pending') {
        if (await send('answer.moderate', { answerId: answer.id, expectedAnswerRevision: revision, status: 'approved', displayText: text }) !== true) return;
        revision++;
    }
    const current = read();
    const approved = current?.answers.find(item => item.id === answer.id);
    if (current?.currentStageId !== stageId || approved?.revision !== revision || approved.moderation?.status !== 'approved' || approved.moderation.displayText !== text || approved.moderation.published) return;
    await send('answer.publish', { answerId: answer.id, expectedAnswerRevision: revision });
}

export function liveAnswer(answers: TeacherAnswer[], dismissed: Set<string>): TeacherAnswer | undefined {
    return answers.slice().reverse().find(answer => !dismissed.has(`${answer.id}:${answer.revision}`) && (answer.moderation?.status === 'pending' || (answer.value.question && !answer.acknowledged)));
}
