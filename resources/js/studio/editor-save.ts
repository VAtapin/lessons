import type { EditorIssue, EditorLesson, LessonDocument, SaveRequest, SaveResponse } from './types';
export type SaveStatus = 'loading' | 'clean' | 'dirty' | 'saving' | 'invalid' | 'offline' | 'retry-required' | 'conflict' | 'blocked';
export const cloneEditor = <T>(value: T): T => JSON.parse(JSON.stringify(value));
function freeze<T>(value: T): T { if (value && typeof value === 'object') { Object.freeze(value); for (const item of Object.values(value)) freeze(item); } return value; }
export class EditorSave {
    status: SaveStatus = 'loading';
    generation = 0;
    savedGeneration = 0;
    revision = 0;
    versionId = '';
    flight = false;
    pending?: { body: SaveRequest; generation: number };
    remote?: EditorLesson;
    issues: EditorIssue[] = [];
    get dirty(): boolean { return this.generation !== this.savedGeneration; }
    load(lesson: EditorLesson): void { this.revision = lesson.revision; this.versionId = lesson.versionId; this.generation = this.savedGeneration = 0; this.pending = undefined; this.remote = undefined; this.issues = []; this.status = 'clean'; }
    edit(): void { this.generation++; if (!['conflict', 'retry-required', 'offline', 'blocked'].includes(this.status) && !this.flight) this.status = 'dirty'; }
    begin(document: LessonDocument, uuid: string, retry = false): SaveRequest | undefined {
        if (this.flight || this.status === 'blocked' || this.status === 'conflict') return;
        if (this.pending) { if (!retry) return; }
        else { if (!this.dirty || !['dirty', 'invalid'].includes(this.status)) return; this.pending = { body: freeze(cloneEditor({ saveId: uuid, expectedRevision: this.revision, document })), generation: this.generation }; }
        this.flight = true; this.status = 'saving'; return this.pending.body;
    }
    acknowledge(response: SaveResponse): 'clean' | 'queued' | 'conflict' | 'uncertain' {
        this.flight = false;
        if (!this.pending || response.acknowledgedSaveId !== this.pending.body.saveId) { this.status = 'retry-required'; return 'uncertain'; }
        const sent = this.pending; this.pending = undefined;
        if (response.lesson.revision !== response.appliedRevision || response.lesson.versionId !== response.appliedVersionId) { this.remote = cloneEditor(response.lesson); this.status = 'conflict'; return 'conflict'; }
        this.revision = response.appliedRevision; this.versionId = response.appliedVersionId; this.savedGeneration = sent.generation; this.issues = [];
        this.status = this.dirty ? 'dirty' : 'clean'; return this.dirty ? 'queued' : 'clean';
    }
    fail(status: number, current?: EditorLesson, issues: EditorIssue[] = []): void {
        this.flight = false;
        if ([401, 403, 404].includes(status)) { this.status = 'blocked'; return; }
        if (status === 409) { this.remote = current && cloneEditor(current); this.pending = undefined; this.status = 'conflict'; return; }
        if (status === 422) { const newer = !!this.pending && this.generation > this.pending.generation; this.pending = undefined; this.issues = newer ? [] : issues; this.status = newer ? 'dirty' : 'invalid'; return; }
        this.status = 'retry-required';
    }
    block(): void { this.status = 'blocked'; }
}
