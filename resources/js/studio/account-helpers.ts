import type { Account, HistorySummary, User } from './types';
export function accountIdentity(account: Account): string { return account.user ? `user:${account.user.id}` : 'guest'; }
export function identityDiffers(previous: string | undefined, account: Account): boolean { return previous !== undefined && previous !== accountIdentity(account); }
export function passwordValid(password: string): boolean { return Array.from(password).length >= 12 && new TextEncoder().encode(password).length <= 72 && !password.includes('\0'); }
export function historyContinuePath(item: Pick<HistorySummary, 'id' | 'status'>, locale: string): string | undefined { return item.status === 'finished' ? undefined : `/${locale}/teach/${encodeURIComponent(item.id)}`; }
export function mayClaim(claim: { available: boolean; status: string }, verified: boolean, afterBytes: number, limitBytes: number): boolean { return verified && claim.available && claim.status === 'pending' && afterBytes <= limitBytes; }
export function registrationRecoveryPath(kind: string, code: string, user: User | null): string | undefined { return kind === 'register' && code === 'mail_unavailable' && user ? `/${user.uiLocale}/verify-email?notice=mail-unavailable` : undefined; }
