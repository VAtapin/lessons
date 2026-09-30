import { onBeforeUnmount } from 'vue';
import type { Messages } from './types';

export class ApiError extends Error {
    constructor(public code: string, public status: number, public data?: unknown) { super(code); }
}

export async function api<T>(path: string, method = 'GET', body?: unknown, signal?: AbortSignal): Promise<T> {
    let response: Response;
    try {
        response = await fetch(path, {
            method, credentials: 'same-origin', signal,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
            ...(body === undefined ? {} : { body: JSON.stringify(body) }),
        });
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') throw error;
        throw new ApiError('network', 0);
    }
    const data = await response.json().catch(() => null);
    if (!response.ok) throw new ApiError(data?.error?.code ?? (response.status === 422 ? 'invalid_action' : response.status === 419 ? 'expired' : 'request'), response.status, data);
    if (!data) throw new ApiError('request', response.status, data);
    return data as T;
}

export function errorMessage(error: unknown, messages: Messages): string {
    return messages[`error_${error instanceof ApiError ? error.code : 'request'}`] ?? messages.error_request ?? 'Request failed';
}

// Sequential requests prevent slow responses from reordering the rendered state.
export function poll(load: (signal: AbortSignal) => Promise<void>, fail: (error: unknown) => void): void {
    let stopped = false;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let controller: AbortController | undefined;
    const tick = async () => {
        controller = new AbortController();
        try { await load(controller.signal); }
        catch (error) { if (!stopped) fail(error); }
        if (!stopped) timer = setTimeout(tick, 2000);
    };
    void tick();
    onBeforeUnmount(() => { stopped = true; clearTimeout(timer); controller?.abort(); });
}
