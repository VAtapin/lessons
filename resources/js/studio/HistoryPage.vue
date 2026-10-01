<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, ApiError, errorMessage } from './api';
import { blockLabel, valueText } from './interactive';
import { historyContinuePath } from './account-helpers';
import type { BlockContent, HistoryDetail, HistorySummary, LessonDocument, Messages, TeacherState } from './types';
const props = defineProps<{ locale: string; sessionId?: string; messages: Messages }>();
const items = ref<HistorySummary[]>([]);
const detail = ref<HistoryDetail>();
const source = ref<LessonDocument>();
const nextCursor = ref<string | null>(null);
const status = ref('');
const mode = ref('');
const note = ref('');
const noteRevision = ref(0);
const conflict = ref(false);
const busy = ref(false);
const error = ref('');
const notice = ref('');
const noteDirty = computed(() => !!detail.value && note.value !== detail.value.teacherNotes);
const date = (value: string | null) => value ? new Date(value).toLocaleString(props.locale) : props.messages.unknown;
const content = (blockId: string): BlockContent => source.value?.stages.flatMap(stage => stage.blocks).find(block => block.id === blockId)?.content[detail.value?.locale ?? ''] ?? {};
async function list(more = false) {
    busy.value = true; error.value = '';
    try { const response = await api<{ sessions: HistorySummary[]; nextCursor: string | null }>(`/api/studio/sessions?${new URLSearchParams({ ...(status.value ? { status: status.value } : {}), ...(mode.value ? { mode: mode.value } : {}), ...(more && nextCursor.value ? { cursor: nextCursor.value } : {}) })}`); items.value = more ? [...new Map([...items.value, ...response.sessions].map(item => [item.id, item])).values()] : response.sessions; nextCursor.value = response.nextCursor; }
    catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
async function load() {
    if (!props.sessionId) return;
    busy.value = true; error.value = '';
    try { detail.value = (await api<{ history: HistoryDetail }>(`/api/studio/sessions/${props.sessionId}/history`)).history; note.value = detail.value.teacherNotes; noteRevision.value = detail.value.revision; conflict.value = false; source.value = detail.value.snapshotDocument; if (detail.value.mode === 'lesson') { try { source.value = (await api<{ version: { document: LessonDocument } }>(`/api/studio/lessons/${detail.value.lessonId}/versions/${detail.value.lessonVersionId}`)).version.document; } catch { source.value = undefined; } } }
    catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
async function save() {
    if (!detail.value) return;
    busy.value = true; error.value = ''; notice.value = '';
    try { detail.value = (await api<{ history: HistoryDetail }>(`/api/studio/sessions/${detail.value.id}/history`, 'PATCH', { expectedRevision: noteRevision.value, teacherNotes: note.value })).history; noteRevision.value = detail.value.revision; note.value = detail.value.teacherNotes; conflict.value = false; notice.value = props.messages.saved; }
    catch (problem) { if (problem instanceof ApiError && problem.code === 'revision_conflict') { const current = (problem.data as { history?: HistoryDetail })?.history; if (current) detail.value = current; conflict.value = true; } error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
async function again() { if (!detail.value) return; busy.value = true; error.value = ''; try { const response = await api<{ session: TeacherState }>(`/api/studio/sessions/${detail.value.id}/again`, 'POST'); location.assign(`/${props.locale}/teach/${response.session.id}`); } catch (problem) { error.value = errorMessage(problem, props.messages); busy.value = false; } }
onMounted(() => { void (props.sessionId ? load() : list()); });
</script>
<template>
    <div class="page-heading"><h1>{{ detail?.title ?? messages.history }}</h1><a v-if="sessionId" class="button-link" :href="`/${locale}/history`">{{ messages.history }}</a></div><p v-if="error" class="error-banner" role="alert">{{ error }}</p><p v-if="notice" class="success-banner" role="status">{{ notice }}</p>
    <template v-if="!sessionId"><form class="studio-card history-filters" @submit.prevent="list()"><label>{{ messages.session_status }}<select v-model="status"><option value="">{{ messages.all_statuses }}</option><option value="active">{{ messages.active_sessions_title }}</option><option v-for="value in ['prepared','running','paused','finished']" :key="value" :value="value">{{ messages['session_' + value] }}</option></select></label><label>{{ messages.session_mode }}<select v-model="mode"><option value="">{{ messages.all_modes }}</option><option value="lesson">{{ messages.history_lesson_mode }}</option><option value="rehearsal">{{ messages.rehearsal }}</option></select></label><button :disabled="busy">{{ messages.search }}</button></form><div class="materials-grid"><article v-for="item in items" :key="item.id" class="studio-card history-item"><h2>{{ item.title }}</h2><span class="status-pill">{{ messages['session_' + item.status] }} · {{ messages[item.mode === 'rehearsal' ? 'rehearsal' : 'history_lesson_mode'] }}</span><p>{{ date(item.createdAt) }} · {{ item.locale }}</p><p v-if="item.mode === 'lesson' && item.status !== 'finished'">{{ messages.session_code }}: {{ item.joinCode }} · {{ messages.session_stage }}: {{ item.stageNumber }} / {{ item.stageCount }} · {{ item.stageTitle }} · {{ messages.session_participants }}: {{ item.participantCount }}</p><div class="action-row"><a class="button-link" :href="`/${locale}/history/${item.id}`">{{ messages.history_details }}</a><a v-if="historyContinuePath(item, locale)" class="button-link" :href="historyContinuePath(item, locale)">{{ messages.return_to_control }}</a></div></article></div><p v-if="!busy && !items.length" class="studio-card">{{ messages.history_empty }}</p><button v-if="nextCursor" :disabled="busy" @click="list(true)">{{ messages.load_more }}</button></template>
    <template v-if="detail"><section class="studio-card history-overview"><span class="status-pill">{{ messages['session_' + detail.status] }} · {{ messages[detail.mode === 'rehearsal' ? 'rehearsal' : 'history_lesson_mode'] }}</span><dl><div><dt>{{ messages.created_at }}</dt><dd>{{ date(detail.createdAt) }}</dd></div><div><dt>{{ messages.started_at }}</dt><dd>{{ date(detail.startedAt) }}</dd></div><div><dt>{{ messages.finished_at }}</dt><dd>{{ date(detail.finishedAt) }}</dd></div><div><dt>{{ messages.visited_stages }}</dt><dd>{{ detail.visitedStageIds === null ? messages.unknown : detail.visitedStageIds.length }}</dd></div><div><dt>{{ messages.details_until }}</dt><dd>{{ date(detail.detailsExpiresAt) }}</dd></div><div><dt>{{ messages.history_until }}</dt><dd>{{ date(detail.historyExpiresAt) }}</dd></div></dl><p class="field-hint">{{ messages.visited_stages_hint }}</p><div class="action-row"><a v-if="historyContinuePath(detail, locale)" class="button-link" :href="historyContinuePath(detail, locale)">{{ messages.return_to_control }}</a><button v-if="detail.mode === 'lesson'" :disabled="busy" @click="again">{{ messages.run_again }}</button><a class="button-link" :href="`/${locale}/studio/lessons/${detail.lessonId}`">{{ messages.open_editor }}</a></div><p v-if="detail.mode === 'lesson'" class="field-hint">{{ messages.run_again_hint }}</p></section>
        <form class="studio-card history-notes" @submit.prevent="save"><label>{{ messages.history_notes }}<textarea v-model="note" rows="4" :disabled="busy" /></label><p class="field-hint">{{ messages.history_notes_hint }} · {{ Array.from(note).length }} / 5000</p><div v-if="conflict" class="info-banner"><p>{{ messages.history_note_conflict }}</p><p class="plain-text">{{ messages.current_history_note }}: {{ detail.teacherNotes }}</p><button type="button" :disabled="busy" @click="noteRevision = detail.revision; conflict = false">{{ messages.use_current_revision }}</button><button type="button" :disabled="busy" @click="note = detail.teacherNotes; noteRevision = detail.revision; conflict = false">{{ messages.reload_answer }}</button></div><button class="primary" :disabled="busy || conflict || !noteDirty || Array.from(note).length > 5000">{{ messages.save }}</button></form>
        <section class="studio-card history-results"><h2>{{ messages.anonymous_totals }}</h2><div v-for="aggregate in detail.aggregates" :key="aggregate.blockId" class="history-aggregate"><h3>{{ source?.stages.flatMap(stage => stage.blocks).find(block => block.id === aggregate.blockId)?.content[detail.locale]?.question ?? blockLabel(aggregate.type, messages) }}</h3><p>{{ messages.answers_received }}: {{ aggregate.submittedCount }}</p><p v-if="aggregate.gradedCount">{{ messages.answer_correct }}: {{ aggregate.correctCount }} · {{ messages.answer_incorrect }}: {{ aggregate.incorrectCount }}</p><p v-for="option in aggregate.options" :key="option.optionId">{{ content(aggregate.blockId).options?.find(item => item.optionId === option.optionId)?.text ?? option.optionId }}: {{ option.count }}</p><p v-for="role in aggregate.roles" :key="role.roleId">{{ content(aggregate.blockId).roles?.find(item => item.roleId === role.roleId)?.text ?? role.roleId }}: {{ role.count }}</p><p v-if="aggregate.signals">{{ messages.ready }}: {{ aggregate.signals.readyCount }} · {{ messages.question_signal }}: {{ aggregate.signals.questionCount }}</p></div><p v-if="!detail.aggregates.length" class="field-hint">{{ messages.no_answers }}</p></section>
        <section class="studio-card history-participants"><h2>{{ messages.student_answers }}</h2><p v-if="!detail.detailsAvailable" class="field-hint">{{ messages.history_details_expired }}</p><details v-for="participant in detail.participants" :key="participant.id"><summary>{{ participant.name }}</summary><div v-for="answer in detail.answers.filter(answer => answer.participantId === participant.id)" :key="answer.id"><p class="plain-text">{{ valueText(content(answer.blockId), answer.value, messages) }}</p><p v-if="answer.moderation?.displayText" class="plain-text">{{ messages.display_answer }}: {{ answer.moderation.displayText }}</p></div></details></section>
    </template><p v-if="busy" role="status">{{ messages.loading }}</p>
</template>
