<script setup lang="ts">
defineProps<{ name: string }>();
const paths: Record<string, string> = {
    menu: 'M4 6h16M4 12h16M4 18h16', close: 'm6 6 12 12M18 6 6 18',
    overview: 'm3 10 9-7 9 7M5 9v12h14V9M9 21v-8h6v8',
    screen: 'M3 4h18v13H3zM12 17v4M8 21h8',
    lessons: 'M4 4h4v16H4zM10 4h4v16h-4zM16 5l4-1 3 15-4 1z',
    edit: 'm4 16 12-12 4 4L8 20l-5 1zM14 6l4 4',
    library: 'm12 3 9 5v10l-9 5-9-5V8zM3 8l9 5 9-5M12 13v10',
    media: 'M3 4h18v16H3zM3 17l6-7 5 5 3-4 4 6M16 8h.01',
    history: 'M3 11a9 9 0 1 1 2 7M3 4v7h7M12 7v6l4 2',
    account: 'M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8M4 22v-2a8 8 0 0 1 16 0v2',
    catalog: 'M3 5h8l1 2h9v13H3zM7 11h10M7 15h7',
    help: 'M9 9a3 3 0 0 1 6 0c0 2-3 2-3 5M12 18h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
    join: 'M4 3h12v18H4M10 12h12m-4-4 4 4-4 4',
    question: 'M3 4h14v11H9l-6 4zM17 9h4v13l-5-4h-4',
    text: 'M4 5h16M12 5v16M8 21h8',
    heart: 'M12 21 3 12C-2 5 7 1 12 7c5-6 14-2 9 5z',
};
</script>
<template><svg class="studio-icon" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path :d="paths[name] ?? paths.text" /></svg></template>
