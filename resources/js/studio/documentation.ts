export function youtubeVideoId(value: string): string | undefined {
    if (/^[A-Za-z0-9_-]{11}$/.test(value)) return value;
    try {
        const url = new URL(value);
        if (url.protocol !== 'https:' || url.username || url.password || url.port) return undefined;
        const id = ['youtube.com', 'www.youtube.com'].includes(url.hostname) && url.pathname === '/watch'
            ? url.searchParams.get('v') : url.hostname === 'youtu.be' ? url.pathname.slice(1) : undefined;
        return id && /^[A-Za-z0-9_-]{11}$/.test(id) ? id : undefined;
    } catch { return undefined; }
}
