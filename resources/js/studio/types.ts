export type Messages = Record<string, string>;
export interface Option { optionId: string; text: string }
export interface BlockContent { text?: string; alt?: string; caption?: string; question?: string; options?: Option[] }
export type BlockType = 'core.text' | 'core.image' | 'core.single-choice';
export interface Block {
    id: string; type: BlockType; schemaVersion: number; content: Record<string, BlockContent>;
    config: { format?: string; fit?: string; allowRepeat?: boolean };
    media: { image?: { assetId: string; versionId: string } };
    solution?: { optionId: string } | null;
    origin?: { templateId: string; versionId: string } | null;
}
export interface Stage { id: string; content: Record<string, { title: string; notes?: string }>; config: { layout?: string; durationSeconds?: number }; blocks: Block[] }
export interface LessonDocument { id: string; schemaVersion: number; defaultLocale: string; locales: string[]; content: Record<string, { title: string }>; stages: Stage[] }
export interface Lesson { id: string; revision: number; status: 'draft' | 'released'; versionId: string; document: LessonDocument }
export interface LessonSummary { id: string; title: string; revision: number; status: 'draft' | 'released'; updatedAt: string }
export interface Media { assetId: string; versionId: string; url: string; labelKey: string }
export interface ProjectedBlock extends Omit<Block, 'content'> { content: BlockContent }
export interface ProjectedStage extends Omit<Stage, 'content' | 'blocks'> { content: { title: string; notes?: string }; blocks: ProjectedBlock[] }
export interface TeacherState { id: string; revision: number; locale: string; currentStageId: string; document: { id: string; content: { title: string }; stages: ProjectedStage[] }; joinCode: string; projectorUrl: string; participants: { id: string; name: string }[]; answers: { participantId: string; blockId: string; optionId: string }[] }
export interface PublicState { id: string; revision: number; locale: string; currentStageId: string; stage: ProjectedStage; ownAnswers?: { blockId: string; optionId: string }[] }
export interface PageContext { lessonId?: string; sessionId?: string; projectorToken?: string }
