<script setup lang="ts">
import type { Messages, RuntimeState } from './types';
import '../../css/kindness-wave.css';
defineProps<{ wave: NonNullable<RuntimeState['wave']>; messages: Messages }>();
</script>
<template>
    <div class="kindness-celebration" role="status" aria-live="assertive" aria-atomic="true">
        <div class="kindness-celebration-rays" aria-hidden="true"><i v-for="ray in 12" :key="ray" :style="{ '--ray': ray }" /></div>
        <span class="kindness-celebration-star" aria-hidden="true">✦</span>
        <strong>{{ messages.wave_title }}</strong>
        <p>{{ messages.wave_subtitle }}</p>
        <p v-if="wave.aggregate" class="kindness-celebration-total">{{ messages.wave_contribution.replace(':points', String(wave.aggregate.points)).replace(':participants', String(wave.aggregate.participants)) }}</p>
    </div>
</template>
