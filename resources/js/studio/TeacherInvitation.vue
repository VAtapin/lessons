<script setup lang="ts">
import { ref } from 'vue';
import { api, errorMessage } from './api';
import { invitationToken, localInterfaceUrl } from './collaboration';
import type { Messages } from './types';
const props = defineProps<{ locale: string; messages: Messages }>();
let token = invitationToken(location.hash);
if (location.hash) history.replaceState(history.state, '', location.pathname + location.search);
const available = ref(!!token);
const name = ref('');
const understood = ref(false);
const busy = ref(false);
const error = ref('');
async function accept() {
    if (!token || busy.value || !understood.value || !name.value.trim()) return;
    busy.value = true; error.value = '';
    try {
        const response = await api<{ sessionId: string; teacherUrl: string }>('/api/teacher-invitations/accept', 'POST', { token, displayName: name.value.trim() });
        token = undefined; available.value = false;
        const target = localInterfaceUrl(response.teacherUrl, props.locale, location.origin);
        location.assign(target ?? `/${props.locale}/conduct/${response.sessionId}`);
    } catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
</script>
<template><section class="studio-card invitation-card"><p class="eyebrow">{{ messages.workspace_caption }}</p><h1>{{ messages.collab_accept_title }}</h1><p>{{ messages.collab_access_warning }}</p><p class="field-hint">{{ messages.collab_fixed_duration }}</p><p v-if="error" class="error-banner" role="alert">{{ error }}</p><p v-if="!available" class="info-banner" role="alert">{{ messages.collab_missing_token }}</p><form v-else @submit.prevent="accept"><label>{{ messages.collab_display_name }}<input v-model="name" required maxlength="80" autocomplete="nickname" :disabled="busy" /></label><label class="checkbox-field"><input v-model="understood" type="checkbox" required :disabled="busy" />{{ messages.collab_accept_privacy }}</label><button class="primary" :disabled="busy || !understood || !name.trim()">{{ busy ? messages.loading : messages.collab_accept }}</button></form></section></template>
