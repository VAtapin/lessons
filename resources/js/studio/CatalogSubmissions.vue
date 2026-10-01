<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api } from './api';
import { plainCopy, adminError, publicationMetadata, submissionPayload, type PublicationMetadata, type Submission, type TaxonomyTerm, type TermKind } from './admin';
import type { AuthoringVersion, Lesson, LessonDocument, Messages, Media } from './types';
import '../../css/admin.css';
const props = defineProps<{ locale: string; messages: Messages; lesson?: Lesson; releasedVersion?: { id: string; document: LessonDocument }; media?: Media[] }>();
const submissions = ref<Submission[]>([]), terms = ref<TaxonomyTerm[]>([]), error = ref(''), busy = ref(false), allowed = ref(false), loaded = ref(false), success = ref('');
const slug = ref(''), coverVersion = ref('');
const releasedSnapshot = ref<{ id: string; document: LessonDocument }>();
const releasedVersions = ref<AuthoringVersion[]>([]), selectedVersion = ref(''), loadingSource = ref(false);
const source = computed(() => props.releasedVersion ?? releasedSnapshot.value);
const metadata = ref<PublicationMetadata | null>(null);
const kinds: TermKind[] = ['age', 'audience', 'topic', 'format'];
let sourceRequest = 0;
async function loadSnapshot() {
    if (!props.lesson || !selectedVersion.value || props.releasedVersion) return;
    const request = ++sourceRequest; loadingSource.value = true; releasedSnapshot.value = undefined;
    try {
        const result = await api<{ version: { id: string; status: string; document: LessonDocument } }>(`/api/studio/lessons/${props.lesson.id}/versions/${selectedVersion.value}`);
        if (request === sourceRequest && result.version.status === 'released') releasedSnapshot.value = { id: result.version.id, document: result.version.document };
    } catch (problem) { if (request === sourceRequest) error.value = adminError(problem, props.messages); }
    finally { if (request === sourceRequest) loadingSource.value = false; }
}
watch(() => `${props.lesson?.id ?? ''}:${props.lesson?.versionId ?? ''}:${props.releasedVersion?.id ?? ''}`, async () => {
    const request = ++sourceRequest;
    releasedSnapshot.value = undefined; releasedVersions.value = []; selectedVersion.value = '';
    if (!props.lesson || props.releasedVersion) return;
    loadingSource.value = true;
    try {
        const result = await api<{ versions: AuthoringVersion[] }>(`/api/studio/lessons/${props.lesson.id}/versions`);
        if (request !== sourceRequest) return;
        releasedVersions.value = result.versions.filter(version => version.status === 'released');
        selectedVersion.value = releasedVersions.value.find(version => version.current)?.id ?? releasedVersions.value[0]?.id ?? '';
        if (selectedVersion.value) await loadSnapshot();
    } catch (problem) { if (request === sourceRequest) error.value = adminError(problem, props.messages); }
    finally { if (request === sourceRequest) loadingSource.value = false; }
}, { immediate: true });
watch(source, value => { metadata.value = value ? publicationMetadata(value.document) : null; slug.value = ''; coverVersion.value = ''; success.value = ''; }, { immediate: true });
const builtinMedia = computed(() => (props.media ?? []).filter(item => item.url.startsWith('/media/builtin/')));
async function load() {
    error.value = ''; loaded.value = false;
    try { const list = await api<{ submissions: Submission[] }>('/api/studio/catalog/submissions'); submissions.value = list.submissions; allowed.value = true; const tax = await api<{ terms: TaxonomyTerm[] }>(`/api/catalog/taxonomy?locale=${props.locale}`); terms.value = tax.terms; }
    catch (problem) { allowed.value = false; error.value = adminError(problem, props.messages); }
    finally { loaded.value = true; }
}
async function submit() {
    if (busy.value || !props.lesson || !source.value || !metadata.value || !allowed.value) return;
    busy.value = true; error.value = ''; success.value = '';
    const snapshot = plainCopy(metadata.value);
    const cover = builtinMedia.value.find(item => item.versionId === coverVersion.value);
    if (cover) snapshot.cover = { assetId: cover.assetId, versionId: cover.versionId };
    try { const response = await api<{ submission: Submission }>('/api/studio/catalog/submissions', 'POST', submissionPayload(props.lesson.id, source.value.id, props.lesson.revision, slug.value, snapshot)); submissions.value = [response.submission, ...submissions.value.filter(item => item.id !== response.submission.id)]; success.value = props.messages.admin_submitted; }
    catch (problem) { error.value = adminError(problem, props.messages); }
    finally { busy.value = false; }
}
onMounted(load);
</script>
<template>
    <section class="admin-module"><h2>{{ messages.admin_submissions_title }}</h2><p>{{ messages.admin_submit_explanation }}</p><p v-if="!loaded" role="status">{{ messages.admin_loading }}</p><div v-if="error" role="alert" class="admin-alert"><p>{{ error }}</p><button v-if="!allowed" type="button" @click="load">{{ messages.admin_retry }}</button></div><p v-if="success" class="admin-success" role="status">{{ success }}</p>
        <label v-if="allowed && releasedVersions.length && !releasedVersion">{{ messages.admin_pinned_version }}<select v-model="selectedVersion" :disabled="busy || loadingSource" @change="loadSnapshot"><option v-for="version in releasedVersions" :key="version.id" :value="version.id">{{ version.createdAt }} · {{ version.id }}</option></select></label><p v-if="loadingSource" role="status">{{ messages.admin_loading }}</p><form v-if="allowed && lesson && source && metadata" class="admin-form" @submit.prevent="submit"><p class="admin-snapshot">{{ messages.admin_pinned_version }}: {{ source.id }}</p><label>{{ messages.admin_slug }}<input v-model="slug" required maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*" :disabled="busy" /><small>{{ messages.admin_slug_hint }}</small></label><div class="admin-translation-grid"><fieldset v-for="language in source.document.locales" :key="language"><legend>{{ language.toUpperCase() }}</legend><label>{{ messages.admin_title }}<input v-model="metadata.translations[language]!.title" required maxlength="200" :disabled="busy" /></label><label>{{ messages.admin_description }}<textarea v-model="metadata.translations[language]!.description" required maxlength="2000" rows="3" :disabled="busy" /></label></fieldset></div><div class="admin-facets"><fieldset v-for="kind in kinds" :key="kind"><legend>{{ messages[`admin_kind_${kind}`] }}</legend><label v-for="term in terms.filter(item => item.kind === kind && item.active)" :key="term.id" class="admin-checkbox"><input v-model="metadata[kind]" type="checkbox" :value="term.key" :disabled="busy" />{{ term.label }}</label></fieldset></div><label>{{ messages.admin_duration }}<input v-model.number="metadata.durationMinutes" required type="number" min="1" max="1440" :disabled="busy" /></label><label v-if="builtinMedia.length">{{ messages.admin_cover }}<select v-model="coverVersion" :disabled="busy"><option value="">{{ messages.admin_no_cover }}</option><option v-for="image in builtinMedia" :key="image.versionId" :value="image.versionId">{{ image.title || messages[image.labelKey ?? ''] || image.versionId }}</option></select></label><button type="submit" :disabled="busy">{{ busy ? messages.admin_saving : messages.admin_submit }}</button></form><p v-else-if="allowed && lesson && !loadingSource" class="admin-notice">{{ messages.admin_release_required }}</p>
        <h3>{{ messages.admin_my_submissions }}</h3><p v-if="allowed && !submissions.length">{{ messages.admin_no_submissions }}</p><div class="admin-records"><article v-for="submission in submissions" :key="submission.id"><div class="admin-record-heading"><h4>{{ submission.metadata.translations[locale]?.title || Object.values(submission.metadata.translations)[0]?.title }}</h4><span class="admin-status" :class="submission.status">{{ messages[`admin_status_${submission.status}`] }}</span></div><p>{{ submission.slug }}</p><small>{{ messages.admin_pinned_version }}: {{ submission.versionId }}</small><p v-if="submission.reason" class="admin-return-reason">{{ messages.admin_reason }}: {{ submission.reason }}</p><a v-if="submission.catalogSlug" :href="`/${locale}/catalog/${submission.catalogSlug}`">{{ messages.admin_open_public }}</a></article></div>
    </section>
</template>
