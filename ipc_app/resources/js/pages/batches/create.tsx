import InputError from '@/components/input-error';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { TwoPane } from '@/components/ipc/two-pane';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { IpcShell } from '@/layouts/ipc-shell';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface MasterProduct {
    id: number;
    fg_code: string;
    product_name: string;
    bulk_code: string;
}

interface MasterLine {
    id: number;
    category: string;
    area: string;
    code: string;
    name: string;
}

export default function BatchesCreate({ products, lines }: { products: MasterProduct[]; lines: MasterLine[] }) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];

    const { data, setData, post, processing, errors } = useForm({
        master_product_id: '',
        no_batch: '',
        master_line_id: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/batches');
    };

    return (
        <IpcShell title="Batch Baru" backHref="/batches">
            <Head title="Batch Baru" />
            <TwoPane list={<BatchNavList batches={recentBatches} />}>
                <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                    <Card className="max-w-xl">
                        <CardHeader>
                            <CardTitle>Batch Baru</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="master_product_id">Produk</Label>
                                    <Select value={data.master_product_id} onValueChange={(value) => setData('master_product_id', value)}>
                                        <SelectTrigger id="master_product_id" className="min-h-11">
                                            <SelectValue placeholder="Pilih produk" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {products.map((product) => (
                                                <SelectItem key={product.id} value={String(product.id)}>
                                                    {product.fg_code} — {product.product_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.master_product_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="no_batch">No Batch</Label>
                                    <Input
                                        id="no_batch"
                                        className="min-h-11"
                                        value={data.no_batch}
                                        onChange={(e) => setData('no_batch', e.target.value)}
                                    />
                                    <InputError message={errors.no_batch} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="master_line_id">Line</Label>
                                    <Select value={data.master_line_id} onValueChange={(value) => setData('master_line_id', value)}>
                                        <SelectTrigger id="master_line_id" className="min-h-11">
                                            <SelectValue placeholder="Pilih line" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {lines.map((line) => (
                                                <SelectItem key={line.id} value={String(line.id)}>
                                                    {line.code} — {line.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.master_line_id} />
                                </div>

                                <Button type="submit" disabled={processing} size="lg" className="w-full sm:w-auto">
                                    Buat Batch & Lanjut ke Startup Check
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </TwoPane>
        </IpcShell>
    );
}
