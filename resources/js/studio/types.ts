export type Messages = Record<string, string>;
export interface Option { optionId: string; text: string }
export interface Item { itemId: string; text: string }
export interface Role { roleId: string; text: string }
export interface AnswerValue { optionId?: string; optionIds?: string[]; text?: string; itemIds?: string[]; pairs?: { leftId: string; rightId: string }[]; roleId?: string | null; ready?: boolean; question?: boolean }
export interface BlockContent { title?: string; source?: string; text?: string; alt?: string; caption?: string; question?: string; options?: Option[]; items?: Item[]; left?: Item[]; right?: Item[]; roles?: Role[] }
export type BlockType = 'core.text' | 'core.image' | 'core.prompt' | 'core.single-choice' | 'core.multiple-choice' | 'core.poll' | 'core.free-response' | 'core.sequence' | 'core.matching' | 'core.roles' | 'core.signals';
export interface Block {
    id: string; type: BlockType; schemaVersion: number; content: Record<string, BlockContent>;
    config: { format?: string; fit?: string; allowRepeat?: boolean; presentation?: string; kind?: string; target?: string; minSelections?: number; maxSelections?: number; maxLength?: number; capacities?: Record<string, number> };
    media: { image?: { assetId: string; versionId: string } };
    solution?: AnswerValue | null; teacherNotes?: Record<string, string>;
    origin?: { templateId: string; versionId: string } | null;
}
export interface Stage { id: string; content: Record<string, { title: string; notes?: string }>; config: { layout?: string; durationSeconds?: number }; blocks: Block[] }
export interface LessonDocument { id: string; schemaVersion: number; defaultLocale: string; locales: string[]; content: Record<string, { title: string }>; stages: Stage[] }
export interface Lesson { id: string; revision: number; status: 'draft' | 'released'; versionId: string; document: LessonDocument }
export interface LessonSummary { id: string; title: string; revision: number; status: 'draft' | 'released'; updatedAt: string }
export interface Media { assetId: string; versionId: string; url: string; labelKey?: string; title?: string; versionNo?: number; mime?: string; bytes?: number; width?: number; height?: number; archived?: boolean }
export type BlockStatus = 'prepared' | 'open' | 'closed' | 'revealed';
export interface BlockRuntime { status: BlockStatus; attemptNo: number; availability?: { roleId: string; used: number; capacity: number }[]; results?: AnswerValue & { counts?: { optionId: string; count: number }[]; totalAnswers?: number; published?: { text: string }[] } }
export interface OwnAnswer { id: number; revision: number; blockId: string; attemptNo: number; optionId?: string; value: AnswerValue; status: string; grade: boolean | null; acknowledged?: boolean }
export interface TeacherAnswer extends Omit<OwnAnswer, 'status'> { participantId: string; moderation: { status: 'pending' | 'approved' | 'rejected'; displayText: string | null; published: boolean } | null; acknowledged: boolean }
export interface ProjectedBlock extends Omit<Block, 'content' | 'teacherNotes'> { content: BlockContent; teacherNotes?: string; resources?: { image?: string }; runtime?: BlockRuntime }
export interface ProjectedStage extends Omit<Stage, 'content' | 'blocks'> { content: { title: string; notes?: string }; blocks: ProjectedBlock[] }
export interface RuntimeState { id: string; status: 'prepared' | 'running' | 'paused' | 'finished'; serverNow: string; timer: { status: 'idle' | 'running' | 'paused' | 'expired'; endsAt: string | null; remainingSeconds: number }; message: string | null; wave: { id: string; expiresAt: string } | null }
export interface TeacherState extends RuntimeState { id: string; revision: number; locale: string; currentStageId: string; document: { id: string; content: { title: string }; stages: ProjectedStage[] }; joinCode: string; joinUrl: string; publicStage: ProjectedStage; projectorUrl: string; participants: { id: string; name: string; connected: boolean; lastSeenAt: string | null }[]; answers: TeacherAnswer[]; blockStates: { blockId: string; status: BlockStatus; attemptNo: number }[] }
export interface PublicState extends RuntimeState { id: string; revision: number; locale: string; currentStageId: string; stage: ProjectedStage; ownAnswers?: OwnAnswer[] }
export interface PageContext { lessonId?: string; sessionId?: string; projectorToken?: string }
export type RightsBasis = 'self_created' | 'permission' | 'public_domain' | 'licensed' | 'ai_generated';
export interface Metadata { title: string; tags: string[]; author: string; source: string; rightsBasis: RightsBasis; usageRights: string }
export interface Usage { lessonId?: string; lessonVersionId?: string; templateId?: string; templateVersionId?: string; title: string; status?: string; blockId?: string }
export interface MediaQuota { usedBytes: number; limitBytes: number; maxFileBytes: number }
export interface MediaAsset extends Metadata { id: string; revision: number; currentVersionId: string; archived: boolean; versions: Media[]; usages: Usage[] }
export interface MediaResponse { media: Media[]; assets: MediaAsset[]; quota: MediaQuota }
export interface TemplateSummary { id: string; title: string; tags: string[]; type: BlockType; locales: string[]; revision: number; currentVersionId: string; archived: boolean }
export interface TemplateVersion { id: string; versionNo: number; locales: string[]; defaultLocale: string; block: Block; attribution: Metadata; createdAt: string }
export interface TemplateDetail extends TemplateSummary, Metadata { versions: TemplateVersion[]; usages: Usage[] }
