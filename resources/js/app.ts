import { createApp } from 'vue';
import '../css/app.css';

export interface InterfaceMessages { [key: string]: string; }

const root = document.querySelector<HTMLElement>('#app');
async function mount() {
    if (root?.dataset.messages && root.dataset.locale) {
        const messages: InterfaceMessages = JSON.parse(root.dataset.messages);
        if (root.dataset.page && !['home', 'catalog'].includes(root.dataset.page)) {
            const { default: StudioRoot } = await import('./studio/StudioRoot.vue');
            createApp(StudioRoot, {
                page: root.dataset.page,
                locale: root.dataset.locale,
                context: JSON.parse(root.dataset.context || '{}'),
                messages: JSON.parse(root.dataset.studioMessages || '{}'),
            }).mount(root);
        } else {
            const { default: App } = await import('./App.vue');
            createApp(App, { locales: JSON.parse(root.dataset.locales || '[]'), locale: root.dataset.locale, messages, studioMessages: JSON.parse(root.dataset.studioMessages || '{}'), page: root.dataset.page || 'home', context: JSON.parse(root.dataset.context || '{}') }).mount(root);
        }
    }
}
void mount();
