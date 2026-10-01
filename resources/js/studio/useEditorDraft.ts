import { computed, nextTick, onBeforeUnmount, reactive, ref, watch, type Ref } from 'vue';
import { api, ApiError, errorMessage } from './api';
import { identityBlocked, blockIdentity } from './identity';
import { cloneEditor, EditorSave } from './editor-save';
import { EditorHistory } from './editor-history';
import type { EditorIssue, EditorLesson, LessonDocument, Messages, SaveResponse } from './types';
export function useEditorDraft(lessonId: string, stageId: Ref<string>, locale: Ref<string>, messages: Messages) {
    const lesson = ref<EditorLesson>();
    const document = ref<LessonDocument>();
    const save = reactive(new EditorSave());
    const history = reactive(new EditorHistory());
    const error = ref('');
    const composing = ref(false);
    const dirty = computed(() => save.dirty);
    let skip = false, editKey = '';
    let timer: ReturnType<typeof setTimeout> | undefined;
    let controller: AbortController | undefined;
    let active: Promise<boolean> | undefined;
    function ignoreNext() { skip = true; queueMicrotask(() => { void nextTick(() => { skip = false; }); }); }
    const fieldIds = new WeakMap<HTMLElement, string>();
    function selection() { return { document: document.value!, stageId: stageId.value, locale: locale.value }; }
    function adopt(value: EditorLesson, resetHistory = true) {
        ignoreNext(); lesson.value = value; document.value = cloneEditor(value.document); save.load(value);
        if (!value.document.locales.includes(locale.value)) locale.value = value.document.defaultLocale;
        if (!value.document.stages.some(stage => stage.id === stageId.value)) stageId.value = value.document.stages[0]!.id;
        if (resetHistory) history.reset(selection());
    }
    function schedule() { clearTimeout(timer); if (save.status === 'dirty' && !navigator.onLine) { save.status = 'offline'; return; } if (save.status === 'dirty' && !composing.value && !identityBlocked.value) timer = setTimeout(() => { void persist(); }, 800); }
    watch(document, () => {
        if (skip) { skip = false; return; }
        if (!document.value || !lesson.value) return;
        save.edit(); history.commit(selection(), editKey, performance.now()); schedule();
    }, { deep: true, flush: 'post' });
    function markText(event: Event) { const field = event.target as HTMLInputElement; if (field.tagName === 'SELECT' || ['checkbox', 'radio'].includes(field.type)) { markOperation(); return; } if (!fieldIds.has(field)) fieldIds.set(field, crypto.randomUUID()); editKey = field.dataset.editorPath ?? `${locale.value}:${stageId.value}:${fieldIds.get(field)}`; }
    function markOperation() { editKey = ''; }
    async function persist(retry = false): Promise<boolean> {
        clearTimeout(timer);
        if (active) return active;
        if (!document.value || !lesson.value || identityBlocked.value || composing.value) return false;
        if (!save.dirty && !save.pending) return save.status === 'clean';
        const body = save.begin(document.value, crypto.randomUUID(), retry);
        if (!body) return false;
        controller = new AbortController(); error.value = '';
        active = (async () => {
            try {
                const response = await api<SaveResponse>(`/api/studio/lessons/${lessonId}`, 'PUT', body, controller!.signal);
                if (identityBlocked.value || save.status === 'blocked') return false;
                const outcome = save.acknowledge(response);
                if (outcome === 'clean') { ignoreNext(); document.value = cloneEditor(response.lesson.document); lesson.value = response.lesson; }
                if (outcome === 'queued') { lesson.value = response.lesson; ignoreNext(); document.value!.id = save.versionId; }
                if (outcome === 'conflict') error.value = messages.error_revision_conflict;
                return outcome === 'clean';
            } catch (problem) {
                const data = problem instanceof ApiError ? problem.data as { lesson?: EditorLesson; issues?: EditorIssue[] } | undefined : undefined;
                save.fail(problem instanceof ApiError ? problem.status : 0, data?.lesson, data?.issues);
                if (!navigator.onLine && save.status === 'retry-required') save.status = 'offline';
                if (save.status === 'blocked') blockIdentity();
                if (identityBlocked.value) save.block();
                error.value = errorMessage(problem, messages);
                return false;
            } finally { active = undefined; controller = undefined; if (save.status === 'dirty') schedule(); }
        })();
        return active;
    }
    function restore(kind: 'undo' | 'redo') {
        const snapshot = history[kind](); if (!snapshot || !document.value || identityBlocked.value) return;
        ignoreNext(); document.value = snapshot.document; document.value.id = save.versionId; stageId.value = snapshot.stageId; locale.value = snapshot.locale; save.edit(); schedule();
    }
    function endComposition() { composing.value = false; void nextTick(schedule); }
    const offline = () => { clearTimeout(timer); if (!save.flight && save.dirty && save.status === 'dirty') save.status = 'offline'; };
    const online = () => { if (save.status === 'offline') { save.status = save.pending ? 'retry-required' : 'dirty'; schedule(); } };
    window.addEventListener('offline', offline); window.addEventListener('online', online);
    watch(identityBlocked, blocked => { if (blocked) { clearTimeout(timer); save.block(); controller?.abort(); } });
    onBeforeUnmount(() => { clearTimeout(timer); controller?.abort(); window.removeEventListener('offline', offline); window.removeEventListener('online', online); });
    return { lesson, document, save, history, dirty, error, composing, adopt, persist, restore, markText, markOperation, endComposition };
}
