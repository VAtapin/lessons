<script setup lang="ts">
defineProps<{ name: string }>();
const paths: Record<string, string> = {
    search: 'M21 21l-6-6 M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0',
    people: 'M16 21v-3a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v3 M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M20 21v-3a4 4 0 0 0-3-4 M17 2a4 4 0 0 1 0 8',
    book: 'M12 5v16 M12 5C8 2 4 3 2 4v16c4-2 7-1 10 1 3-2 6-3 10-1V4c-2-1-6-2-10 1',
    file: 'M14 2H5v20h14V7z M14 2v6h5 M8 12h8 M8 16h8',
    screen: 'M2 3h20v14H2z M12 17v5 M7 22h10',
    game: 'M12 2l10 6v10l-10 5-10-5V8z M2 8l10 6 10-6 M12 14v9 M7 7v1 M17 12v1 M6 14v1 M17 17v1',
    question: 'M9 8a3 3 0 1 1 5 2c-2 1-2 2-2 3 M12 17h.01 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
    pencil: 'M3 21l1-6L17 2l5 5L9 20z M14 5l5 5 M4 15l5 5',
    arrow: 'M4 12h16 M14 6l6 6-6 6',
};
</script>
<template><svg class="public-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="paths[name] || paths.book" /></svg></template>
