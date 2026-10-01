<script lang="ts">
const operationCountKeys: Record<string, string[]> = {
    retention: ['sessionsDeleted', 'detailsPurged', 'rehearsalVersionsDeleted', 'receiptsDeleted', 'saveReceiptsDeleted', 'operationRunsDeleted'],
    backup: ['databaseBytes', 'mediaBytes', 'mediaVersions'],
};
export function operationCounters(operation: string, counts: Record<string, unknown> | null): { key: string; value: number }[] {
    const keys = Object.hasOwn(operationCountKeys, operation) ? operationCountKeys[operation]! : [];
    return keys.flatMap(key => {
        const value = counts?.[key];
        return typeof value === 'number' && Number.isSafeInteger(value) && value >= 0 ? [{ key, value }] : [];
    });
}
export function operationDate(value: string, locale: string, fallback: string): string {
    const date = new Date(value);
    return Number.isFinite(date.getTime()) ? new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'medium', timeZone: 'UTC' }).format(date) + ' UTC' : fallback;
}
export function operationScheduleTime(value: string, fallback: string): string {
    return /^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/.test(value) ? value + ' UTC' : fallback;
}
export function operationMessageKey(operation: string): string {
    return operation === 'backup' ? 'admin_operation_backup' : operation === 'retention' ? 'admin_operation_retention' : 'admin_operation_unknown';
}
</script>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from './api';
import { adminError } from './admin';
import type { Messages } from './types';
interface OperationRun {
    id: string; operation: string; status: string; dryRun: boolean;
    counts: Record<string, unknown> | null; errorCode: string | null;
    startedAt: string; finishedAt: string | null;
}
interface OperationsState {
    backup: { enabled: boolean; time: string };
    retention: { enabled: boolean; restoreVerified: boolean; dryRun: boolean; batch: number; time: string };
    runs: OperationRun[];
    failedJobs: { id: number; failedAt: string; queue: string }[];
}
const props = defineProps<{ locale: string; messages: Messages }>();
const state = ref<OperationsState | null>(null), loading = ref(false), error = ref('');
const date = (value: string) => operationDate(value, props.locale, props.messages.admin_unknown_time);
const scheduleTime = (value: string) => operationScheduleTime(value, props.messages.admin_unknown_time);
const number = (value: number) => new Intl.NumberFormat(props.locale).format(value);
const statusLabel = (status: string) => props.messages[['running', 'succeeded', 'failed'].includes(status) ? `admin_run_${status}` : 'admin_run_unknown'];
const errorLabel = (code: string) => props.messages[['retention_failed', 'backup_failed', 'operation_record_failed'].includes(code) ? `admin_error_${code}` : 'admin_operation_failed'];
const queueLabel = (queue: string) => props.messages[queue === 'backups' ? 'admin_operation_backup' : queue === 'retention' ? 'admin_operation_retention' : 'admin_operation_unknown'];
async function load() {
    if (loading.value) return;
    loading.value = true; error.value = '';
    try { state.value = await api<OperationsState>('/api/admin/operations'); }
    catch (problem) { state.value = null; error.value = adminError(problem, props.messages); }
    finally { loading.value = false; }
}
onMounted(load);
</script>

<template>
    <section class="admin-module operations-panel">
        <div class="admin-record-heading"><h2>{{ messages.admin_operations_title }}</h2><button type="button" :disabled="loading" @click="load">{{ messages.admin_refresh }}</button></div>
        <p>{{ messages.admin_operations_intro }}</p>
        <p v-if="loading" role="status">{{ messages.admin_loading }}</p>
        <p v-if="error" class="admin-alert" role="alert">{{ error }}</p>
        <template v-if="state">
            <h3>{{ messages.admin_operation_backup }}</h3>
            <dl class="operations-flags">
                <div><dt>{{ messages.admin_backup_enabled }}</dt><dd>{{ state.backup.enabled ? messages.admin_yes : messages.admin_no }}</dd></div>
                <div><dt>{{ messages.admin_backup_time }}</dt><dd>{{ scheduleTime(state.backup.time) }}</dd></div>
            </dl>
            <h3>{{ messages.admin_operation_retention }}</h3>
            <dl class="operations-flags">
                <div><dt>{{ messages.admin_retention_enabled }}</dt><dd>{{ state.retention.enabled ? messages.admin_yes : messages.admin_no }}</dd></div>
                <div><dt>{{ messages.admin_restore_verified }}</dt><dd>{{ state.retention.restoreVerified ? messages.admin_yes : messages.admin_no }}</dd></div>
                <div><dt>{{ messages.admin_retention_mode }}</dt><dd>{{ state.retention.dryRun ? messages.admin_dry_run : messages.admin_write_run }}</dd></div>
                <div><dt>{{ messages.admin_retention_batch }}</dt><dd>{{ number(state.retention.batch) }}</dd></div>
                <div><dt>{{ messages.admin_retention_time }}</dt><dd>{{ scheduleTime(state.retention.time) }}</dd></div>
            </dl>
            <p>{{ messages.admin_schedule_conditions }}</p>
            <h3>{{ messages.admin_operation_runs }}</h3>
            <p v-if="!state.runs.length">{{ messages.admin_no_operation_runs }}</p>
            <div class="admin-records">
                <article v-for="run in state.runs" :key="run.id">
                    <div class="admin-record-heading"><h4>{{ messages[operationMessageKey(run.operation)] }}</h4><span>{{ statusLabel(run.status) }}</span></div>
                    <p v-if="run.operation === 'retention'">{{ run.dryRun ? messages.admin_dry_run : messages.admin_write_run }}</p>
                    <p>{{ messages.admin_started }}: {{ date(run.startedAt) }}</p>
                    <p v-if="run.finishedAt">{{ messages.admin_finished }}: {{ date(run.finishedAt) }}</p>
                    <p v-if="run.errorCode" class="admin-return-reason">{{ errorLabel(run.errorCode) }}</p>
                    <dl v-if="operationCounters(run.operation, run.counts).length" class="operation-counts">
                        <div v-for="counter in operationCounters(run.operation, run.counts)" :key="counter.key"><dt>{{ messages[`admin_count_${counter.key}`] }}</dt><dd>{{ number(counter.value) }}</dd></div>
                    </dl>
                    <p v-else>{{ messages.admin_no_counts }}</p>
                </article>
            </div>
            <h3>{{ messages.admin_failed_jobs }}</h3>
            <p v-if="!state.failedJobs.length">{{ messages.admin_no_failed_jobs }}</p>
            <ul v-else class="operations-failed-list">
                <li v-for="job in state.failedJobs" :key="job.id"><strong>{{ queueLabel(job.queue) }} #{{ job.id }}</strong> · {{ date(job.failedAt) }}</li>
            </ul>
        </template>
    </section>
</template>
