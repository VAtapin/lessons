<script setup lang="ts">
import { reactive } from 'vue';
import { filterOptions, filterQuery, readFilters, type FilterKey } from './filters';
import PublicIcon from './PublicIcon.vue';
import type { CatalogMessages } from './types';
const props = defineProps<{ locale: string; messages: CatalogMessages; full?: boolean }>();
const filters = reactive(readFilters(window.location.search));
const groups: FilterKey[] = ['age', 'audience', 'topic', 'format'];
const icons = ['people', 'people', 'book', 'file'];
function values(key: FilterKey): readonly string[] { return key === 'audience' && !props.full ? filterOptions.audience.slice(0, 4) : filterOptions[key]; }
function submit() { window.location.assign(`/${props.locale}/catalog${filterQuery(filters) ? `?${filterQuery(filters)}` : ''}`); }
</script>
<template>
    <form id="find-materials" class="material-picker" @submit.prevent="submit">
        <h2>{{ messages.find_title }}</h2>
        <div class="picker-groups">
            <fieldset v-for="(key, index) in groups" :key="key">
                <legend><PublicIcon :name="icons[index]!" />{{ messages[`filter_${key}`] }}</legend>
                <div class="filter-chips"><button v-for="value in values(key)" :key="value" type="button" :aria-pressed="filters[key] === value" @click="filters[key] = filters[key] === value ? '' : value">{{ messages[`${key}_${value}`] }}</button></div>
            </fieldset>
        </div>
        <div class="picker-search">
            <label><span>{{ messages.search_label }}</span><input v-model="filters.q" type="search" maxlength="120" :placeholder="messages.search_placeholder" /></label>
            <label v-if="full" class="duration-select"><span>{{ messages.filter_duration }}</span><select v-model="filters.duration"><option value="">{{ messages.any_duration }}</option><option v-for="value in filterOptions.duration" :key="value" :value="value">{{ messages[`duration_${value}`] }}</option></select></label>
            <button class="public-button" type="submit">{{ messages.show_materials }}<PublicIcon name="arrow" /></button>
            <a v-if="full" :href="`/${locale}/catalog`" class="picker-reset">{{ messages.reset_filters }}</a>
        </div>
    </form>
</template>
