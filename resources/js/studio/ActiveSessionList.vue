<script setup lang="ts">
import { ref } from 'vue';
import { api, ApiError, errorMessage } from './api';
import type { HistorySummary, Messages, RuntimeState } from './types';
const props = defineProps<{ sessions: HistorySummary[]; locale: string; messages: Messages; manageable?: boolean }>();
const emit = defineEmits<{ finished: [id: string]; refresh: [] }>();
const pending = ref<{ id: string; revision: number; commandId: string }>();
const busy = ref(false), error = ref('');
function confirmFinish(session: HistorySummary) {
    if (!props.manageable || busy.value) return;
    pending.value = { id: session.id, revision: session.revision, commandId: crypto.randomUUID() }; error.value = '';
}
async function finish() {
    if (!props.manageable || !pending.value || busy.value) return;
    busy.value = true; error.value = '';
    const snapshot = pending.value;
    try {
        const result = await api<{ session: RuntimeState; acknowledgedCommandId: string }>(`/api/studio/sessions/${snapshot.id}/commands`, 'POST', { commandId: snapshot.commandId, expectedRevision: snapshot.revision, action: 'finish', payload: {} });
        if (result.acknowledgedCommandId !== snapshot.commandId || result.session.id !== snapshot.id || result.session.status !== 'finished') throw new ApiError('request', 0);
        pending.value = undefined; emit('finished', snapshot.id);
    } catch (problem) {
        if (problem instanceof ApiError && problem.status === 409) { pending.value = undefined; emit('refresh'); }
        error.value = errorMessage(problem, props.messages);
    } finally { busy.value = false; }
}
</script>
<template>
    <p v-if="error" role="alert" class="error-banner">{{ error }}</p>
    <ul class="active-session-list">
        <li v-for="session in sessions" :key="session.id">
            <div><strong>{{ session.title }}</strong><span class="status-pill">{{ messages['session_' + session.status] }}</span></div>
            <p>{{ messages.session_code }}: <strong>{{ session.joinCode }}</strong> · {{ new Date(session.createdAt).toLocaleString(locale) }}</p>
            <p>{{ messages.session_stage }}: {{ session.stageNumber ?? messages.unknown }} / {{ session.stageCount }}<template v-if="session.stageTitle"> · {{ session.stageTitle }}</template> · {{ messages.session_participants }}: {{ session.participantCount }}</p>
            <a class="button-link" :href="`/${locale}/teach/${session.id}`">{{ messages.return_to_control }} →</a>
            <button v-if="manageable" :disabled="busy" :aria-expanded="pending?.id === session.id" @click="confirmFinish(session)">{{ messages.finish_remove_session }}</button>
            <div v-if="pending?.id === session.id" class="info-banner" role="alert"><p>{{ messages.finish_remove_confirm }}</p><button :disabled="busy" @click="finish">{{ messages.confirm_finish_session }}</button><button :disabled="busy" @click="pending = undefined">{{ messages.cancel }}</button></div>
        </li>
    </ul>
</template>
<style scoped>
.active-session-list{list-style:none;padding:0;margin:0;display:grid;gap:14px}.active-session-list li{min-width:0;padding:14px;border:1px solid #dbc7a5;border-radius:8px;background:#fffaf0b8}.active-session-list li>div{display:flex;align-items:center;flex-wrap:wrap;gap:10px}.active-session-list strong{overflow-wrap:anywhere}.active-session-list p{margin:8px 0;font-size:14px;line-height:1.45}.active-session-list a{display:inline-flex;margin-top:4px;font:600 14px/1.4 system-ui,sans-serif;color:#924210}
</style>
