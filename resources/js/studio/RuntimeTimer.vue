<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { formatTimer, remainingSeconds } from './runtime';
import type { Messages, RuntimeState } from './types';
const props = defineProps<{ state: RuntimeState; messages: Messages; prominent?: boolean }>();
const now = ref(performance.now());
let receivedAt = now.value;
const clock = setInterval(() => { now.value = performance.now(); }, 250);
onBeforeUnmount(() => clearInterval(clock));
watch(() => props.state, () => { receivedAt = performance.now(); now.value = receivedAt; });
const remaining = computed(() => remainingSeconds(props.state, now.value - receivedAt));
const status = computed(() => remaining.value === 0 && props.state.timer.status === 'running' ? 'expired' : props.state.timer.status);
</script>
<template>
    <span :class="['timer-value', { 'timer-prominent': prominent }]" role="timer"><strong>{{ formatTimer(remaining) }}</strong><small>{{ messages['timer_' + status] }}</small></span>
</template>
