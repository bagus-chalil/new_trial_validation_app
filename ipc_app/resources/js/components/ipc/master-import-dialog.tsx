import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

export function MasterImportDialog({
    templateHref,
    importAction,
    title,
    description,
}: {
    templateHref: string;
    importAction: string;
    title: string;
    description: string;
}) {
    const [open, setOpen] = useState(false);
    const { setData, post, processing, errors, reset, clearErrors } = useForm<{ file: File | null }>({ file: null });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(importAction, {
            forceFormData: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <>
            <div className="flex flex-wrap items-center gap-2">
                <Button asChild type="button" variant="outline" size="sm" className="h-9 gap-1.5 text-[12.5px]">
                    <a href={templateHref}>
                        <Download className="size-3.5" strokeWidth={2.2} />
                        Unduh Template
                    </a>
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-9 gap-1.5 text-[12.5px]"
                    onClick={() => {
                        clearErrors();
                        reset();
                        setOpen(true);
                    }}
                >
                    <Upload className="size-3.5" strokeWidth={2.2} />
                    Import Excel
                </Button>
            </div>

            <Dialog
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);
                    if (!next) {
                        clearErrors();
                        reset();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                    </DialogHeader>
                    <p className="text-muted-foreground text-[13px]">{description}</p>
                    <form onSubmit={submit} className="space-y-4">
                        <input
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                            className="border-border-soft bg-background file:bg-accent block w-full rounded-xl border p-2.5 text-[13px] file:mr-3 file:rounded-lg file:border-0 file:px-3 file:py-1.5 file:text-[12.5px] file:font-semibold"
                        />
                        <InputError message={errors.file} />
                        <DialogFooter>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Mengupload...' : 'Import'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
