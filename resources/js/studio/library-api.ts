import { ApiError, errorMessage } from './api';
import type { Messages } from './types';
export function libraryError(problem: unknown, messages: Messages): string {
    if (problem instanceof ApiError && problem.status === 422 && (problem.data as { errors?: unknown } | undefined)?.errors) return messages.error_invalid_metadata ?? errorMessage(problem, messages);
    return errorMessage(problem, messages);
}
export async function multipart<T>(path: string, fields: Record<string, string>, file: File): Promise<T> {
    const body = new FormData();
    for (const [name, value] of Object.entries(fields)) body.append(name, value);
    body.append('file', file);
    let response: Response;
    try { response = await fetch(path, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' }, body }); }
    catch { throw new ApiError('network', 0); }
    const data = await response.json().catch(() => null);
    if (!response.ok || !data) throw new ApiError(data?.error?.code ?? (response.status === 413 ? 'file_too_large' : response.status === 419 ? 'expired' : 'request'), response.status, data);
    return data as T;
}
