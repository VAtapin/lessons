<script setup lang="ts">
import { ref } from 'vue';
import { api, errorMessage } from './api';
import type { Messages } from './types';
const props = defineProps<{ locale: string; messages: Messages }>();
const code = ref('');
const name = ref('');
const busy = ref(false);
const error = ref('');
async function join() {
    busy.value = true; error.value = '';
    try {
        const response = await api<{ participant: { id: string; name: string }; sessionId: string }>('/api/join', 'POST', { code: code.value.trim().toUpperCase(), name: name.value.trim() });
        window.location.assign(`/${props.locale}/participate/${response.sessionId}`);
    } catch (problem) { error.value = errorMessage(problem, props.messages); busy.value = false; }
}
</script>
<template>
    <section class="studio-card join-form"><p class="eyebrow">{{ messages.student_join }}</p><h1>{{ messages.join_title }}</h1><p>{{ messages.join_description }}</p><p v-if="error" role="alert" class="error-banner">{{ error }}</p><form @submit.prevent="join"><label>{{ messages.join_code }}<input v-model="code" required maxlength="32" autocomplete="off" autocapitalize="characters" /></label><label>{{ messages.your_name }}<input v-model="name" required maxlength="80" autocomplete="nickname" /></label><button class="primary" :disabled="busy">{{ busy ? messages.loading : messages.join }}</button></form></section>
</template>
