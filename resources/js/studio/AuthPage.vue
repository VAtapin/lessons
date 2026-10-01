<script setup lang="ts">
import { computed, ref } from 'vue';
import { api, ApiError, errorMessage } from './api';
import { announceIdentityChange } from './identity';
import { passwordValid, registrationRecoveryPath } from './account-helpers';
import type { Account, Messages, PageContext, User } from './types';
const props = defineProps<{ kind: string; locale: string; context: PageContext; messages: Messages }>();
const email = ref(props.context.email ?? '');
const name = ref('');
const password = ref('');
const confirmation = ref('');
const uiLocale = ref(props.locale);
const busy = ref(false);
const error = ref('');
const accepted = ref(false);
const withPassword = computed(() => ['login', 'register', 'reset-password'].includes(props.kind));
const newPassword = computed(() => ['register', 'reset-password'].includes(props.kind));
async function submit() {
    if (busy.value) return;
    busy.value = true; error.value = '';
    try {
        const body = props.kind === 'register' ? { name: name.value, email: email.value, password: password.value, passwordConfirmation: confirmation.value, uiLocale: uiLocale.value } : props.kind === 'login' ? { email: email.value, password: password.value } : props.kind === 'reset-password' ? { email: email.value, token: props.context.resetToken, password: password.value, passwordConfirmation: confirmation.value } : { email: email.value };
        const result = await api<{ user?: User }>(`/api/auth/${props.kind}`, 'POST', body);
        password.value = ''; confirmation.value = '';
        if (props.kind === 'login' || props.kind === 'register') { announceIdentityChange(); location.assign(`/${result.user?.uiLocale ?? props.locale}/${props.kind === 'register' ? 'verify-email' : 'studio'}`); }
        else { accepted.value = true; if (props.kind === 'reset-password') announceIdentityChange(); }
    } catch (problem) {
        error.value = errorMessage(problem, props.messages);
        if (props.kind === 'register' && problem instanceof ApiError && problem.code === 'mail_unavailable') {
            password.value = ''; confirmation.value = '';
            try {
                const account = await api<Account>('/api/account');
                const path = registrationRecoveryPath(props.kind, problem.code, account.user);
                if (path) { announceIdentityChange(); location.assign(path); }
            } catch { /* Keep the honest mail error if the account refresh also fails. */ }
        }
    }
    finally { busy.value = false; }
}
</script>
<template>
    <div class="page-heading"><h1>{{ messages['auth_' + kind.replaceAll('-', '_')] }}</h1></div><p v-if="error" role="alert" class="error-banner">{{ error }}</p>
    <section v-if="accepted" class="studio-card auth-card"><p role="status">{{ messages[kind === 'reset-password' ? 'password_reset_done' : 'reset_mail_requested'] }}</p><a class="button-link" :href="`/${locale}/login`">{{ messages.auth_login }}</a></section>
    <form v-else class="studio-card auth-card" @submit.prevent="submit"><fieldset :disabled="busy" class="editor-fields"><label v-if="kind === 'register'">{{ messages.profile_name }}<input v-model="name" autocomplete="name" required /></label><label>{{ messages.email }}<input v-model="email" type="email" autocomplete="email" maxlength="254" required /></label><label v-if="withPassword">{{ messages.password }}<input v-model="password" type="password" :autocomplete="newPassword ? 'new-password' : 'current-password'" required /></label><template v-if="newPassword"><p class="field-hint">{{ messages.password_hint }}</p><label>{{ messages.password_confirmation }}<input v-model="confirmation" type="password" autocomplete="new-password" required /></label><p v-if="password && !passwordValid(password)" class="field-hint">{{ messages.password_hint }}</p></template><label v-if="kind === 'register'">{{ messages.interface_language }}<select v-model="uiLocale"><option value="ru">Русский</option><option value="de">Deutsch</option></select></label><button class="primary" :disabled="busy || (newPassword && (!passwordValid(password) || password !== confirmation)) || (kind === 'register' && (!name.trim() || Array.from(name).length > 80))">{{ busy ? messages.saving : messages['auth_' + kind.replaceAll('-', '_')] }}</button></fieldset><nav class="auth-links"><a v-if="kind !== 'login'" :href="`/${locale}/login`">{{ messages.auth_login }}</a><a v-if="kind !== 'register'" :href="`/${locale}/register`">{{ messages.auth_register }}</a><a v-if="kind === 'login'" :href="`/${locale}/forgot-password`">{{ messages.auth_forgot_password }}</a><a :href="`/${locale}/studio`">{{ messages.continue_guest }}</a></nav></form>
</template>
