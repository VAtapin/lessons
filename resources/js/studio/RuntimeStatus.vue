<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { waveIsActive } from './runtime';
import { createWavePlayback } from './kindness-wave';
import KindnessWave from './KindnessWave.vue';
import RuntimeTimer from './RuntimeTimer.vue';
import type { Messages, RuntimeState } from './types';
const props = defineProps<{ state: RuntimeState; messages: Messages; hideTimer?: boolean; hideStatus?: boolean }>();
let storage: Storage | undefined;
try { storage = sessionStorage; } catch { /* Browser storage may be unavailable. */ }
const playback = createWavePlayback(storage);
const waveVisible = ref(false);
let waveTimeout: ReturnType<typeof setTimeout>;
onBeforeUnmount(() => clearTimeout(waveTimeout));
watch(() => props.state, state => {
    if (!waveIsActive(state)) { waveVisible.value = false; clearTimeout(waveTimeout); return; }
    const duration = playback(state);
    if (duration > 0) {
        waveVisible.value = true; clearTimeout(waveTimeout);
        waveTimeout = setTimeout(() => { waveVisible.value = false; }, duration);
    }
}, { immediate: true });
</script>
<template>
    <div v-if="!hideStatus || (!hideTimer && state.timer.status !== 'idle')" class="runtime-status" aria-live="polite"><span v-if="!hideStatus" class="status-pill">{{ messages['session_' + state.status] }}</span><RuntimeTimer v-if="!hideTimer && state.timer.status !== 'idle'" :state="state" :messages="messages" /></div>
    <p v-if="state.message" class="studio-card session-message plain-text">{{ state.message }}</p>
    <KindnessWave v-if="waveVisible && state.wave" :wave="state.wave" :messages="messages" />
</template>
