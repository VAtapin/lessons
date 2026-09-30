import { createApp } from 'vue';
import App from './App.vue';
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
}

const root = document.querySelector<HTMLElement>('#app');
if (root?.dataset.messages && root.dataset.locale) {
    const messages: InterfaceMessages = JSON.parse(root.dataset.messages);
    createApp(App, { locale: root.dataset.locale, messages }).mount(root);
}
