<script setup lang="ts">
import type { InterfaceMessages } from './app';
import CatalogFilters from './catalog/CatalogFilters.vue';
import CatalogPage from './catalog/CatalogPage.vue';
import PublicIcon from './catalog/PublicIcon.vue';
import { catalogLink } from './catalog/filters';
import hero from '../../UI-Design/1.png';
import landscape from '../../UI-Design/2.png';
import logo from '../../UI-Design/logo_kl.png';
import school from '../../UI-Design/3.png';
import sunday from '../../UI-Design/4.png';
import children from '../../UI-Design/5.png';
import adults from '../../UI-Design/6.png';
import bible from '../../UI-Design/19.png';
import reading from '../../UI-Design/22.png';
import mercy from '../../UI-Design/15.png';
const props = defineProps<{ locale: string; messages: InterfaceMessages; page?: string; studioMessages?: InterfaceMessages; context?: { slug?: string } }>();
const audiences = [{ key: 'school', image: school }, { key: 'sunday-school', image: sunday }, { key: 'children', image: children }, { key: 'adults', image: adults }];
const topics = [{ key: 'bible', image: bible }, { key: 'holidays', image: reading }, { key: 'parables', image: landscape }, { key: 'family', image: sunday }, { key: 'prayer', image: adults }, { key: 'mercy', image: mercy }];
const formats = [{ key: 'presentation', icon: 'screen' }, { key: 'notes', icon: 'file' }, { key: 'game', icon: 'game' }, { key: 'questions', icon: 'question' }, { key: 'worksheet', icon: 'pencil' }, { key: 'interactive', icon: 'screen' }];
const navigation = ['about', 'audiences', 'topics', 'formats', 'contact'];
const homeAnchor = (key: string) => props.page === 'catalog' ? `/${props.locale}#${key}` : `#${key}`;
const languageLink = (locale: string) => `/${locale}${props.page === 'catalog' ? `/catalog${props.context?.slug ? `/${encodeURIComponent(props.context.slug)}` : ''}` : ''}${window.location.search}`;
</script>

