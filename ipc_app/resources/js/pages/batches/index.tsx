import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Batches', href: '/batches' }];

interface MasterProduct {
    id: number;
    fg_code: string;
    product_name: string;
    bulk_code: string;
}

interface MasterLine {
    id: number;
    code: string;
    name: string;
}

interface StageCheck {
    id: number;
    completed_at: string | null;
}

interface Batch {
    id: number;
    no_batch: string;
    current_stage: string;
    created_at: string;
    master_product: MasterProduct;
    master_line: MasterLine;
    creator: { name: string };
    startup_check: StageCheck | null;
    filling_check: StageCheck | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

export default function BatchesIndex({
    batches,
    filters,
    stages,
}: {
    batches: Paginated<Batch>;
    filters: { q?: string; stage?: string };
    stages: string[];
}) {
    const { props } = usePage<{ flash?: { success?: string } }>();
    const [q, setQ] = useState(filters.q ?? '');

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        router.get('/batches', { q, stage: filters.stage }, { preserveState: true, replace: true });
    };

    const setStage = (stage?: string) => {
        router.get('/batches', { q, stage }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Batches" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                {props.flash?.success && (
                    <div className="rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                        {props.flash.success}
                    </div>
                )}

                <div className="flex items-center justify-between gap-4">
                    <form onSubmit={submitSearch} className="flex gap-2">
                        <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari no batch / produk..." className="w-64" />
                        <Button type="submit" variant="secondary">
                            Cari
                        </Button>
                    </form>
                    <Button asChild>
                        <Link href="/batches/create">Batch Baru</Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button size="sm" variant={!filters.stage ? 'default' : 'outline'} onClick={() => setStage(undefined)}>
                        Semua
                    </Button>
                    {stages.map((stage) => (
                        <Button key={stage} size="sm" variant={filters.stage === stage ? 'default' : 'outline'} onClick={() => setStage(stage)}>
                            {stage}
                        </Button>
                    ))}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-2">No Batch</th>
                                <th className="px-4 py-2">Produk</th>
                                <th className="px-4 py-2">Line</th>
                                <th className="px-4 py-2">Stage</th>
                                <th className="px-4 py-2">Dibuat oleh</th>
                                <th className="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {batches.data.map((batch) => (
                                <tr key={batch.id} className="border-t">
                                    <td className="px-4 py-2 font-medium">{batch.no_batch}</td>
                                    <td className="px-4 py-2">{batch.master_product.product_name}</td>
                                    <td className="px-4 py-2">{batch.master_line.name}</td>
                                    <td className="px-4 py-2">
                                        <span className="bg-muted rounded-full px-2 py-0.5 text-xs capitalize">{batch.current_stage}</span>
                                    </td>
                                    <td className="px-4 py-2">{batch.creator.name}</td>
                                    <td className="px-4 py-2 text-right">
                                        {batch.current_stage === 'startup' && (
                                            <Link
                                                href={`/batches/${batch.id}/startup-check`}
                                                className="text-primary underline-offset-4 hover:underline"
                                            >
                                                {batch.startup_check?.completed_at ? 'Lihat Startup Check' : 'Isi Startup Check'}
                                            </Link>
                                        )}
                                        {batch.current_stage === 'filling' && (
                                            <Link
                                                href={`/batches/${batch.id}/filling-check`}
                                                className="text-primary underline-offset-4 hover:underline"
                                            >
                                                {batch.filling_check?.completed_at ? 'Lihat Filling Check' : 'Isi Filling Check'}
                                            </Link>
                                        )}
                                        {batch.current_stage !== 'startup' && batch.current_stage !== 'filling' && (
                                            <span className="text-muted-foreground">Tahap berikutnya belum tersedia</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {batches.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-6 text-center">
                                        Belum ada batch.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {batches.last_page > 1 && (
                    <div className="flex flex-wrap gap-1">
                        {batches.links.map((link, i) => (
                            <Button
                                key={i}
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                                disabled={!link.url}
                                onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
