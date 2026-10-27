import InputError from '@/components/input-error';
import { StickySaveBar } from '@/components/ipc/sticky-save-bar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { IpcShell } from '@/layouts/ipc-shell';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface MicroCheckData {
    save_count: number;
    updated_at: string;
    user?: { name: string } | null;
    revisions?: { id: number; revision_no: number; created_at: string; user?: { name: string } | null; [key: string]: unknown }[];
    [key: string]: unknown;
}

interface Batch {
    id: number;
    no_batch: string;
    master_product: { product_name: string; fg_code: string };
}

const formatDateTime = (value: string) =>
    new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

export default function MicroCheckEdit({
    batch,
    microCheck,
    parameters,
    defaultStd,
    isReadOnly,
    finishedCompleted,
}: {
    batch: Batch;
    microCheck: MicroCheckData | null;
    parameters: Record<string, string>;
    defaultStd: Record<string, string>;
    isReadOnly: boolean;
    finishedCompleted: boolean;
}) {
    const keys = Object.keys(parameters);

    // Std is pre-filled with the usual paper-form entry on a first save; later saves show what was stored.
    const initial: Record<string, string> = {};
    keys.forEach((key) => {
        initial[`${key}_result`] = (microCheck?.[`${key}_result`] as string | undefined) ?? '';
        initial[`${key}_std`] = (microCheck?.[`${key}_std`] as string | undefined) ?? defaultStd[key] ?? '';
    });

    const { data, setData, put, processing, errors } = useForm<Record<string, string>>(initial);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/batches/${batch.id}/micro-check`, { preserveScroll: true });
    };

    const revisions = microCheck?.revisions ?? [];

    return (
        <IpcShell
            title="Micro Check"
            subtitle={`${batch.no_batch} · ${batch.master_product.product_name}`}
            backHref="/micro-checks"
            headerActions={
                isReadOnly ? (
                    <span className="rounded-full bg-green-100 px-3.5 py-1.5 text-[12.5px] font-bold whitespace-nowrap text-green-800">
                        {finishedCompleted ? 'Finished Good selesai — terkunci' : 'Read only'}
                    </span>
                ) : undefined
            }
        >
            <Head title={`Micro Check — ${batch.no_batch}`} />
            <form onSubmit={submit} className="flex flex-1 flex-col overflow-y-auto">
                <div className="flex flex-1 flex-col gap-3.5 px-5 pt-1 pb-4 md:px-8">
                    <div className="border-border-soft bg-card flex flex-col gap-4 rounded-[20px] border p-[18px]">
                        <p className="text-muted-foreground text-[12.5px] font-medium">
                            Isi sesuai form FR.QAC.193.00, misalnya <b>&lt;10</b>, <b>&lt;100</b>, atau <b>(-)</b>. Semua kolom wajib diisi sebelum
                            Finished Good bisa diselesaikan.
                        </p>
                        {keys.map((key) => (
                            <div key={key} className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor={`${key}_result`} className="text-foreground text-[13px] font-semibold">
                                        {parameters[key]} — Result (cfu/gr)
                                    </Label>
                                    <Input
                                        id={`${key}_result`}
                                        value={data[`${key}_result`]}
                                        onChange={(e) => setData(`${key}_result`, e.target.value)}
                                        disabled={isReadOnly}
                                        maxLength={50}
                                    />
                                    <InputError message={errors[`${key}_result`]} />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor={`${key}_std`} className="text-foreground text-[13px] font-semibold">
                                        {parameters[key]} — Std (cfu/gr)
                                    </Label>
                                    <Input
                                        id={`${key}_std`}
                                        value={data[`${key}_std`]}
                                        onChange={(e) => setData(`${key}_std`, e.target.value)}
                                        disabled={isReadOnly}
                                        maxLength={50}
                                    />
                                    <InputError message={errors[`${key}_std`]} />
                                </div>
                            </div>
                        ))}
                        <div className="text-muted-foreground text-[12.5px] font-medium">
                            Microbiologist: {microCheck?.user?.name ?? 'otomatis dari akun yang menyimpan'}
                        </div>
                    </div>

                    {revisions.length > 0 && (
                        <div className="border-border-soft bg-card flex flex-col gap-2 rounded-[20px] border p-[18px]">
                            <p className="text-[13px] font-bold">Riwayat Simpan</p>
                            {revisions.map((rev) => (
                                <div key={rev.id} className="border-border-soft rounded-xl border px-3 py-2 text-[12.5px]">
                                    <p className="font-bold">
                                        #{rev.revision_no} · {formatDateTime(rev.created_at)} · {rev.user?.name ?? '—'}
                                    </p>
                                    <p className="text-muted-foreground mt-0.5">
                                        {keys
                                            .map(
                                                (key) => `${parameters[key]}: ${rev[`${key}_result`] as string} (Std ${rev[`${key}_std`] as string})`,
                                            )
                                            .join(' · ')}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {!isReadOnly && (
                    <StickySaveBar
                        label="Simpan Micro Check"
                        processing={processing}
                        note="Bisa dikoreksi selama Finished Good belum diselesaikan — setiap simpan tercatat di riwayat."
                    />
                )}
            </form>
        </IpcShell>
    );
}