<template>
    <div class="public-site">
        <a class="skip-link" href="#main-content">{{ messages.catalog_title }}</a>
        <header class="public-header">
            <a class="public-brand" :href="`/${locale}`"><img :src="logo" alt="" width="72" height="64" /><span><strong>lessons.atapin.de</strong><small>{{ messages.tagline }}</small></span></a>
            <nav class="public-nav" :aria-label="messages.home"><a v-for="key in navigation" :key="key" :href="homeAnchor(key)">{{ messages[`nav_${key}`] }}</a></nav>
            <a class="header-search" :href="page === 'catalog' ? '#find-materials' : homeAnchor('find-materials')" :aria-label="messages.search_label"><PublicIcon name="search" /></a>
            <a class="public-button header-choose" :href="catalogLink(locale)">{{ messages.choose_lesson }}</a>
            <nav class="public-languages" :aria-label="messages.language"><a v-for="language in ['ru', 'de']" :key="language" :href="languageLink(language)" :lang="language" :aria-current="locale === language ? 'page' : undefined">{{ language.toUpperCase() }}</a></nav>
        </header>
        <main id="main-content">
            <template v-if="page !== 'catalog'">
                <section class="public-hero" :style="{ '--hero-image': `url(${hero})` }">
                    <div class="hero-art" role="img" :aria-label="messages.image_alt"></div>
                    <div class="hero-botanical" aria-hidden="true"><svg viewBox="0 0 180 400"><path d="M8 0Q95 150 32 370" fill="none" stroke="#8a794c" stroke-width="3"/><g fill="#8b8756"><ellipse v-for="n in 13" :key="n" :cx="36 + Math.sin(n) * 23" :cy="n * 25" rx="9" ry="25" :transform="`rotate(${n % 2 ? -45 : 45} ${36 + Math.sin(n) * 23} ${n * 25})`" /></g></svg></div>
                    <div class="public-hero-copy"><p class="hero-eyebrow">{{ messages.eyebrow }}</p><h1>{{ messages.title }}</h1><p class="hero-description">{{ messages.description }}</p><div class="public-actions"><a class="public-button" href="#topics">{{ messages.view_topics }}<PublicIcon name="arrow" /></a><a class="public-button secondary" href="#find-materials">{{ messages.start_selection }}</a></div><p class="hero-blessing">«{{ messages.footer }}»</p></div>
                    <p class="hero-handwriting" aria-hidden="true">{{ messages.eyebrow }}<br />{{ messages.tagline }} ♡</p>
                </section>
                <div class="public-content">
                    <CatalogFilters :locale="locale" :messages="messages" />
                    <section id="audiences" class="home-section"><h2>{{ messages.audiences_title }}</h2><p>{{ messages.audiences_intro }}</p><div class="audience-grid"><a v-for="card in audiences" :key="card.key" class="illustrated-card" :href="catalogLink(locale, 'audience', card.key)"><img :src="card.image" alt="" width="1448" height="1086" loading="lazy" /><h3>{{ messages[`card_${card.key}`] }}</h3><p>{{ messages[`card_${card.key}_text`] }}</p></a></div></section>
                    <section id="topics" class="home-section"><h2>{{ messages.topics_title }}</h2><p>{{ messages.topics_intro }}</p><div class="topic-grid"><a v-for="card in topics" :key="card.key" class="illustrated-card topic-card" :href="catalogLink(locale, 'topic', card.key)"><img :src="card.image" alt="" width="1672" height="941" loading="lazy" /><h3>{{ messages[`card_${card.key}`] }}</h3></a></div></section>
                    <section id="steps" class="home-section"><h2>{{ messages.steps_title }}</h2><p>{{ messages.steps_intro }}</p><ol class="home-steps"><li v-for="(icon, index) in ['search', 'file', 'people']" :key="icon"><span class="step-number">{{ index + 1 }}</span><span class="step-icon"><PublicIcon :name="icon" /></span><div><h3>{{ messages[`step_${index + 1}`] }}</h3><p>{{ messages[`step_${index + 1}_text`] }}</p></div><PublicIcon v-if="index < 2" name="arrow" /></li></ol></section>
                    <section id="formats" class="home-section"><h2>{{ messages.formats_title }}</h2><p>{{ messages.formats_intro }}</p><div class="format-grid"><a v-for="format in formats" :key="format.key" :href="catalogLink(locale, 'format', format.key)"><PublicIcon :name="format.icon" /><div><h3>{{ messages[`feature_${format.key}`] }}</h3><p>{{ messages[`feature_${format.key}_text`] }}</p></div></a></div></section>
                </div>
                <section class="landscape-cta" :style="{ backgroundImage: `url(${landscape})` }"><div><h2>{{ messages.cta_title }}</h2><p>{{ messages.cta_text }}</p><a class="public-button" :href="catalogLink(locale)">{{ messages.open_catalog }}<PublicIcon name="arrow" /></a></div><p class="cta-handwriting" aria-hidden="true">{{ messages.tagline }} ♡</p></section>
                <section id="about" class="home-about public-content"><div><h2>{{ messages.nav_about }}</h2><p>{{ messages.about_text }}</p></div><div class="public-actions"><a class="public-button" :href="catalogLink(locale)">{{ messages.start_guest }}</a><a class="public-button secondary" :href="`/${locale}/studio`">{{ messages.create_own }}</a><a :href="`/${locale}/join`">{{ messages.join_session }}</a></div></section>
            </template>
            <CatalogPage v-else :locale="locale" :messages="messages" :slug="context?.slug" :studio-messages="studioMessages" />
        </main>
        <footer id="contact" class="public-footer"><div class="footer-main"><a class="public-brand" :href="`/${locale}`"><img :src="logo" alt="" width="64" height="58" /><span><strong>lessons.atapin.de</strong><small>{{ messages.tagline }}</small></span></a><nav :aria-label="messages.nav_contact"><a v-for="key in navigation" :key="key" :href="homeAnchor(key)">{{ messages[`nav_${key}`] }}</a></nav><a href="mailto:info@atapin.de">info@atapin.de</a><a :href="`/${locale}/studio`">{{ messages.open_studio }}</a></div><div class="footer-bottom"><span>© {{ new Date().getFullYear() }} lessons.atapin.de</span><span>{{ messages.eyebrow }}</span></div></footer>
    </div>
</template>
