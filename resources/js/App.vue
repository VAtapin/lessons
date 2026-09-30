<script setup lang="ts">
import type { InterfaceMessages } from './app';
import hero from '../../UI-Design/1.png';
import logo from '../../UI-Design/logo_kl.png';

defineProps<{ locale: string; messages: InterfaceMessages }>();
</script>

<template>
    <div class="site-shell">
        <header class="site-header">
            <a class="brand" :href="`/${locale}`" aria-label="lessons.atapin.de">
                <img :src="logo" alt="" width="82" height="74" />
                <span><strong>lessons.atapin.de</strong><small>{{ messages.tagline }}</small></span>
            </a>
            <nav class="language-nav" :aria-label="messages.language">
                <a href="/ru" lang="ru" :aria-current="locale === 'ru' ? 'page' : undefined">RU</a>
                <a href="/de" lang="de" :aria-current="locale === 'de' ? 'page' : undefined">DE</a>
            </nav>
        </header>
        <main>
            <section class="hero">
                <div class="hero-copy">
                    <p class="eyebrow">{{ messages.eyebrow }}</p>
                    <h1>{{ messages.title }}</h1>
                    <p class="description">{{ messages.description }}</p>
                    <div class="preparation">
                        <span class="status-dot" aria-hidden="true"></span><span>{{ messages.status }}</span>
                    </div>
                </div>
                <div class="hero-image"><img :src="hero" :alt="messages.image_alt" width="1672" height="941" fetchpriority="high" /></div>
            </section>
            <section class="coming-soon"><p>{{ messages.coming_soon }}</p></section>
        </main>
        <footer><span>lessons.atapin.de</span><p>{{ messages.footer }}</p></footer>
    </div>
</template>
