/** Resolve navigation without stealing keys from editing fields or dialogs. */
export function keyboardStageIndex(key: string, current: number, count: number, blocked: boolean): number | undefined {
    if (blocked || count < 1) return;
    const next = key === 'ArrowRight' || key === 'ArrowDown' ? current + 1
        : key === 'ArrowLeft' || key === 'ArrowUp' ? current - 1
        : key === 'Home' ? 0 : key === 'End' ? count - 1 : undefined;
    return next !== undefined && next >= 0 && next < count && next !== current ? next : undefined;
}
