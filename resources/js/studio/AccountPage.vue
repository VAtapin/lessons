<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, errorMessage } from './api';
import { accountState, acceptAccount, announceIdentityChange } from './identity';
import { mayClaim } from './account-helpers';
import { formatBytes } from './library';
import type { Account, GuestClaim, Messages, User } from './types';
const reload = () => location.reload();
const props = defineProps<{ locale: string; messages: Messages; verify?: boolean }>();
const mailFailed = props.verify && new URLSearchParams(location.search).get('notice') === 'mail-unavailable';
const name = ref(accountState.value?.user?.name ?? '');
const uiLocale = ref(accountState.value?.user?.uiLocale ?? props.locale);
const busy = ref(false);
const error = ref('');
const notice = ref('');
const claim = ref<GuestClaim>();
const confirmClaim = ref(false);
const canClaim = computed(() => !!claim.value && mayClaim(claim.value.claim, !!accountState.value?.user?.verified, claim.value.quota.afterClaimBytes, claim.value.quota.limitBytes));
async function action(kind: 'save' | 'resend' | 'logout' | 'preview' | 'claim' | 'guest') {
    busy.value = true; error.value = ''; notice.value = '';
    try {
        if (kind === 'save') { const response = await api<{ user: User }>('/api/account', 'PATCH', { name: name.value, uiLocale: uiLocale.value }); if (accountState.value) acceptAccount({ ...accountState.value, user: response.user }); notice.value = props.messages.profile_saved; }
        if (kind === 'resend') { await api('/api/auth/verification-notification', 'POST'); notice.value = props.messages.verification_requested; }
        if (kind === 'preview') { claim.value = await api<GuestClaim>('/api/account/guest-claim'); confirmClaim.value = false; }
        if (kind === 'claim') { await api('/api/account/guest-claim', 'POST'); announceIdentityChange(); location.assign(`/${props.locale}/studio`); }
        if (kind === 'logout' || kind === 'guest') { await api(kind === 'logout' ? '/api/auth/logout' : '/api/account/guest-continue', 'POST'); announceIdentityChange(); location.assign(`/${props.locale}/${kind === 'guest' ? 'studio' : 'account'}`); }
    } catch (problem) { error.value = errorMessage(problem, props.messages); }
    finally { busy.value = false; }
}
onMounted(async () => { try { acceptAccount(await api<Account>('/api/account')); name.value = accountState.value?.user?.name ?? ''; uiLocale.value = accountState.value?.user?.uiLocale ?? props.locale; } catch (problem) { error.value = errorMessage(problem, props.messages); } });
</script>
<template>
    <div class="page-heading"><h1>{{ verify ? messages.verify_email : messages.account }}</h1><button v-if="accountState?.user" :disabled="busy" @click="action('logout')">{{ messages.logout }}</button></div><p v-if="error" class="error-banner" role="alert">{{ error }}</p><p v-if="notice" class="success-banner" role="status">{{ notice }}</p>
    <p v-if="mailFailed && accountState?.user" class="error-banner" role="alert">{{ messages.account_created_mail_failed }}</p>
    <div v-if="accountState" class="account-layout"><section class="studio-card"><template v-if="accountState.user"><p>{{ accountState.user.email }}</p><p class="status-pill">{{ accountState.user.verified ? messages.email_verified : messages.email_unverified }}</p><div v-if="!accountState.user.verified" class="verification-actions"><p>{{ messages.verification_hint }}</p><button :disabled="busy" @click="action('resend')">{{ messages.resend_verification }}</button><button :disabled="busy" @click="reload">{{ messages.check_verification }}</button></div><form @submit.prevent="action('save')"><label>{{ messages.profile_name }}<input v-model="name" autocomplete="name" required :disabled="busy" /></label><label>{{ messages.interface_language }}<select v-model="uiLocale" :disabled="busy"><option value="ru">Русский</option><option value="de">Deutsch</option></select></label><button class="primary" :disabled="busy || !name.trim() || Array.from(name).length > 80">{{ messages.save_profile }}</button></form></template><template v-else><p>{{ messages.guest_notice }}</p><nav class="auth-links"><a class="button-link" :href="`/${locale}/login`">{{ messages.auth_login }}</a><a class="button-link" :href="`/${locale}/register`">{{ messages.auth_register }}</a><button v-if="accountState.guestClaimAvailable" :disabled="busy" @click="action('guest')">{{ messages.restore_guest }}</button></nav></template></section>
        <section class="studio-card"><h2>{{ messages.media_quota }}</h2><p>{{ formatBytes(accountState.quota.usedBytes) }} / {{ formatBytes(accountState.quota.limitBytes) }}</p><p class="field-hint">{{ messages.quota_hint }} {{ formatBytes(accountState.quota.maxFileBytes) }}</p><template v-if="accountState.user"><h2>{{ messages.guest_claim }}</h2><p class="field-hint">{{ messages.claim_hint }}</p><button :disabled="busy" @click="action('preview')">{{ messages.preview_claim }}</button><div v-if="claim" class="claim-preview"><p>{{ messages['claim_' + claim.claim.status] }}</p><dl><div v-for="(count, key) in claim.claim.counts" :key="key"><dt>{{ messages['claim_count_' + key] }}</dt><dd>{{ count }}</dd></div></dl><p>{{ messages.claim_bytes }}: {{ formatBytes(claim.claim.bytes) }}</p><p>{{ messages.quota_after_claim }}: {{ formatBytes(claim.quota.afterClaimBytes) }} / {{ formatBytes(claim.quota.limitBytes) }}</p><p v-if="claim.verificationRequired" class="info-banner">{{ messages.claim_verify_first }}</p><p v-if="claim.quota.afterClaimBytes > claim.quota.limitBytes" class="error-banner">{{ messages.error_quota_exceeded }}</p><button v-if="claim.claim.available" :disabled="busy" @click="action('guest')">{{ messages.restore_guest }}</button><button v-if="canClaim && !confirmClaim" :disabled="busy" @click="confirmClaim = true">{{ messages.transfer_guest }}</button><div v-if="confirmClaim && canClaim" class="info-banner"><p>{{ messages.claim_confirm }}</p><div class="action-row"><button :disabled="busy" @click="action('claim')">{{ messages.confirm_transfer }}</button><button :disabled="busy" @click="confirmClaim = false">{{ messages.cancel }}</button></div></div></div></template></section>
    </div>
</template>
