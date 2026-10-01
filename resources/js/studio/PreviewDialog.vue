<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
defineProps<{ title: string; closeLabel: string }>();
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement>();
onMounted(() => dialog.value?.showModal());
onBeforeUnmount(() => { if (dialog.value?.open) dialog.value.close(); });
function outside(event: MouseEvent) {
    if (event.target !== dialog.value || !dialog.value) return;
    const bounds = dialog.value.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) emit('close');
}
</script>
<template>
    <dialog ref="dialog" class="preview-dialog" role="dialog" aria-modal="true" :aria-label="title" @cancel.prevent="$emit('close')" @click="outside">
        <div class="section-heading preview-dialog-heading"><h2>{{ title }}</h2><button type="button" autofocus :aria-label="closeLabel" @click="$emit('close')">{{ closeLabel }}</button></div>
        <div class="preview-dialog-content"><slot /></div>
    </dialog>
</template>
<style scoped>
.preview-dialog { width: min(920px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); padding: 24px; overflow: auto; border: 1px solid var(--border); border-radius: 16px; background: var(--card, #fffaf0); color: var(--ink, #193b3a); box-shadow: 0 24px 80px #193b3a40; }
.preview-dialog::backdrop { background: #182d2bad; }
.preview-dialog-heading { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 20px; }
.preview-dialog-heading h2 { margin: 0; }
.preview-dialog-content { min-width: 0; }
.preview-dialog-content :deep(img) { max-width: 100%; max-height: 65dvh; object-fit: contain; }
.preview-dialog-content :deep(.render-block) { padding: 16px; margin-bottom: 20px; border: 1px solid var(--border); border-radius: 10px; }
@media (max-width: 600px) { .preview-dialog { padding: 16px; } .preview-dialog-heading { gap: 12px; } }
</style>
