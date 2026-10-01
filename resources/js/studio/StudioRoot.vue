<script setup lang="ts">
import { computed, nextTick, onMounted, onBeforeUnmount, ref } from 'vue';
import { api, errorMessage } from './api';
import { accountState, acceptAccount, identityBlocked, startIdentityWatch } from './identity';
import AuthPage from './AuthPage.vue';
import AccountPage from './AccountPage.vue';
import HistoryPage from './HistoryPage.vue';
import StudioList from './StudioList.vue';
import LibraryPage from './LibraryPage.vue';
import MediaPage from './MediaPage.vue';
import LessonEditor from './LessonEditor.vue';
import TeacherPanel from './TeacherPanel.vue';
import JoinForm from './JoinForm.vue';
import PublicSession from './PublicSession.vue';
import StudioIcon from './StudioIcon.vue';
import type { Account, Messages, PageContext } from './types';
import logo from '../../../UI-Design/logo_kl.png';
import '../../css/studio.css';
import '../../css/workspace-art.css';
const props = defineProps<{ page: string; context: PageContext; locale: string; messages: Messages }>();
const privatePage = !['join', 'student', 'projector'].includes(props.page);
const ready = ref(!privatePage);
const identityError = ref('');
const reload = () => location.reload();
let stopWatch: (() => void) | undefined;
async function refreshAccount() { if (!privatePage || identityBlocked.value) return; try { acceptAccount(await api<Account>('/api/account')); ready.value = true; identityError.value = ''; } catch (problem) { identityError.value = errorMessage(problem, props.messages); } }
onMounted(() => { if (privatePage) { stopWatch = startIdentityWatch(refreshAccount); void refreshAccount(); } });
onBeforeUnmount(() => stopWatch?.());
const menuOpen = ref(false);
const menuToggle = ref<HTMLButtonElement>();
const menuClose = ref<HTMLButtonElement>();
async function toggleMenu() { menuOpen.value = !menuOpen.value; await nextTick(); if (menuOpen.value) menuClose.value?.focus(); else menuToggle.value?.focus(); }
async function closeMenu() { menuOpen.value = false; await nextTick(); menuToggle.value?.focus(); }
const workspaceView = new URLSearchParams(window.location.search).get('view');
const navigation = computed(() => [
    { icon: 'overview', label: props.messages.workspace_overview, href: `/${props.locale}/studio?view=overview`, active: props.page === 'studio' && workspaceView === 'overview' },
    ...(props.page === 'teacher' && props.context.sessionId ? [{ icon: 'screen', label: props.messages.teacher_panel, href: `/${props.locale}/teach/${props.context.sessionId}`, active: true }] : []),
    { icon: 'lessons', label: props.messages.workspace_lessons, href: `/${props.locale}/studio`, active: props.page === 'studio' && !['overview', 'constructor'].includes(workspaceView ?? '') },
    { icon: 'edit', label: props.messages.constructor, href: props.page === 'editor' ? `/${props.locale}/studio/lessons/${props.context.lessonId}` : `/${props.locale}/studio?view=constructor`, active: props.page === 'editor' || (props.page === 'studio' && workspaceView === 'constructor') },
    { icon: 'library', label: props.messages.block_library, href: `/${props.locale}/library`, active: props.page === 'library' },
    { icon: 'media', label: props.messages.media_library, href: `/${props.locale}/media`, active: props.page === 'media' },
    { icon: 'history', label: props.messages.history, href: `/${props.locale}/history`, active: props.page === 'history' },
    { icon: 'catalog', label: props.messages.workspace_catalog, href: `/${props.locale}/catalog`, active: false },
]);
const controlQuery = window.location.search;
const joinCode = new URLSearchParams(controlQuery).get('code');
const joinQuery = joinCode ? `?${new URLSearchParams({ code: joinCode }).toString()}` : '';
function languagePath(page: string, language: string, context: PageContext): string {
    const path = ['login','register','forgot-password','verify-email','account'].includes(page) ? '/' + page
        : page === 'reset-password' ? '/reset-password/' + encodeURIComponent(context.resetToken ?? '') + (context.email ? '?' + new URLSearchParams({ email: context.email }) : '')
        : page === 'history' ? '/history' + (context.sessionId ? '/' + context.sessionId : '')
        : page === 'rehearsal' ? '/rehearsal/' + context.sessionId + '/' + context.audience
        : page === 'studio' ? '/studio' + (workspaceView ? '?' + new URLSearchParams({ view: workspaceView }) : '')
        : page === 'library' ? '/library'
        : page === 'media' ? '/media'
        : page === 'editor' ? '/studio/lessons/' + context.lessonId
        : page === 'control' ? '/control/' + context.sessionId + controlQuery
        : page === 'teacher' ? '/teach/' + context.sessionId
        : page === 'join' ? '/join' + joinQuery
        : page === 'student' ? '/participate/' + context.sessionId
        : '/project/' + context.projectorToken;
    return '/' + language + path;
}
</script>
<template>
    <div :class="['studio-shell', { 'menu-open': menuOpen }]">
        <header class="studio-header"><button v-if="page !== 'projector' && page !== 'control'" ref="menuToggle" class="menu-toggle" :aria-label="menuOpen ? messages.close_menu : messages.open_menu" :aria-expanded="menuOpen" aria-controls="studio-menu" @click="toggleMenu"><StudioIcon name="menu" /></button><a class="studio-brand" :href="`/${locale}`"><img class="brand-mark" :src="logo" alt="" width="46" height="42" /><strong>lessons.atapin.de</strong></a><span class="header-caption">{{ privatePage ? messages.workspace_caption : messages.student_screen }}</span><nav class="studio-languages" :aria-label="messages.interface_language"><a v-for="language in ['ru', 'de']" :key="language" :href="languagePath(page, language, context)" :aria-current="locale === language ? 'page' : undefined">{{ language.toUpperCase() }}</a></nav><a v-if="privatePage && page !== 'control'" class="header-account" :href="`/${locale}/account`" :aria-label="accountState?.user?.name ?? messages.account" :title="accountState?.user?.name ?? messages.account"><span v-if="accountState?.user" aria-hidden="true">{{ accountState.user.name.slice(0, 1).toLocaleUpperCase(locale) }}</span><StudioIcon v-else name="account" /></a></header>
        <div class="studio-body">
            <button v-if="menuOpen" class="menu-backdrop" tabindex="-1" :aria-label="messages.close_menu" @click="closeMenu"></button>
            <Transition name="workspace-menu"><aside v-if="menuOpen && page !== 'projector' && page !== 'control'" id="studio-menu" class="global-sidebar" @keydown.esc.prevent="closeMenu"><div class="section-heading"><strong>{{ messages.workspace_caption }}</strong><button ref="menuClose" class="icon-button" :aria-label="messages.close_menu" @click="closeMenu"><StudioIcon name="close" /></button></div><nav :aria-label="messages.workspace_caption"><a v-for="item in navigation" :key="item.icon" :href="item.href" :aria-current="item.active ? 'page' : undefined"><StudioIcon :name="item.icon" /><span>{{ item.label }}</span></a></nav><div class="sidebar-secondary"><a :href="`/${locale}/account`"><StudioIcon name="account" />{{ accountState?.user?.name ?? messages.account }}</a><a v-if="!accountState?.user" :href="`/${locale}/login`">{{ messages.auth_login }}</a><a :href="`/${locale}/join`"><StudioIcon name="join" />{{ messages.student_join }}</a><a :href="`/${locale}`"><StudioIcon name="help" />{{ messages.home }}</a></div></aside></Transition>
            <main class="studio-workspace">
                <div v-if="identityBlocked" class="error-banner" role="alert"><p>{{ messages.identity_changed_hint }}</p><button @click="reload">{{ messages.reload_workspace }}</button></div><div v-if="identityError" class="error-banner" role="alert">{{ identityError }} <button @click="refreshAccount">{{ messages.retry_command }}</button></div><p v-if="!ready" role="status">{{ messages.loading }}</p>
                <div v-if="ready" v-show="!identityBlocked" :inert="identityBlocked ? true : undefined">
                <AuthPage v-if="['login', 'register', 'forgot-password', 'reset-password'].includes(page)" :kind="page" :context="context" :locale="locale" :messages="messages" />
                <AccountPage v-else-if="page === 'account' || page === 'verify-email'" :verify="page === 'verify-email'" :locale="locale" :messages="messages" />
                <HistoryPage v-else-if="page === 'history'" :session-id="context.sessionId" :locale="locale" :messages="messages" />
                <PublicSession v-else-if="page === 'rehearsal' && context.sessionId && context.audience" :mode="context.audience" :session-id="context.sessionId" :messages="messages" rehearsal />
                <StudioList v-else-if="page === 'studio'" :locale="locale" :messages="messages" />
                <LibraryPage v-else-if="page === 'library'" :locale="locale" :messages="messages" />
                <MediaPage v-else-if="page === 'media'" :locale="locale" :messages="messages" />
                <LessonEditor v-else-if="page === 'editor' && context.lessonId" :lesson-id="context.lessonId" :locale="locale" :messages="messages" />
                <TeacherPanel v-else-if="page === 'teacher' && context.sessionId" :session-id="context.sessionId" :locale="locale" :messages="messages" />
                <TeacherPanel v-else-if="page === 'control' && context.sessionId" :session-id="context.sessionId" :locale="locale" :messages="messages" compact />
                <JoinForm v-else-if="page === 'join'" :locale="locale" :messages="messages" />
                <PublicSession v-else-if="page === 'student' && context.sessionId" mode="student" :session-id="context.sessionId" :messages="messages" />
                <PublicSession v-else-if="page === 'projector' && context.projectorToken" mode="projector" :projector-token="context.projectorToken" :messages="messages" />
                <p v-else role="alert" class="error-banner">{{ messages.error_not_found }}</p>
                </div>
            </main>
        </div>
    </div>
</template>
