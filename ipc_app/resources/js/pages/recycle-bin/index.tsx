import { Button } from '@/components/ui/button';
import { IpcShell } from '@/layouts/ipc-shell';
import { stageBadgeStyle, stageLabel } from '@/lib/ipc-stages';
import { Head, router } from '@inertiajs/react';
import { ClipboardList, MapPin, Package, RotateCcw, TestTube2, Trash2 } from 'lucide-react';
import { type ReactNode } from 'react';

interface DeletedBy { name: string }

interface DeletedBatch {
    id: number; no_batch: string; current_stage: string; deleted_at: string;
    master_product: { product_name: string; fg_code: string } | null;
    master_line: { name: string } | null;
    deleted_by_user: DeletedBy | null;
}
interface DeletedProduct {
    id: number; fg_code: string; product_name: string; deleted_at: string;
    deleted_by_user: DeletedBy | null;
}
interface DeletedLine {
    id: number; code: string; name: string; category: string; area: string; deleted_at: string;
    deleted_by_user: DeletedBy | null;
}
interface DeletedTestType {
    id: number; name: string; category: string; deleted_at: string;
    deleted_by_user: DeletedBy | null;
}

const fmtDate = (iso: string) =>
    new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

function RestoreBtn({ onRestore }: { onRestore: () => void }) {
    return (
        <Button type="button" size="sm" variant="outline" className="shrink-0 gap-1.5 text-[12.5px]" onClick={onRestore}>
            <RotateCcw className="size-3.5" strokeWidth={2.2} />
            Kembalikan
        </Button>
    );
}

function EmptyState({ icon: Icon, label }: { icon: typeof Trash2; label: string }) {
    return (
        <div className="border-border flex flex-col items-center gap-2 rounded-2xl border border-dashed py-8 text-center">
            <Icon className="text-muted-foreground/50 size-7" strokeWidth={1.5} />
            <p className="text-muted-foreground text-sm">{label}</p>
        </div>
    );
}

function Section({ title, icon: Icon, count, children }: { title: string; icon: typeof Trash2; count: number; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center gap-2">
                <Icon className="text-muted-foreground size-4" strokeWidth={2} />
                <h2 className="text-[14px] font-bold">
                    {title}<span className="text-muted-foreground ml-1.5 font-medium">({count})</span>
                </h2>
            </div>
            {children}
        </div>
    );
}

function DeletedInfo({ deletedAt, deletedBy }: { deletedAt: string; deletedBy: DeletedBy | null }) {
    return (
        <p className="text-muted-foreground/70 mt-1 text-[11.5px]">
            Dihapus {fmtDate(deletedAt)}{deletedBy && ` oleh ${deletedBy.name}`}
        </p>
    );
}

export default function RecycleBinIndex({
    batches, products, lines, testTypes,
}: {
    batches: DeletedBatch[];
    products: DeletedProduct[];
    lines: DeletedLine[];
    testTypes: DeletedTestType[];
}) {
    const restore = (routeName: string, id: number) =>
        router.patch(route(routeName, id), {}, { preserveScroll: true });

    const total = batches.length + products.length + lines.length + testTypes.length;

    return (
        <IpcShell title="Tempat Sampah" subtitle={`${total} item terhapus`} backHref="/dashboard">
            <Head title="Tempat Sampah" />
            <div className="flex flex-1 flex-col gap-6 overflow-y-auto p-5 md:p-6">

                {total === 0 && (
                    <div className="flex flex-col items-center gap-3 py-16 text-center">
                        <Trash2 className="text-muted-foreground/40 size-12" strokeWidth={1.5} />
                        <p className="font-bold">Tempat sampah kosong</p>
                        <p className="text-muted-foreground text-sm">Tidak ada data yang dihapus.</p>
                    </div>
                )}

                <Section title="Batch" icon={ClipboardList} count={batches.length}>
                    {batches.length === 0 ? <EmptyState icon={ClipboardList} label="Tidak ada batch yang dihapus." /> : batches.map((b) => (
                        <div key={b.id} className="border-border-soft bg-card flex items-start justify-between gap-3 rounded-[20px] border p-4">
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="text-[15px] font-bold tracking-tight">{b.no_batch}</p>
                                    <span className="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold" style={stageBadgeStyle(b.current_stage)}>
                                        {stageLabel(b.current_stage)}
                                    </span>
                                </div>
                                {b.master_product && (
                                    <p className="text-muted-foreground mt-0.5 text-[12.5px]">
                                        {b.master_product.product_name} · {b.master_product.fg_code}
                                    </p>
                                )}
                                <DeletedInfo deletedAt={b.deleted_at} deletedBy={b.deleted_by_user} />
                            </div>
                            <RestoreBtn onRestore={() => restore('recycle-bin.batches.restore', b.id)} />
                        </div>
                    ))}
                </Section>

                <Section title="Produk" icon={Package} count={products.length}>
                    {products.length === 0 ? <EmptyState icon={Package} label="Tidak ada produk yang dihapus." /> : products.map((p) => (
                        <div key={p.id} className="border-border-soft bg-card flex items-center justify-between gap-3 rounded-[20px] border p-4">
                            <div className="min-w-0 flex-1">
                                <p className="text-[15px] font-bold">{p.product_name}</p>
                                <p className="text-muted-foreground text-[12.5px]">{p.fg_code}</p>
                                <DeletedInfo deletedAt={p.deleted_at} deletedBy={p.deleted_by_user} />
                            </div>
                            <RestoreBtn onRestore={() => restore('recycle-bin.products.restore', p.id)} />
                        </div>
                    ))}
                </Section>

                <Section title="Line" icon={MapPin} count={lines.length}>
                    {lines.length === 0 ? <EmptyState icon={MapPin} label="Tidak ada line yang dihapus." /> : lines.map((l) => (
                        <div key={l.id} className="border-border-soft bg-card flex items-center justify-between gap-3 rounded-[20px] border p-4">
                            <div className="min-w-0 flex-1">
                                <p className="text-[15px] font-bold">{l.code} — {l.name}</p>
                                <p className="text-muted-foreground text-[12.5px]">{l.category} · {l.area}</p>
                                <DeletedInfo deletedAt={l.deleted_at} deletedBy={l.deleted_by_user} />
                            </div>
                            <RestoreBtn onRestore={() => restore('recycle-bin.lines.restore', l.id)} />
                        </div>
                    ))}
                </Section>

                <Section title="Test Type" icon={TestTube2} count={testTypes.length}>
                    {testTypes.length === 0 ? <EmptyState icon={TestTube2} label="Tidak ada test type yang dihapus." /> : testTypes.map((t) => (
                        <div key={t.id} className="border-border-soft bg-card flex items-center justify-between gap-3 rounded-[20px] border p-4">
                            <div className="min-w-0 flex-1">
                                <p className="text-[15px] font-bold">{t.name}</p>
                                <p className="text-muted-foreground text-[12.5px]">{t.category}</p>
                                <DeletedInfo deletedAt={t.deleted_at} deletedBy={t.deleted_by_user} />
                            </div>
                            <RestoreBtn onRestore={() => restore('recycle-bin.test-types.restore', t.id)} />
                        </div>
                    ))}
                </Section>
            </div>
        </IpcShell>
    );
}
