import { cloneEditor } from './editor-save';
import type { LessonDocument } from './types';
export interface EditorSnapshot { document: LessonDocument; stageId: string; locale: string }
export class EditorHistory {
    past: EditorSnapshot[] = [];
    future: EditorSnapshot[] = [];
    current?: EditorSnapshot;
    private key = '';
    private at = 0;
    reset(value: EditorSnapshot): void { this.current = cloneEditor(value); this.past = []; this.future = []; this.key = ''; }
    commit(value: EditorSnapshot, key: string, now: number): void {
        if (!this.current) { this.reset(value); return; }
        if (JSON.stringify(this.current.document) === JSON.stringify(value.document)) return;
        if (!key || key !== this.key || now - this.at > 500) { this.past.push(this.current); if (this.past.length > 100) this.past.shift(); }
        this.current = cloneEditor(value); this.future = []; this.key = key; this.at = now;
    }
    undo(): EditorSnapshot | undefined { if (!this.current || !this.past.length) return; this.future.push(this.current); this.current = this.past.pop()!; this.key = ''; return cloneEditor(this.current); }
    redo(): EditorSnapshot | undefined { if (!this.current || !this.future.length) return; this.past.push(this.current); this.current = this.future.pop()!; this.key = ''; return cloneEditor(this.current); }
}
