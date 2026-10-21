import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { IpcShell } from '@/layouts/ipc-shell';
import { Head, useForm } from '@inertiajs/react';
import { FileText, Pencil } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface IpcReportDocument {
    id: number;
    report_key: string;
    doc_number: string;
    revision: string;
    title: string;
    effective_date: string | null;
}

const reportKeyLabels: Record<string, string> = {
    filling: 'Form Filling',
    packing: 'Form Packing',
    filling_packing_fg: 'Form Filling & Packing + FG',
};

export default function IpcReportDocumentsIndex({ reportDocuments }: { reportDocuments: IpcReportDocument[] }) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<IpcReportDocument | null>(null);
    const { data, setData, put, processing, errors, clearErrors } = useForm({
        doc_number: '',
        revision: '',
        title: '',
        effective_date: '' as string,
    });

    const openEdit = (doc: IpcReportDocument) => {
        clearErrors();
        setEditing(doc);
        setData({
            doc_number: doc.doc_number,
            revision: doc.revision,
            title: doc.title,
            effective_date: doc.effective_date ?? '',
        });
        setOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!editing) return;
        put(route('report-documents.update', editing.id), { onSuccess: () => setOpen(false) });
    };

    return (
        <IpcShell title="Dokumen Laporan" subtitle={`${reportDocuments.length} dokumen`} backHref="/dashboard">
            <Head title="Dokumen Laporan" />
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-5 md:p-6">
                <p className="text-muted-foreground text-sm">
                    Nomor dokumen, revisi, dan judul untuk setiap form laporan cetak/PDF IPC. Field ini dipakai langsung di PDF yang dicetak —
                    perbarui di sini saat formulir resmi direvisi, tanpa perlu perubahan kode.
                </p>

                <div className="flex flex-col gap-3">
                    {reportDocuments.map((doc) => (
                        <div key={doc.id} className="border-border-soft bg-card flex items-start justify-between gap-3 rounded-[20px] border p-4">
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="text-[15px] font-bold tracking-tight">{reportKeyLabels[doc.report_key] ?? doc.report_key}</p>
                                </div>
                                <div className="text-muted-foreground/70 mt-1.5 flex items-center gap-1.5 text-[12.5px] font-medium">
                                    <FileText className="size-3.5" strokeWidth={2} />
                                    {doc.doc_number} · Rev. {doc.revision}
                                </div>
                                <p className="mt-1 text-[13px] font-medium">{doc.title}</p>
                                {doc.effective_date && (
                                    <p className="text-muted-foreground/70 mt-1 text-[12px]">Berlaku sejak {doc.effective_date}</p>
                                )}
                            </div>
                            <div className="flex shrink-0 items-center gap-1.5">
                                <Button type="button" variant="outline" size="icon" className="size-10" onClick={() => openEdit(doc)}>
                                    <Pencil className="size-4" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit {editing ? (reportKeyLabels[editing.report_key] ?? editing.report_key) : ''}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="doc_number">Nomor Dokumen</Label>
                            <Input id="doc_number" value={data.doc_number} onChange={(e) => setData('doc_number', e.target.value)} />
                            <InputError message={errors.doc_number} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="revision">Revisi</Label>
                            <Input id="revision" value={data.revision} onChange={(e) => setData('revision', e.target.value)} />
                            <InputError message={errors.revision} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="title">Judul</Label>
                            <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                            <InputError message={errors.title} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="effective_date">Berlaku Sejak (opsional)</Label>
                            <Input
                                id="effective_date"
                                type="date"
                                value={data.effective_date}
                                onChange={(e) => setData('effective_date', e.target.value)}
                            />
                            <InputError message={errors.effective_date} />
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={processing}>
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </IpcShell>
    );
}
