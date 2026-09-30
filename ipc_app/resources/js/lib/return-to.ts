/**
 * `?return_to=` lets a page send the user somewhere to fix a prerequisite (e.g. add a bulk
 * code in Master Produk) and have that page's back button bring them straight back, instead
 * of to its fixed parent. Only same-app paths are honoured, so the param can't be used as an
 * open redirect.
 */
export const RETURN_TO_PARAM = 'return_to';

export function safeReturnTo(value: string | null | undefined): string | null {
    if (!value || !value.startsWith('/') || value.startsWith('//') || value.startsWith('/\\')) return null;
    return value;
}

/** Reads `return_to` from an Inertia page URL (`usePage().url`). */
export function returnToFromUrl(url: string): string | null {
    const query = url.split('?')[1] ?? '';
    return safeReturnTo(new URLSearchParams(query).get(RETURN_TO_PARAM));
}

/** Builds `href` with `return_to` pointing back at `returnTo`. */
export function withReturnTo(href: string, returnTo: string): string {
    return `${href}${href.includes('?') ? '&' : '?'}${RETURN_TO_PARAM}=${encodeURIComponent(returnTo)}`;
}
