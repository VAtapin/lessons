import { createApp } from 'vue';
import App from './App.vue';
import StudioRoot from './studio/StudioRoot.vue';
import '../css/app.css';

export interface InterfaceMessages { [key: string]: string; }

const root = document.querySelector<HTMLElement>('#app');
if (root?.dataset.messages && root.dataset.locale) {
    const messages: InterfaceMessages = JSON.parse(root.dataset.messages);
    if (root.dataset.page && !['home', 'catalog'].includes(root.dataset.page)) {
        createApp(StudioRoot, {
            page: root.dataset.page,
            locale: root.dataset.locale,
            context: JSON.parse(root.dataset.context || '{}'),
            messages: JSON.parse(root.dataset.studioMessages || '{}'),
        }).mount(root);
    } else {
        createApp(App, { locale: root.dataset.locale, messages, studioMessages: JSON.parse(root.dataset.studioMessages || '{}'), page: root.dataset.page || 'home', context: JSON.parse(root.dataset.context || '{}') }).mount(root);
    }
}
