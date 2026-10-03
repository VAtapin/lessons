export type Messages = Record<string, string>;
export interface Option { optionId: string; text: string }
export interface Item { itemId: string; text: string; label?: string; icon?: string }
export interface Role { roleId: string; text: string }
export interface AnswerValue { modeId?: string; optionId?: string; optionIds?: string[]; text?: string; itemIds?: string[]; pairs?: { leftId: string; rightId: string }[]; roleId?: string | null; ready?: boolean; question?: boolean }
export interface BlockContent { table?: { headers: string[]; rows: string[][] }; title?: string; source?: string; text?: string; alt?: string; caption?: string; question?: string; eyebrow?: string; subtitle?: string; quote?: string; label?: string; placeholder?: string; submitLabel?: string; emptyText?: string; feedback?: string; readyLabel?: string; questionLabel?: string; actionLabel?: string; hideLabel?: string; resetLabel?: string; restartLabel?: string; resetText?: string; feedbackFirstWrong?: string; feedbackWrong?: string; feedbackCorrect?: string; feedbackComplete?: string; reviewLabel?: string; options?: Option[]; items?: Item[]; left?: Item[]; right?: Item[]; roles?: Role[]; modes?: { modeId: string; text: string; label?: string; title?: string; count?: number }[] }
export type BlockType = 'core.text' | 'core.image' | 'core.prompt' | 'core.single-choice' | 'core.multiple-choice' | 'core.poll' | 'core.free-response' | 'core.sequence' | 'core.matching' | 'core.roles' | 'core.signals' | 'core.presentation';
export interface Block {
    id: string; type: BlockType; schemaVersion: number; content: Record<string, BlockContent>;
    config: { format?: string; fit?: string; allowRepeat?: boolean; presentation?: string; kind?: string; variant?: string; scene?: string; imageSide?: string; target?: string; minSelections?: number; maxSelections?: number; maxLength?: number; capacities?: Record<string, number>; reviewBlockId?: string | null; sourceBlockIds?: string[]; maxItems?: number };
    media: { image?: { assetId: string; versionId: string } };
    solution?: AnswerValue | null; teacherNotes?: Record<string, string>;
    origin?: { templateId: string; versionId: string } | null;
}
export interface Stage { id: string; content: Record<string, { title: string; notes?: string }>; config: { theme?: 'green' | 'terracotta' | 'slate' | 'lavender' | 'ocean' | 'berry' | 'cobalt' | 'plum' | 'copper' | 'indigo' | 'rose' | 'graphite'; layout?: string; durationSeconds?: number; openTasks?: boolean; sequentialTasks?: boolean; closeOnTimer?: boolean; answerSeconds?: number }; blocks: Block[] }
export interface DocumentationFile { fileId: string; kind: 'plan' | 'presentation'; locale: string; url?: string; bytes?: number; label?: string }
export interface TeacherDocumentation { schemaVersion: 1; content: Record<string, { plan: string }>; files: DocumentationFile[]; video?: { id: string; locale: string } }
export interface ProjectedDocumentation { plan: string | null; files: DocumentationFile[]; video: { id: string; locale: string } | null }
export interface LessonDocument { id: string; schemaVersion: number; defaultLocale: string; locales: string[]; content: Record<string, { title: string }>; stages: Stage[]; documentation?: TeacherDocumentation }
export interface Lesson { id: string; revision: number; status: 'draft' | 'released'; versionId: string; document: LessonDocument }
export interface EditorIssue { code: string; path: string; locale?: string; stageId?: string; blockId?: string }
export interface Readiness { defaultLocale: string; readyLocales: string[]; locales: { locale: string; status: 'draft' | 'partial' | 'ready'; issues: EditorIssue[] }[] }
export interface EditorLesson extends Lesson { readiness: Readiness }
export interface SaveRequest { saveId: string; expectedRevision: number; document: LessonDocument }
export interface SaveResponse { lesson: EditorLesson; acknowledgedSaveId: string; appliedRevision: number; appliedVersionId: string }
export interface LessonSummary { id: string; title: string; revision: number; status: 'draft' | 'released'; updatedAt: string; favorite?: boolean; archived: boolean; archivedAt?: string | null }
export interface Media { assetId: string; versionId: string; url: string; labelKey?: string; title?: string; versionNo?: number; mime?: string; bytes?: number; width?: number; height?: number; archived?: boolean }
export type BlockStatus = 'prepared' | 'open' | 'closed' | 'revealed';
export interface BlockRuntime { status: BlockStatus; attemptNo: number; mode?: 'lesson' | 'rehearsal'; summary?: { totalAnswers: number; ready?: number; question?: number; counts?: { optionId: string; count: number }[] }; availability?: { roleId: string; used: number; capacity: number }[]; presentation?: { revealedRoleIds?: string[]; roleReset?: boolean; itemIds?: string[]; optionId?: string; feedback?: 'incorrect' | 'correct' | 'complete'; visible?: boolean; modeId?: string }; board?: { answerId: number; text: string; discussed: boolean }[]; results?: AnswerValue & { counts?: { optionId: string; count: number }[]; totalAnswers?: number; published?: { text: string }[] } }
export interface OwnAnswer { id: number; revision: number; blockId: string; attemptNo: number; optionId?: string; value: AnswerValue; status: string; grade: boolean | null; acknowledged?: boolean; privateReply?: string }
export interface TeacherAnswer extends Omit<OwnAnswer, 'status'> { participantId: string; moderation: { status: 'pending' | 'approved' | 'rejected'; displayText: string | null; published: boolean } | null; acknowledged: boolean }
export interface ProjectedBlock extends Omit<Block, 'content' | 'teacherNotes'> { content: BlockContent; teacherNotes?: string; resources?: { image?: string }; runtime?: BlockRuntime }
export interface ProjectedStage extends Omit<Stage, 'content' | 'blocks'> { content: { title: string; notes?: string }; blocks: ProjectedBlock[] }
export interface RuntimeState { id: string; status: 'prepared' | 'running' | 'paused' | 'finished'; serverNow: string; timer: { status: 'idle' | 'running' | 'paused' | 'expired'; endsAt: string | null; remainingSeconds: number }; message: string | null; joinProjection?: { code: string; url: string } | null; closing?: ProjectedBlock | null; wave: { id: string; expiresAt: string; aggregate?: { points: number; participants: number } } | null }
export interface TeacherState extends RuntimeState { mode: 'lesson' | 'rehearsal'; joinProjectionVisible?: boolean; id: string; revision: number; locale: string; currentStageId: string; document: { id: string; content: { title: string }; stages: ProjectedStage[]; documentation?: ProjectedDocumentation }; joinCode?: string; joinUrl?: string; publicStage: ProjectedStage; projectorUrl?: string; participants: { id: string; name: string; connected: boolean; lastSeenAt: string | null }[]; answers: TeacherAnswer[]; blockStates: { blockId: string; status: BlockStatus; attemptNo: number }[] }
export interface PublicState extends RuntimeState { id: string; revision: number; locale: string; currentStageId: string; stage: ProjectedStage; ownAnswers?: OwnAnswer[]; kindnessPoints?: number }
export interface PageContext { lessonId?: string; sessionId?: string; projectorToken?: string; resetToken?: string; email?: string; audience?: 'student' | 'projector'; teacherScope?: 'grant' }
export type TeacherCapability = 'present' | 'moderate' | 'finish' | 'manageCollaboration';
export interface TeacherActorState { kind: 'owner' | 'grant'; isPresenter: boolean; capabilities: TeacherCapability[]; expiresAt?: string }
export interface TeacherInvitationInfo { id: string; expiresAt: string; acceptedAt: string | null; revokedAt: string | null }
export interface TeacherGrantInfo { id: string; displayName: string; expiresAt: string; revokedAt: string | null; isPresenter: boolean }
export interface CollaborationState { controlEpoch: number; presenter: { kind: 'owner' | 'grant' | 'vacant'; grantId?: string; displayName?: string }; invitations?: TeacherInvitationInfo[]; grants?: TeacherGrantInfo[] }
export interface TeacherActorResponse { session: TeacherState; actor: TeacherActorState; collaboration: CollaborationState; acknowledgedCommandId?: string; invitation?: { id: string; expiresAt: string; url: string } }
export interface User { id: number; name: string; email: string; verified: boolean; uiLocale: string; isAdmin?: boolean }
export interface Account { user: User | null; guestClaimAvailable: boolean; quota: MediaQuota }
export interface ClaimCounts { lessons: number; templates: number; mediaAssets: number; sessions: number }
export interface GuestClaim { claim: { available: boolean; status: 'none' | 'pending' | 'claimed'; counts: ClaimCounts; bytes: number }; quota: { usedBytes: number; limitBytes: number; afterClaimBytes: number }; verificationRequired: boolean }
export interface AuthoringVersion { id: string; status: 'draft' | 'released'; purpose: 'authoring'; createdAt: string; current: boolean }
export interface HistorySummary { lessonArchived?: boolean; lessonPurged?: boolean; joinCode: string | null; stageNumber: number | null; stageCount: number; stageTitle: string | null; participantCount: number; id: string; lessonId: string; lessonVersionId: string; title: string; locale: string; mode: 'lesson' | 'rehearsal'; status: RuntimeState['status']; revision: number; createdAt: string; startedAt: string | null; finishedAt: string | null; visitedStageIds: string[] | null; detailsAvailable: boolean; detailsExpiresAt: string | null; historyExpiresAt: string | null }
export interface HistoryAggregate { stageId: string; blockId: string; type: BlockType; schemaVersion: number; submittedCount: number; gradedCount: number; correctCount: number; incorrectCount: number; options?: { optionId: string; count: number }[]; roles?: { roleId: string; count: number }[]; signals?: { readyCount: number; questionCount: number } }
export interface HistoryDetail extends HistorySummary { aggregates: HistoryAggregate[]; teacherNotes: string; participants: TeacherState['participants']; answers: TeacherAnswer[]; snapshotDocument?: LessonDocument }
export type RightsBasis = 'unspecified' | 'self_created' | 'permission' | 'public_domain' | 'licensed' | 'ai_generated';
export interface Metadata { title: string; tags: string[]; author: string; source: string; rightsBasis: RightsBasis; usageRights: string }
export interface Usage { lessonId?: string; lessonVersionId?: string; templateId?: string; templateVersionId?: string; title: string; status?: string; blockId?: string }
export interface MediaQuota { usedBytes: number; limitBytes: number; maxFileBytes: number }
export interface MediaAsset extends Metadata { id: string; revision: number; currentVersionId: string; archived: boolean; versions: Media[]; usages: Usage[] }
export interface MediaResponse { media: Media[]; assets: MediaAsset[]; quota: MediaQuota }
export interface TemplateSummary { id: string; title: string; tags: string[]; type: BlockType; locales: string[]; revision: number; currentVersionId: string; archived: boolean; archivedAt?: string | null }
export interface TemplateVersion { id: string; versionNo: number; locales: string[]; defaultLocale: string; block: Block; attribution: Metadata; createdAt: string }
export interface TemplateDetail extends TemplateSummary, Metadata { versions: TemplateVersion[]; usages: Usage[] }
