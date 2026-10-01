import { ref } from 'vue';
import { accountIdentity, identityDiffers } from './account-helpers';
import type { Account } from './types';
export const accountState = ref<Account>();
export const identityBlocked = ref(false);
let baseline: string | undefined;
let channel: BroadcastChannel | undefined;
let revision = 0;
export function acceptAccount(account: Account): void {
    if (identityDiffers(baseline, account)) blockIdentity();
    if (!identityBlocked.value) { baseline = accountIdentity(account); accountState.value = account; }
}
export function blockIdentity(): void { revision++; identityBlocked.value = true; }
export function announceIdentityChange(): void { channel?.postMessage({ type: 'identity-changed' }); }
export function identityRevision(): number { return revision; }
export function workspaceRequest(path: string): boolean { return path.startsWith('/api/studio/') || path.startsWith('/api/account') || ['/api/auth/logout', '/api/auth/verification-notification'].includes(path); }
export async function guardWorkspace(path: string, method: string): Promise<void> {
    if (!workspaceRequest(path)) return;
    if (identityBlocked.value) throw new Error('identity_changed');
    if (method !== 'GET' && baseline !== undefined) {
        const response = await fetch('/api/account', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (response.status === 401) { blockIdentity(); throw new Error('identity_changed'); }
        if (!response.ok) throw new Error('identity_check');
        acceptAccount(await response.json() as Account);
        if (identityBlocked.value) throw new Error('identity_changed');
    }
}
export function startIdentityWatch(refresh: () => Promise<void>): () => void {
    try { channel = new BroadcastChannel('lesson-account'); channel.onmessage = event => { if (event.data?.type === 'identity-changed') blockIdentity(); }; } catch { /* Focus checks still guard mutations. */ }
    const focus = () => { void refresh().catch(() => { /* Existing page reports request failures. */ }); };
    window.addEventListener('focus', focus);
    return () => { channel?.close(); window.removeEventListener('focus', focus); };
}
