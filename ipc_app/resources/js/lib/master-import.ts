export type MasterImportType = 'master_products' | 'master_lines';

export type MasterImportStatus = 'queued' | 'validating' | 'validated' | 'commit_queued' | 'committing' | 'completed' | 'failed' | 'cancelled';

export interface MasterImportIssue {
    row_number: number;
    message: string;
    values: Record<string, string>;
}

export interface MasterImport {
    id: number;
    type: MasterImportType;
    status: MasterImportStatus;
    original_filename: string;
    total_rows: number;
    processed_rows: number;
    valid_rows: number;
    error_rows: number;
    warning_rows: number;
    preview: Record<string, number> | null;
    result: Record<string, number> | null;
    error_message: string | null;
    cancel_requested: boolean;
    can_commit: boolean;
    waiting_seconds: number;
    columns: Record<string, string>;
    issues: {
        errors: MasterImportIssue[];
        warnings: MasterImportIssue[];
        error_count: number;
        warning_count: number;
        limit: number;
    } | null;
    issues_url: string | null;
    created_at: string | null;
    finished_at: string | null;
}

export const RUNNING_STATUSES: MasterImportStatus[] = ['queued', 'validating', 'commit_queued', 'committing'];

export const isRunning = (status: MasterImportStatus) => RUNNING_STATUSES.includes(status);

/** Preview/result counter keys (from the PHP processors) → labels, in display order. */
export const COUNT_LABELS: Record<string, { label: string; tone: 'new' | 'update' | 'neutral' | 'warn' }> = {
    products_new: { label: 'Produk baru', tone: 'new' },
    products_updated: { label: 'Produk diperbarui', tone: 'update' },
    products_restored: { label: 'Produk dipulihkan dari Recycle Bin', tone: 'update' },
    products_unchanged: { label: 'Produk tidak berubah', tone: 'neutral' },
    bulk_codes_new: { label: 'Bulk code baru', tone: 'new' },
    bulk_codes_restored: { label: 'Bulk code dipulihkan', tone: 'update' },
    bulk_codes_existing: { label: 'Bulk code sudah ada', tone: 'neutral' },
    lines_new: { label: 'Line baru', tone: 'new' },
    lines_updated: { label: 'Line diperbarui', tone: 'update' },
    lines_unchanged: { label: 'Line tidak berubah', tone: 'neutral' },
    skipped_duplicates: { label: 'Baris duplikat dilewati', tone: 'warn' },
};

export const formatNumber = (value: number) => value.toLocaleString('id-ID');

export const formatBytes = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

// JSON endpoints (not Inertia visits), so the CSRF token is sent manually from Laravel's
// XSRF-TOKEN cookie — same approach as use-vision-analyze.ts.
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export class ImportRequestError extends Error {}

async function errorMessage(response: Response): Promise<string> {
    try {
        const data = await response.json();
        const firstFieldError = data.errors ? (Object.values(data.errors)[0] as string[] | undefined)?.[0] : undefined;
        return firstFieldError ?? data.message ?? `Gagal (HTTP ${response.status})`;
    } catch {
        return `Gagal (HTTP ${response.status})`;
    }
}

export async function importRequest<T>(url: string, method: 'GET' | 'POST' = 'GET'): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
    });
    if (!response.ok) throw new ImportRequestError(await errorMessage(response));
    return (await response.json()) as T;
}

/** Uses XMLHttpRequest rather than fetch because fetch has no upload-progress events. */
export function uploadImportFile(
    url: string,
    file: File,
    onProgress: (loaded: number, total: number) => void,
): { promise: Promise<MasterImport>; abort: () => void } {
    const xhr = new XMLHttpRequest();
    const promise = new Promise<MasterImport>((resolve, reject) => {
        const body = new FormData();
        body.append('file', file);

        xhr.open('POST', url);
        xhr.withCredentials = true;
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-XSRF-TOKEN', xsrfToken());
        xhr.upload.onprogress = (event) => event.lengthComputable && onProgress(event.loaded, event.total);
        xhr.onload = async () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(JSON.parse(xhr.responseText) as MasterImport);
                return;
            }
            if (xhr.status === 413) {
                reject(new ImportRequestError('File terlalu besar untuk server. Maksimal 10 MB.'));
                return;
            }
            reject(new ImportRequestError(await errorMessage(new Response(xhr.responseText, { status: xhr.status }))));
        };
        xhr.onerror = () => reject(new ImportRequestError('Koneksi terputus saat upload. Periksa jaringan lalu coba lagi.'));
        xhr.onabort = () => reject(new ImportRequestError('Upload dibatalkan.'));
        xhr.send(body);
    });

    return { promise, abort: () => xhr.abort() };
}
