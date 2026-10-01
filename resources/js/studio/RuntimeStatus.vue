<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { waveIsActive } from './runtime';
import { createWavePlayback } from './kindness-wave';
import KindnessWave from './KindnessWave.vue';
import RuntimeTimer from './RuntimeTimer.vue';
import JoinProjection from './JoinProjection.vue';
import type { Messages, RuntimeState } from './types';
const props = defineProps<{ state: RuntimeState; messages: Messages; hideTimer?: boolean; hideStatus?: boolean; presenter?: boolean; disabled?: boolean }>();
defineEmits<{ clearMessage: []; hideJoin: [] }>();
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
    <section v-if="state.message" class="classroom-overlay message-projection" :aria-label="messages.screen_message" role="status" aria-live="polite"><div class="message-projection-card"><button v-if="presenter" class="overlay-close" :aria-label="messages.clear_message" :disabled="disabled" @click="$emit('clearMessage')">×</button><p class="eyebrow">{{ messages.screen_message }}</p><p class="plain-text">{{ state.message }}</p></div></section>
    <JoinProjection v-if="state.joinProjection" :code="state.joinProjection.code" :url="state.joinProjection.url" :messages="messages" :dismissible="presenter" :disabled="disabled" @dismiss="$emit('hideJoin')" />
    <KindnessWave v-if="waveVisible && state.wave" :wave="state.wave" :messages="messages" />
</template>
