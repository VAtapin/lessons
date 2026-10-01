<script setup lang="ts">
import { ref } from 'vue';
import { responseTextValid } from './interactive';
import type { Messages, TeacherAnswer } from './types';
const props = defineProps<{ answer: TeacherAnswer; name: string; messages: Messages; disabled: boolean; submitCommand: (action: string, payload: Record<string, unknown>) => Promise<boolean | undefined> }>();
defineEmits<{ publish: []; dismiss: []; edit: []; command: [action: string, payload: Record<string, unknown>] }>();
const replying = ref(false);
const reply = ref('');
const replyRevision = ref<number>();
function toggleReply() { replying.value = !replying.value; if (replying.value && !reply.value) replyRevision.value = props.answer.revision; }
async function sendReply() {
    if (props.disabled || replyRevision.value !== props.answer.revision || !responseTextValid(reply.value, 1000)) return;
    const text = reply.value;
    const acknowledged = await props.submitCommand('answer.reply', { answerId: props.answer.id, expectedAnswerRevision: replyRevision.value, text });
    if (acknowledged === true && reply.value === text) { reply.value = ''; replying.value = false; }
}
</script>
<template>
    <section class="live-answer-card" aria-live="polite" :aria-label="messages.live_answer">
        <div class="live-answer-heading"><strong>{{ name }}</strong><small>{{ answer.value.question ? messages.question_signal : messages.live_answer }}</small><button type="button" :aria-label="messages.focus_close_panel" @click="$emit('dismiss')">×</button></div>
        <p v-if="answer.value.text" class="plain-text live-answer-text">{{ answer.value.text }}</p>
        <div class="live-answer-actions">
            <template v-if="answer.moderation"><button type="button" class="primary" :disabled="disabled" @click="$emit('publish')">✓ {{ messages.show_everyone }}</button><button type="button" :disabled="disabled" @click="$emit('command', 'answer.moderate', { answerId: answer.id, expectedAnswerRevision: answer.revision, status: 'rejected' })">× {{ messages.reject_answer }}</button></template>
            <button v-else type="button" class="primary" :disabled="disabled" @click="$emit('command', 'signal.ack', { blockId: answer.blockId, participantId: answer.participantId })">✓ {{ messages.acknowledge_question }}</button>
            <button type="button" :disabled="disabled" :aria-expanded="replying" @click="toggleReply">↩ {{ messages.reply_to_student }}</button><button v-if="answer.moderation" type="button" :disabled="disabled" @click="$emit('edit')">{{ messages.edit_answer }}</button>
        </div>
        <form v-if="replying" class="live-reply-form" @submit.prevent="sendReply"><label>{{ messages.private_reply }}<textarea v-model="reply" rows="2" maxlength="1000" :disabled="disabled" /></label><small>{{ messages.private_reply_hint }}</small><p v-if="replyRevision !== answer.revision" role="status">{{ messages.answer_changed }} <button type="button" :disabled="disabled" @click="replyRevision = answer.revision">{{ messages.use_current_revision }}</button></p><button class="primary" :disabled="disabled || replyRevision !== answer.revision || !responseTextValid(reply, 1000)">{{ messages.send_reply }}</button></form>
    </section>
</template>
