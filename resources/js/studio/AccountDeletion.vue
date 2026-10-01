<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { api, ApiError, errorMessage } from './api';
import type { Messages } from './types';

interface DeletionRequest { id: string; status: 'pending' | 'cancelled'; revision: number; requestedAt: string; cancelledAt: string | null }
const props = defineProps<{ locale: string; messages: Messages }>();
const request = ref<DeletionRequest | null>(null);
const password = ref('');
const loaded = ref(false);
const busy = ref(false);
const error = ref('');
const notice = ref('');
const controller = new AbortController();
async function load() {
    const response = await api<{ request: DeletionRequest | null }>('/api/account/deletion-request', 'GET', undefined, controller.signal);
    request.value = response.request; loaded.value = true;
}
async function change(cancel: boolean) {
    if (busy.value || !loaded.value) return;
    busy.value = true; error.value = ''; notice.value = '';
    try {
        const response = await api<{ request: DeletionRequest }>('/api/account/deletion-request' + (cancel ? '/cancel' : ''), 'POST', {
            currentPassword: password.value, expectedRevision: request.value?.revision ?? 0,
        }, controller.signal);
        request.value = response.request; notice.value = props.messages.deletion_saved; password.value = '';
    } catch (problem) {
        if (problem instanceof DOMException && problem.name === 'AbortError') return;
        error.value = errorMessage(problem, props.messages);
        if (problem instanceof ApiError && problem.status === 409) {
            try { await load(); } catch { /* The original failure remains visible. */ }
        }
    } finally { busy.value = false; }
}
onMounted(async () => { try { await load(); } catch (problem) { error.value = errorMessage(problem, props.messages); } });
onBeforeUnmount(() => { controller.abort(); password.value = ''; });
</script>
<template>
    <section class="studio-card account-deletion">
        <h2>{{ messages.deletion_title }}</h2>
        <p class="field-hint">{{ messages.deletion_hint }}</p>
        <p v-if="error" class="error-banner" role="alert">{{ error }}</p>
        <p v-if="notice" class="success-banner" role="status">{{ notice }}</p>
        <template v-if="request">
            <p class="info-banner">{{ request.status === 'pending' ? messages.deletion_pending : messages.deletion_cancelled }}</p>
            <p>{{ messages.deletion_requested_at }}: {{ new Date(request.requestedAt).toLocaleString(locale === 'de' ? 'de-DE' : 'ru-RU') }}</p>
        </template>
        <form v-if="loaded" @submit.prevent="change(request?.status === 'pending')">
            <label>{{ messages.deletion_current_password }}<input v-model="password" type="password" autocomplete="current-password" required :disabled="busy" /></label>
            <button :disabled="busy || !password" type="submit">{{ request?.status === 'pending' ? messages.deletion_cancel : messages.deletion_submit }}</button>
        </form>
        <button v-else :disabled="busy" @click="load().catch(problem => error = errorMessage(problem, messages))">{{ messages.retry_command }}</button>
    </section>
</template>
