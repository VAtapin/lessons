<script setup lang="ts">
import { ref, watch } from 'vue';
import { localInterfaceUrl } from './collaboration';
import type { CollaborationState, Messages, TeacherActorResponse, TeacherActorState } from './types';
const props = defineProps<{ sessionId: string; locale: string; messages: Messages; actor: TeacherActorState; collaboration: CollaborationState; status: string; serverNow: string; disabled: boolean; invitation?: TeacherActorResponse['invitation']; expanded?: boolean }>();
defineEmits<{ command: [action: string, payload: Record<string, unknown>] }>();
const seconds = ref(3600);
const copyResult = ref('');
watch(() => props.invitation?.id, () => { copyResult.value = ''; });
const active = (item: { expiresAt: string; revokedAt: string | null }) => !item.revokedAt && Date.parse(item.expiresAt) > Date.parse(props.serverNow);
const date = (value: string) => new Date(value).toLocaleString(props.locale);
const inviteUrl = () => localInterfaceUrl(props.invitation?.url, props.locale, location.origin);
async function copy() {
    const url = inviteUrl(); if (!url) return;
    try { await navigator.clipboard.writeText(url); copyResult.value = props.messages.collab_link_copied; }
    catch { copyResult.value = props.messages.collab_copy_manual; }
}
</script>
<template>
    <section class="studio-card collaboration-panel"><h2>{{ messages.collab_title }}</h2><p class="presenter-line"><span class="participant-initial" aria-hidden="true">{{ collaboration.presenter.kind === 'grant' ? Array.from(collaboration.presenter.displayName ?? '')[0] : '·' }}</span><span><strong>{{ collaboration.presenter.kind === 'owner' ? messages.collab_owner_presenter : collaboration.presenter.kind === 'grant' ? collaboration.presenter.displayName : messages.collab_vacant }}</strong><small>{{ actor.isPresenter ? messages.collab_you_present : messages.collab_you_moderate }}</small></span></p>
        <p v-if="actor.kind === 'grant' && actor.expiresAt" class="field-hint">{{ messages.collab_access_until }} {{ date(actor.expiresAt) }}</p>
        <button v-if="actor.capabilities.includes('manageCollaboration') && collaboration.presenter.kind !== 'owner'" :disabled="disabled || status === 'finished'" @click="$emit('command', 'presenter.reclaim', {})">{{ messages.collab_reclaim }}</button>
        <details v-if="actor.capabilities.includes('manageCollaboration')" :open="expanded"><summary>{{ messages.collab_manage }}</summary><p class="collaboration-warning">{{ messages.collab_access_warning }} {{ messages.collab_fixed_duration }}</p><form @submit.prevent="$emit('command', 'invite.create', { expiresInSeconds: Number(seconds) })"><label>{{ messages.collab_invitation_valid }}<select v-model="seconds" :disabled="disabled || status === 'finished'"><option :value="600">{{ messages.collab_ten_minutes }}</option><option :value="3600">{{ messages.collab_one_hour }}</option><option :value="86400">{{ messages.collab_one_day }}</option></select></label><button :disabled="disabled || status === 'finished'">{{ messages.collab_create }}</button></form>
            <div v-if="invitation" class="invitation-link"><label>{{ messages.collab_link_once }}<input :value="inviteUrl()" readonly @focus="($event.target as HTMLInputElement).select()" /></label><p class="field-hint">{{ messages.collab_link_until }} {{ date(invitation.expiresAt) }}</p><button @click="copy">{{ messages.collab_copy }}</button><p v-if="copyResult" role="status" class="field-hint">{{ copyResult }}</p></div>
            <h3>{{ messages.collab_grants }}</h3><p v-if="!collaboration.grants" role="status">{{ messages.loading }}</p><p v-else-if="!collaboration.grants.length" class="field-hint">{{ messages.collab_no_grants }}</p><ul v-else class="collaboration-list"><li v-for="grant in collaboration.grants" :key="grant.id"><strong>{{ grant.displayName }}</strong><small>{{ grant.revokedAt ? messages.collab_revoked : active(grant) ? messages.collab_access_until + ' ' + date(grant.expiresAt) : messages.collab_expired }}</small><div class="action-row"><button v-if="active(grant) && !grant.isPresenter" :disabled="disabled || status === 'finished'" @click="$emit('command', 'presenter.transfer', { grantId: grant.id })">{{ messages.collab_transfer }}</button><span v-if="grant.isPresenter" class="status-pill">{{ messages.collab_presenting }}</span><button v-if="!grant.revokedAt" :disabled="disabled" @click="$emit('command', 'grant.revoke', { id: grant.id })">{{ messages.collab_revoke }}</button></div></li></ul>
            <h3>{{ messages.collab_invitations }}</h3><ul class="collaboration-list"><li v-for="invite in collaboration.invitations ?? []" :key="invite.id"><small>{{ invite.revokedAt ? messages.collab_revoked : invite.acceptedAt ? messages.collab_accepted : active(invite) ? messages.collab_link_until + ' ' + date(invite.expiresAt) : messages.collab_expired }}</small><button v-if="!invite.revokedAt" :disabled="disabled" @click="$emit('command', 'invite.revoke', { id: invite.id })">{{ messages.collab_revoke_invite }}</button></li></ul><p class="field-hint">{{ messages.collab_revoke_hint }}</p>
        </details>
    </section>
</template>
