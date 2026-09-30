<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { waveIsFresh, waveIsActive } from './runtime';
import RuntimeTimer from './RuntimeTimer.vue';
import type { Messages, RuntimeState } from './types';
const props = defineProps<{ state: RuntimeState; messages: Messages; hideTimer?: boolean; hideStatus?: boolean }>();
let locallySeen: string | null = null;
const waveVisible = ref(false);
let waveTimeout: ReturnType<typeof setTimeout>;
onBeforeUnmount(() => clearTimeout(waveTimeout));
watch(() => props.state, state => {
    if (!waveIsActive(state)) { waveVisible.value = false; clearTimeout(waveTimeout); return; }
    let seen: string | null = locallySeen;
    try { seen = sessionStorage.getItem(`wave:${state.id}`) ?? locallySeen; } catch { /* Private storage may be unavailable. */ }
    if (waveIsFresh(state, seen)) {
        locallySeen = state.wave!.id;
        try { sessionStorage.setItem(`wave:${state.id}`, state.wave!.id); } catch { /* Keep the current page usable. */ }
        waveVisible.value = true; clearTimeout(waveTimeout);
        waveTimeout = setTimeout(() => { waveVisible.value = false; }, Math.max(0, Date.parse(state.wave!.expiresAt) - Date.parse(state.serverNow)));
    }
}, { immediate: true });
</script>
<template>
    <div v-if="!hideStatus || (!hideTimer && state.timer.status !== 'idle')" class="runtime-status" aria-live="polite"><span v-if="!hideStatus" class="status-pill">{{ messages['session_' + state.status] }}</span><RuntimeTimer v-if="!hideTimer && state.timer.status !== 'idle'" :state="state" :messages="messages" /></div>
    <p v-if="state.message" class="studio-card session-message plain-text">{{ state.message }}</p>
    <div v-if="waveVisible" class="kindness-wave" role="status"><span aria-hidden="true">♥</span> {{ messages.wave_received }}</div>
</template>
