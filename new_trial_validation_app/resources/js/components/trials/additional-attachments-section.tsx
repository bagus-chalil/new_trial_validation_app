import { router } from '@inertiajs/react';
import {
    Download,
    ExternalLink,
    FileText,
    Image as ImageIcon,
    Paperclip,
    Trash2,
    UploadCloud,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import TrialAdditionalAttachmentController from '@/actions/App/Http/Controllers/TrialAdditionalAttachmentController';
import { AttachmentImagePreview } from '@/components/attachment-image-preview';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type AdditionalAttachment = {
    id: number;
    original_name: string;
    mime_type: string;
    is_pdf: boolean;
    size_bytes: number;
    description: string | null;
    uploaded_by_name: string | null;
    uploader_role: string | null;
    created_at: string | null;
    url: string;
    can_delete: boolean;
};

const ACCEPT = 'application/pdf,image/jpeg,image/png,image/webp,image/gif';
const ACCEPTED_MIME = ACCEPT.split(',');
const MAX_SIZE_BYTES = 10 * 1024 * 1024;

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function AdditionalAttachmentsSection({
    trialId,
    attachments,
    canUpload,
    limit,
}: {
    trialId: number;
    attachments: AdditionalAttachment[];
    canUpload: boolean;
    limit: number;
}) {
    const { t, formatDate } = useTranslation();
    const inputRef = useRef<HTMLInputElement>(null);
    const [selected, setSelected] = useState<File[]>([]);
    const [description, setDescription] = useState('');
    const [clientError, setClientError] = useState<string | null>(null);
    const [serverErrors, setServerErrors] = useState<string[]>([]);
    const [dragging, setDragging] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [progress, setProgress] = useState<number | null>(null);

    const remaining = Math.max(0, limit - attachments.length);
    const slotsLeft = remaining - selected.length;
    const usedPercent = Math.min(100, (attachments.length / limit) * 100);

    if (!canUpload && attachments.length === 0) {
        return null;
    }

    function addFiles(list: FileList | null) {
        if (!list) {
            return;
        }

        const rejected: string[] = [];
        const accepted: File[] = [];

        for (const file of Array.from(list)) {
            if (!ACCEPTED_MIME.includes(file.type)) {
                rejected.push(
                    t('report.additional.error_type', { name: file.name }),
                );
            } else if (file.size > MAX_SIZE_BYTES) {
                rejected.push(
                    t('report.additional.error_size', { name: file.name }),
                );
            } else {
                accepted.push(file);
            }
        }

        const room = remaining - selected.length;

        if (accepted.length > room) {
            rejected.push(
                t('report.additional.error_room', {
                    room,
                    ignored: accepted.length - room,
                }),
            );
        }

        setSelected((current) => [
            ...current,
            ...accepted.slice(0, Math.max(0, room)),
        ]);
        setClientError(rejected.length ? rejected.join(' · ') : null);
        setServerErrors([]);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }

    function submit() {
        if (selected.length === 0) {
            return;
        }

        const data = new FormData();
        selected.forEach((file) => data.append('files[]', file));

        if (description.trim()) {
            data.append('description', description.trim());
        }

        router.post(
            TrialAdditionalAttachmentController.store(trialId).url,
            data,
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onProgress: (event) => setProgress(event?.percentage ?? null),
                onSuccess: () => {
                    setSelected([]);
                    setDescription('');
                    setClientError(null);
                    setServerErrors([]);
                },
                onError: (errors) =>
                    setServerErrors(Array.from(new Set(Object.values(errors)))),
                onFinish: () => {
                    setProcessing(false);
                    setProgress(null);
                },
            },
        );
    }

    return (
        <section className="space-y-3 print:hidden">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
                        <Paperclip className="size-4" />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h3 className="text-base font-semibold">
                                {t('report.additional.title')}
                            </h3>
                            <Badge variant="outline" className="tabular-nums">
                                {attachments.length}/{limit}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {t('report.additional.description')}
                        </p>
                    </div>
                </div>
                <div className="w-full max-w-48 space-y-1 sm:w-48">
                    <div className="flex justify-between text-xs text-muted-foreground">
                        <span>{t('report.additional.quota_used')}</span>
                        <span className="tabular-nums">
                            {t('report.additional.slots_left', {
                                count: remaining,
                            })}
                        </span>
                    </div>
                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            className={cn(
                                'h-full rounded-full transition-all',
                                remaining === 0 ? 'bg-amber-500' : 'bg-brand',
                            )}
                            style={{ width: `${usedPercent}%` }}
                        />
                    </div>
                </div>
            </div>

            <div className="rounded-xl border bg-card">
                {canUpload && remaining > 0 && (
                    <div className="space-y-3 border-b p-4">
                        <button
                            type="button"
                            onClick={() => inputRef.current?.click()}
                            onDragOver={(e) => {
                                e.preventDefault();
                                setDragging(true);
                            }}
                            onDragLeave={() => setDragging(false)}
                            onDrop={(e) => {
                                e.preventDefault();
                                setDragging(false);
                                addFiles(e.dataTransfer.files);
                            }}
                            disabled={processing || slotsLeft <= 0}
                            className={cn(
                                'flex w-full flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed px-4 py-6 text-center transition-colors',
                                'hover:border-brand/60 hover:bg-brand/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60',
                                dragging
                                    ? 'border-brand bg-brand/5'
                                    : 'border-muted-foreground/25',
                            )}
                        >
                            <UploadCloud className="size-7 text-muted-foreground" />
                            <span className="text-sm font-medium">
                                {t('report.additional.drop_hint')}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {t('report.additional.file_rules', {
                                    count: slotsLeft,
                                })}
                            </span>
                        </button>
                        <input
                            ref={inputRef}
                            type="file"
                            multiple
                            accept={ACCEPT}
                            className="hidden"
                            onChange={(e) => addFiles(e.target.files)}
                        />

                        {(clientError || serverErrors.length > 0) && (
                            <Alert variant="destructive">
                                <AlertDescription>
                                    {[clientError, ...serverErrors]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </AlertDescription>
                            </Alert>
                        )}

                        {selected.length > 0 && (
                            <div className="space-y-3">
                                <ul className="flex flex-wrap gap-2">
                                    {selected.map((file, index) => (
                                        <li
                                            key={`${file.name}-${index}`}
                                            className="flex max-w-full items-center gap-2 rounded-md border bg-muted/40 py-1 pr-1 pl-2 text-xs"
                                        >
                                            {file.type === 'application/pdf' ? (
                                                <FileText className="size-3.5 shrink-0 text-red-600" />
                                            ) : (
                                                <ImageIcon className="size-3.5 shrink-0 text-sky-600" />
                                            )}
                                            <span className="max-w-48 truncate">
                                                {file.name}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {formatSize(file.size)}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setSelected((current) =>
                                                        current.filter(
                                                            (_, i) =>
                                                                i !== index,
                                                        ),
                                                    )
                                                }
                                                disabled={processing}
                                                className="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                aria-label={t(
                                                    'report.additional.remove_selected',
                                                    { name: file.name },
                                                )}
                                            >
                                                <X className="size-3.5" />
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <Input
                                        value={description}
                                        onChange={(e) =>
                                            setDescription(e.target.value)
                                        }
                                        maxLength={500}
                                        placeholder={t(
                                            'report.additional.description_placeholder',
                                        )}
                                        disabled={processing}
                                    />
                                    <Button
                                        type="button"
                                        onClick={submit}
                                        disabled={processing}
                                        className="shrink-0"
                                    >
                                        <UploadCloud className="size-4" />
                                        {processing
                                            ? progress !== null
                                                ? t(
                                                      'report.additional.uploading_progress',
                                                      {
                                                          percent:
                                                              Math.round(
                                                                  progress,
                                                              ),
                                                      },
                                                  )
                                                : t(
                                                      'report.additional.uploading',
                                                  )
                                            : t(
                                                  'report.additional.upload_count',
                                                  {
                                                      count: selected.length,
                                                  },
                                              )}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {canUpload && remaining === 0 && (
                    <div className="border-b px-4 py-3 text-sm text-amber-700 dark:text-amber-400">
                        {t('report.additional.quota_full', { limit })}
                    </div>
                )}

                {attachments.length === 0 ? (
                    <p className="p-6 text-center text-sm text-muted-foreground">
                        {t('report.additional.empty')}
                    </p>
                ) : (
                    <ul className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                        {attachments.map((file) => (
                            <li
                                key={file.id}
                                className="flex gap-3 rounded-lg border p-3 transition-shadow hover:shadow-sm"
                            >
                                <div className="size-16 shrink-0 overflow-hidden rounded-md border bg-muted/40">
                                    {file.is_pdf ? (
                                        <a
                                            href={file.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="flex size-full flex-col items-center justify-center gap-0.5 bg-red-50 text-red-600 dark:bg-red-950/30"
                                            aria-label={t(
                                                'report.additional.open_file',
                                                {
                                                    name: file.original_name,
                                                },
                                            )}
                                        >
                                            <FileText className="size-6" />
                                            <span className="text-[10px] font-semibold">
                                                PDF
                                            </span>
                                        </a>
                                    ) : (
                                        <AttachmentImagePreview
                                            src={file.url}
                                            alt={file.original_name}
                                            fileName={file.original_name}
                                            caption={file.description}
                                            className="size-16 object-cover"
                                        />
                                    )}
                                </div>
                                <div className="min-w-0 flex-1 space-y-1">
                                    <p
                                        className="truncate text-sm font-medium"
                                        title={file.original_name}
                                    >
                                        {file.original_name}
                                    </p>
                                    {file.description && (
                                        <p className="line-clamp-2 text-xs break-words text-foreground/80">
                                            {file.description}
                                        </p>
                                    )}
                                    <div className="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                                        {file.uploader_role && (
                                            <Badge
                                                variant="secondary"
                                                className="px-1.5 py-0 text-[10px]"
                                            >
                                                {file.uploader_role}
                                            </Badge>
                                        )}
                                        <span className="truncate">
                                            {file.uploaded_by_name ?? '-'}
                                        </span>
                                    </div>
                                    <p className="text-[11px] text-muted-foreground">
                                        {formatDate(file.created_at)} ·{' '}
                                        {formatSize(file.size_bytes)}
                                    </p>
                                    <div className="flex items-center gap-1 pt-0.5">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            className="size-7"
                                            title={t('report.additional.open')}
                                        >
                                            <a
                                                href={file.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <ExternalLink className="size-3.5" />
                                            </a>
                                        </Button>
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            className="size-7"
                                            title={t(
                                                'report.additional.download',
                                            )}
                                        >
                                            <a href={`${file.url}?download=1`}>
                                                <Download className="size-3.5" />
                                            </a>
                                        </Button>
                                        {file.can_delete && (
                                            <ConfirmDialog
                                                trigger={
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-7 text-destructive hover:text-destructive"
                                                        title={t(
                                                            'report.additional.delete',
                                                        )}
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                }
                                                title={t(
                                                    'report.additional.delete_title',
                                                )}
                                                description={file.original_name}
                                                confirmLabel={t(
                                                    'report.additional.delete',
                                                )}
                                                formProps={TrialAdditionalAttachmentController.destroy.form(
                                                    {
                                                        trial: trialId,
                                                        attachment: file.id,
                                                    },
                                                )}
                                            />
                                        )}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}
