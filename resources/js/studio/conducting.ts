import type { ProjectedStage, TeacherState } from './types';
export function activeStageAnswers(answers: TeacherState['answers'], stage: ProjectedStage | undefined): TeacherState['answers'] {
    if (!stage) return [];
    const blockIds = new Set(stage.blocks.map(block => block.id));
    return answers.filter(answer => blockIds.has(answer.blockId));
}
export function countAnsweredParticipants(answers: TeacherState['answers']): number {
    return new Set(answers.map(answer => answer.participantId)).size;
}
