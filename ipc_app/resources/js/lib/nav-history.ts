/**
 * In-app navigation history for the header "Kembali" button, so Back returns to the page the
 * user actually came from (Approval Queue, Batch list, Dashboard, ...) instead of a fixed parent.
 * Kept in sessionStorage per tab; pages opened directly fall back to the page's own `backHref`.
 */
const KEY = 'ipc.nav.stack';
const BACK_KEY = 'ipc.nav.back';
const MAX = 30;

const pathOf = (url: string) => url.split('?')[0];

function read(): string[] {
    try {
        const parsed = JSON.parse(sessionStorage.getItem(KEY) ?? '[]');
        return Array.isArray(parsed) ? parsed.filter((u) => typeof u === 'string') : [];
    } catch {
        return [];
    }
}

function backClicked(): boolean {
    try {
        return sessionStorage.getItem(BACK_KEY) === '1';
    } catch {
        return false;
    }
}

/** Pure: the stack as it should look once `url` is the current page. */
function advance(stack: string[], url: string, wasBack: boolean): string[] {
    const next = [...stack];
    if (wasBack) next.pop(); // drop the page we just left via the Back button

    const existing = next.lastIndexOf(url);
    if (existing >= 0) {
        next.length = existing + 1;
    } else if (next.length > 0 && pathOf(next[next.length - 1]) === pathOf(url)) {
        next[next.length - 1] = url; // same page, only filters/pagination changed
    } else {
        next.push(url);
    }

    return next.slice(-MAX);
}

/** Where Back should go from `url`, or null when there is no earlier in-app page. */
export function previousUrl(url: string): string | null {
    if (typeof window === 'undefined') return null;
    const next = advance(read(), url, backClicked());

    return next.length >= 2 ? next[next.length - 2] : null;
}

/** Persists `url` as the current page. Call once per visit. */
export function recordVisit(url: string): void {
    try {
        sessionStorage.setItem(KEY, JSON.stringify(advance(read(), url, backClicked())));
        sessionStorage.removeItem(BACK_KEY);
    } catch {
        // storage unavailable — Back falls back to the page's fixed parent
    }
}

/** Marks that the next visit comes from pressing the Back button. */
export function markBackClicked(): void {
    try {
        sessionStorage.setItem(BACK_KEY, '1');
    } catch {
        // ignore
    }
}
