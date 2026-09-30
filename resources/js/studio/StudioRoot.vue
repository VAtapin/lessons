<script setup lang="ts">
import { ref } from 'vue';
import StudioList from './StudioList.vue';
import LibraryPage from './LibraryPage.vue';
import MediaPage from './MediaPage.vue';
import LessonEditor from './LessonEditor.vue';
import TeacherPanel from './TeacherPanel.vue';
import JoinForm from './JoinForm.vue';
import PublicSession from './PublicSession.vue';
import type { Messages, PageContext } from './types';
import logo from '../../../UI-Design/logo_kl.png';
import '../../css/studio.css';
defineProps<{ page: string; context: PageContext; locale: string; messages: Messages }>();
const menuOpen = ref(false);
const controlQuery = window.location.search;
const joinCode = new URLSearchParams(controlQuery).get('code');
const joinQuery = joinCode ? `?${new URLSearchParams({ code: joinCode }).toString()}` : '';
function languagePath(page: string, language: string, context: PageContext): string {
    const path = page === 'studio' ? '/studio'
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
        <header class="studio-header"><button v-if="page !== 'projector' && page !== 'control'" class="menu-toggle" :aria-label="menuOpen ? messages.close_menu : messages.open_menu" :aria-expanded="menuOpen" aria-controls="studio-menu" @click="menuOpen = !menuOpen">☰</button><a class="studio-brand" :href="`/${locale}`"><img class="brand-mark" :src="logo" alt="" width="46" height="42" /><strong>lessons.atapin.de</strong></a><span class="header-caption">{{ messages.workspace }}</span><nav class="studio-languages" :aria-label="messages.interface_language"><a v-for="language in ['ru', 'de']" :key="language" :href="languagePath(page, language, context)" :aria-current="locale === language ? 'page' : undefined">{{ language.toUpperCase() }}</a></nav></header>
        <div class="studio-body">
            <aside v-if="menuOpen && page !== 'projector' && page !== 'control'" id="studio-menu" class="global-sidebar"><div class="section-heading"><strong>{{ messages.workspace }}</strong><button class="icon-button" :aria-label="messages.close_menu" @click="menuOpen = false">×</button></div><nav><a :href="`/${locale}/studio`">{{ messages.my_materials }}</a><a :href="`/${locale}/library`">{{ messages.block_library }}</a><a :href="`/${locale}/media`">{{ messages.media_library }}</a><a :href="`/${locale}/join`">{{ messages.student_join }}</a><a :href="`/${locale}`">{{ messages.home }}</a></nav><p>{{ messages.guest_notice }}</p></aside>
            <main class="studio-workspace">
                <StudioList v-if="page === 'studio'" :locale="locale" :messages="messages" />
                <LibraryPage v-else-if="page === 'library'" :locale="locale" :messages="messages" />
                <MediaPage v-else-if="page === 'media'" :locale="locale" :messages="messages" />
                <LessonEditor v-else-if="page === 'editor' && context.lessonId" :lesson-id="context.lessonId" :locale="locale" :messages="messages" />
                <TeacherPanel v-else-if="page === 'teacher' && context.sessionId" :session-id="context.sessionId" :locale="locale" :messages="messages" />
                <TeacherPanel v-else-if="page === 'control' && context.sessionId" :session-id="context.sessionId" :locale="locale" :messages="messages" compact />
                <JoinForm v-else-if="page === 'join'" :locale="locale" :messages="messages" />
                <PublicSession v-else-if="page === 'student' && context.sessionId" mode="student" :session-id="context.sessionId" :messages="messages" />
                <PublicSession v-else-if="page === 'projector' && context.projectorToken" mode="projector" :projector-token="context.projectorToken" :messages="messages" />
                <p v-else role="alert" class="error-banner">{{ messages.error_not_found }}</p>
            </main>
        </div>
    </div>
</template>
