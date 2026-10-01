<script setup lang="ts">
import type { HistorySummary, Messages } from './types';
defineProps<{ sessions: HistorySummary[]; locale: string; messages: Messages }>();
</script>
<template>
    <ul class="active-session-list">
        <li v-for="session in sessions" :key="session.id">
            <div><strong>{{ session.title }}</strong><span class="status-pill">{{ messages['session_' + session.status] }}</span></div>
            <p>{{ messages.session_code }}: <strong>{{ session.joinCode }}</strong> · {{ new Date(session.createdAt).toLocaleString(locale) }}</p>
            <p>{{ messages.session_stage }}: {{ session.stageNumber ?? messages.unknown }} / {{ session.stageCount }}<template v-if="session.stageTitle"> · {{ session.stageTitle }}</template> · {{ messages.session_participants }}: {{ session.participantCount }}</p>
            <a class="button-link" :href="`/${locale}/teach/${session.id}`">{{ messages.return_to_control }} →</a>
        </li>
    </ul>
</template>
<style scoped>
.active-session-list{list-style:none;padding:0;margin:0;display:grid;gap:14px}.active-session-list li{min-width:0;padding:14px;border:1px solid #dbc7a5;border-radius:8px;background:#fffaf0b8}.active-session-list li>div{display:flex;align-items:center;flex-wrap:wrap;gap:10px}.active-session-list strong{overflow-wrap:anywhere}.active-session-list p{margin:8px 0;font-size:14px;line-height:1.45}.active-session-list a{display:inline-flex;margin-top:4px;font:600 14px/1.4 system-ui,sans-serif;color:#924210}
</style>
