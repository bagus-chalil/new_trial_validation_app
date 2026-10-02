import { BatchActionsMenu } from '@/components/ipc/batch-actions-menu';
import { MasterSearchBar } from '@/components/ipc/master-search-bar';
import { PaginationFooter } from '@/components/ipc/pagination-footer';
import { IpcShell } from '@/layouts/ipc-shell';
import { Head, Link } from '@inertiajs/react';
import { Archive, ClipboardList } from 'lucide-react';

interface ArchivedBatch {
    id: number;
    no_batch: string;
    current_stage: string;
    archived_at: string;
    master_product: { fg_code: string; product_name: string } | null;
    master_line: { name: string } | null;
    creator: { name: string } | null;
    archived_by_user: { name: string } | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    last_page: number;
    total: number;
}

const fmtDate = (iso: string) => new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

function ArchivedBatchCard({ batch }: { batch: ArchivedBatch }) {
    return (
        <div className="border-border-soft bg-card hover:border-border relative flex flex-col gap-2 rounded-[20px] border p-4 transition-colors">
            <div className="flex items-start justify-between gap-2.5">
                <div className="min-w-0">
                    <Link
                        href={`/batches/${batch.id}`}
                        className="text-[16px] font-bold tracking-tight after:absolute after:inset-0 after:rounded-[20px]"
                    >
                        {batch.no_batch}
                    </Link>
                    {batch.master_product && (
                        <p className="text-muted-foreground mt-0.5 truncate text-[13px] font-medium">
                            {batch.master_product.product_name} &middot; {batch.master_product.fg_code}
                        </p>
                    )}
                </div>
                <BatchActionsMenu batch={batch} />
            </div>

            <div className="text-muted-foreground/70 flex items-center gap-1.5 text-[12.5px] font-medium">
                <ClipboardList className="size-3.5" strokeWidth={2} />
                {batch.master_line?.name ?? '—'}
                {batch.creator && <> &middot; dibuat oleh {batch.creator.name}</>}
            </div>

            <div className="bg-border-soft h-px" />

            <p className="text-muted-foreground/70 flex items-center gap-1.5 text-xs font-medium">
                <Archive className="size-3.5" strokeWidth={2} />
                Diarsipkan {fmtDate(batch.archived_at)}
                {batch.archived_by_user && ` oleh ${batch.archived_by_user.name}`}
            </p>
        </div>
    );
}

export default function ArchiveIndex({ batches, filters }: { batches: Paginated<ArchivedBatch>; filters: { q?: string } }) {
    return (
        <IpcShell title="Arsip" subtitle={`${batches.total} batch diarsipkan`} backHref="/batches">
            <Head title="Arsip" />
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-5 md:p-6">
                <MasterSearchBar baseUrl="/archive" initialQ={filters.q ?? ''} placeholder="Cari no batch / produk..." />

                <div className="grid gap-3 lg:grid-cols-2">
                    {batches.data.map((batch) => (
                        <ArchivedBatchCard key={batch.id} batch={batch} />
                    ))}
                </div>

                {batches.data.length === 0 && (
                    <div className="border-border flex flex-col items-center gap-2 rounded-2xl border border-dashed py-12 text-center">
                        <Archive className="text-muted-foreground/50 size-8" strokeWidth={1.5} />
                        <p className="font-bold">{filters.q ? 'Tidak ada batch arsip yang cocok' : 'Arsip masih kosong'}</p>
                        <p className="text-muted-foreground max-w-xs text-sm">
                            Batch yang sudah Selesai bisa dipindahkan ke sini lewat menu ⋮ di daftar Batch.
                        </p>
                    </div>
                )}

                <PaginationFooter links={batches.links} lastPage={batches.last_page} />
            </div>
        </IpcShell>
    );
}
