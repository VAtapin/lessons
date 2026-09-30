<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { formatTimer, remainingSeconds, waveIsFresh, waveIsActive } from './runtime';
import type { Messages, RuntimeState } from './types';
const props = defineProps<{ state: RuntimeState; messages: Messages }>();
const now = ref(performance.now());
let receivedAt = performance.now();
let locallySeen: string | null = null;
const waveVisible = ref(false);
let waveTimeout: ReturnType<typeof setTimeout>;
const clock = setInterval(() => { now.value = performance.now(); }, 250);
onBeforeUnmount(() => { clearInterval(clock); clearTimeout(waveTimeout); });
const remaining = computed(() => remainingSeconds(props.state, now.value - receivedAt));
watch(() => props.state, state => {
    receivedAt = performance.now(); now.value = receivedAt;
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
    <div class="runtime-status" aria-live="polite"><span class="status-pill">{{ messages['session_' + state.status] }}</span><span v-if="state.timer.status !== 'idle'" class="timer-value" role="timer">{{ formatTimer(remaining) }} <small>{{ messages['timer_' + (remaining === 0 && state.timer.status === 'running' ? 'expired' : state.timer.status)] }}</small></span></div>
    <p v-if="state.message" class="studio-card session-message plain-text">{{ state.message }}</p>
    <div v-if="waveVisible" class="kindness-wave" role="status"><span aria-hidden="true">♥</span> {{ messages.wave_received }}</div>
</template>
