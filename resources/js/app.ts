import { createApp } from 'vue';
import App from './App.vue';
import StudioRoot from './studio/StudioRoot.vue';
import '../css/app.css';

export interface InterfaceMessages {
    tagline: string;
    language: string;
    eyebrow: string;
    title: string;
    description: string;
    status: string;
    coming_soon: string;
    footer: string;
    image_alt: string;
    open_studio: string;
    join_session: string;
}

const root = document.querySelector<HTMLElement>('#app');
if (root?.dataset.messages && root.dataset.locale) {
    const messages: InterfaceMessages = JSON.parse(root.dataset.messages);
    if (root.dataset.page && root.dataset.page !== 'home') {
        createApp(StudioRoot, {
            page: root.dataset.page,
            locale: root.dataset.locale,
            context: JSON.parse(root.dataset.context || '{}'),
            messages: JSON.parse(root.dataset.studioMessages || '{}'),
        }).mount(root);
    } else {
        createApp(App, { locale: root.dataset.locale, messages }).mount(root);
    }
}
