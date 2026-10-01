<script setup lang="ts">
import { ref, watch } from 'vue';
import QRCode from 'qrcode';
import type { Messages } from './types';
const props = defineProps<{ code: string; url: string; messages: Messages; dismissible?: boolean; disabled?: boolean }>();
defineEmits<{ dismiss: [] }>();
const qr = ref('');
const failed = ref(false);
watch(() => props.url, async url => {
    qr.value = ''; failed.value = false;
    try {
        const result = await QRCode.toDataURL(url, { width: 768, margin: 3, color: { dark: '#173f3b', light: '#ffffff' } });
        if (props.url === url) qr.value = result;
    } catch { if (props.url === url) failed.value = true; }
}, { immediate: true });
</script>
<template>
    <section class="classroom-overlay join-projection" :aria-label="messages.show_qr_on_screen">
        <div class="join-projection-card">
            <button v-if="dismissible" class="overlay-close" :aria-label="messages.hide_qr_on_screen" :disabled="disabled" @click="$emit('dismiss')">×</button>
            <p class="eyebrow">{{ messages.student_join }}</p>
            <h1>{{ messages.scan_qr }}</h1>
            <img v-if="qr" :src="qr" :alt="messages.qr_alt" width="768" height="768" />
            <p v-if="failed" role="alert">{{ messages.error_qr }}</p>
            <strong class="join-code">{{ code }}</strong>
            <p class="join-projection-url">{{ url }}</p>
        </div>
    </section>
</template>
