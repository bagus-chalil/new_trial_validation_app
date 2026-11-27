import { Form, Head, Link } from '@inertiajs/react';
import {
    Check,
    CheckCircle2,
    Download,
    FileText,
    FolderPlus,
    Pencil,
    Trash2,
    XCircle,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import ImprovementController from '@/actions/App/Http/Controllers/ImprovementController';
import ImprovementFileController from '@/actions/App/Http/Controllers/ImprovementFileController';
import ImprovementReportController from '@/actions/App/Http/Controllers/ImprovementReportController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { ImprovementDialog } from '@/components/improvements/improvement-dialog';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import {
    formatBytes,
    improvementDecisionLabel,
    improvementStatusClassName,
    improvementStatusLabel,
} from '@/lib/project';
import { show as projectShow } from '@/routes/projects';
import { index as improvementsIndex } from '@/routes/projects/improvements';

type FeedbackEntry = {
    at: string;
    by: string;
    status: string;
    decision: string;
    text: string;
};

type Improvement = {
    id: number;
    title: string;
    department: string;
    type: string;
    status: string;
    decision: string;
    submitted_by_name: string;
    submitted_at: string | null;
    issue: string | null;
    background: string | null;
    purpose: string | null;
    goals: string | null;
    saving_mp: string | null;
    quality_metric: string | null;
    links: string[];
    pi_owner: string;
    pi_feedback: string | null;
    pi_suggestion: string | null;
    pi_action: string | null;
    pi_reason: string | null;
    feedback_log: FeedbackEntry[];
    after_condition: string | null;
    after_result: string | null;
    after_date: string | null;
    after_pic: string;
    after_links: { title: string; url: string }[];
};

type FileItem = {
    id: number;
    file_name: string;
    size_bytes: number;
    description: string | null;
    uploaded_at: string | null;
    is_missing: boolean;
    phase: string;
    url: string;
};

type LogItem = {
    id: number;
    user_name: string;
    action: string;
    created_at: string | null;
};

type PageProps = {
    improvement: Improvement;
    project: { id: number; name: string; code: string } | null;
    files: FileItem[];
    logs: LogItem[];
    options: { statuses: string[]; decisions: string[] };
    can: {
        update: boolean;
        review: boolean;
        delete: boolean;
        attach: boolean;
        convert: boolean;
        close: boolean;
    };
    uploadLimitMb: number;
};

function ImprovementStepper({
    improvement,
    project,
}: {
    improvement: Improvement;
    project: PageProps['project'];
}) {
    const { t } = useTranslation();
    const finalReview = [
        'Disetujui',
        'Selesai',
        'Ditolak',
        'Tidak Perlu Improvement',
    ].includes(improvement.status);
    const stepIdx = project
        ? 4
        : finalReview
          ? 3
          : ['Review PI', 'Menunggu Data'].includes(improvement.status)
            ? 2
            : 1;
    const steps = [
        {
            n: 1,
            title: t('improvements.stepper.submitted'),
            sub: `${improvement.submitted_by_name || '-'} · ${improvement.department || '-'}`,
        },
        {
            n: 2,
            title: t('improvements.stepper.review'),
            sub: improvement.pi_owner
                ? t('improvements.stepper.review_pic', {
                      name: improvement.pi_owner,
                  })
                : t('improvements.stepper.review_sub'),
        },
        {
            n: 3,
            title: t('improvements.stepper.decision'),
            sub:
                improvement.decision === 'To Project'
                    ? t('improvements.stepper.decision_project')
                    : improvement.decision === 'Suggestion Only'
                      ? t('improvements.stepper.decision_suggestion')
                      : t('improvements.stepper.decision_pending'),
        },
        {
            n: 4,
            title: t('improvements.stepper.followup'),
            sub: project
                ? project.code || project.name
                : t('improvements.stepper.followup_none'),
        },
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    {t('improvements.stepper.title')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <ol className="grid gap-4 sm:grid-cols-4">
                    {steps.map((step) => {
                        const done =
                            step.n < stepIdx ||
                            (step.n === stepIdx && (project || finalReview));
                        const active = step.n === stepIdx && !done;

                        return (
                            <li
                                key={step.n}
                                className="flex items-start gap-3"
                                aria-current={active ? 'step' : undefined}
                            >
                                <span
                                    className={`flex size-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold ${
                                        done
                                            ? 'border-emerald-600 bg-emerald-600 text-white'
                                            : active
                                              ? 'border-brand bg-brand/10 text-brand'
                                              : 'text-muted-foreground'
                                    }`}
                                >
                                    {done ? (
                                        <Check className="size-4" />
                                    ) : (
                                        step.n
                                    )}
                                </span>
                                <span className="min-w-0 text-sm">
                                    <span className="block font-medium">
                                        {step.title}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {step.sub}
                                    </span>
                                </span>
                            </li>
                        );
                    })}
                </ol>
            </CardContent>
        </Card>
    );
}

function Detail({
    label,
    value,
}: {
    readonly label: string;
    readonly value: ReactNode;
}) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    return (
        <div className="space-y-1">
            <dt className="text-xs font-medium text-muted-foreground">
                {label}
            </dt>
            <dd className="text-sm whitespace-pre-line">{value}</dd>
        </div>
    );
}

function FieldBlock({
    label,
    error,
    children,
}: {
    readonly label: string;
    readonly error?: string;
    readonly children: ReactNode;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

export default function ImprovementShow({
    improvement,
    project,
    files,
    logs,
    options,
    can,
    uploadLimitMb,
}: PageProps) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState(false);

    return (
        <>
            <Head title={improvement.title} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Heading
                            title={improvement.title}
                            description={t('improvements.submitted_meta', {
                                name: improvement.submitted_by_name || '-',
                                date: improvement.submitted_at ?? '-',
                            })}
                        />
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant="outline"
                                className={improvementStatusClassName(
                                    improvement.status,
                                )}
                            >
                                {improvementStatusLabel(t, improvement.status)}
                            </Badge>
                            <Badge variant="outline">
                                {improvementDecisionLabel(
                                    t,
                                    improvement.decision,
                                )}
                            </Badge>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={improvementsIndex().url}>
                                {t('improvements.back')}
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a
                                href={ImprovementReportController.pdf.url(
                                    improvement.id,
                                )}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <FileText />
                                {t('improvements.show.print_pdf')}
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a
                                href={ImprovementReportController.word.url(
                                    improvement.id,
                                )}
                            >
                                <Download />
                                {t('improvements.show.download_word')}
                            </a>
                        </Button>
                        {can.update && (
                            <Button
                                variant="outline"
                                onClick={() => setEditing(true)}
                            >
                                <Pencil />
                                {t('improvements.edit')}
                            </Button>
                        )}
                        {can.convert && (
                            <ConfirmDialog
                                trigger={
                                    <Button>
                                        <FolderPlus />
                                        {t('improvements.show.convert')}
                                    </Button>
                                }
                                title={t('improvements.show.convert')}
                                description={t(
                                    'improvements.show.convert_hint',
                                )}
                                confirmLabel={t('improvements.show.convert')}
                                confirmVariant="default"
                                formProps={ImprovementController.convert.form(
                                    improvement.id,
                                )}
                            >
                                {({ errors }) => (
                                    <InputError message={errors.convert} />
                                )}
                            </ConfirmDialog>
                        )}
                        {can.close && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="outline">
                                        <CheckCircle2 />
                                        {t('improvements.show.close')}
                                    </Button>
                                }
                                title={t('improvements.show.close_title')}
                                description={t('improvements.show.close_hint')}
                                confirmLabel={t('improvements.show.close')}
                                confirmVariant="default"
                                formProps={ImprovementController.close.form(
                                    improvement.id,
                                )}
                            >
                                {({ errors }) => (
                                    <InputError message={errors.close} />
                                )}
                            </ConfirmDialog>
                        )}
                        {can.close && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="outline">
                                        <XCircle />
                                        {t('improvements.show.reject')}
                                    </Button>
                                }
                                title={t('improvements.show.reject_title')}
                                description={t('improvements.show.reject_hint')}
                                confirmLabel={t('improvements.show.reject')}
                                formProps={ImprovementController.reject.form(
                                    improvement.id,
                                )}
                            >
                                {({ errors }) => (
                                    <div className="mt-3 grid gap-2">
                                        <Label htmlFor="reject-reason">
                                            {t(
                                                'improvements.show.reject_reason',
                                            )}
                                        </Label>
                                        <Textarea
                                            id="reject-reason"
                                            name="reason"
                                            rows={3}
                                            required
                                        />
                                        <InputError message={errors.reason} />
                                    </div>
                                )}
                            </ConfirmDialog>
                        )}
                        {can.delete && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="destructive">
                                        <Trash2 />
                                        {t('improvements.delete')}
                                    </Button>
                                }
                                title={t('improvements.show.delete_title')}
                                description={t(
                                    'improvements.show.delete_description',
                                    { title: improvement.title },
                                )}
                                confirmLabel={t('improvements.delete')}
                                formProps={ImprovementController.destroy.form(
                                    improvement.id,
                                )}
                            />
                        )}
                    </div>
                </div>

                <ImprovementStepper
                    improvement={improvement}
                    project={project}
                />

                {project && (
                    <Alert>
                        <AlertDescription>
                            {t('improvements.show.project_link')}:{' '}
                            <Link
                                href={projectShow(project.id).url}
                                className="font-medium underline underline-offset-2"
                            >
                                {project.code
                                    ? `${project.code} · ${project.name}`
                                    : project.name}
                            </Link>
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('improvements.show.section_detail')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <Detail
                                label={t('improvements.fields.department')}
                                value={improvement.department}
                            />
                            <Detail
                                label={t('improvements.fields.type')}
                                value={improvement.type}
                            />
                            <Detail
                                label={t('improvements.fields.issue')}
                                value={improvement.issue}
                            />
                            <Detail
                                label={t('improvements.fields.background')}
                                value={improvement.background}
                            />
                            <Detail
                                label={t('improvements.fields.purpose')}
                                value={improvement.purpose}
                            />
                            <Detail
                                label={t('improvements.fields.goals')}
                                value={improvement.goals}
                            />
                            <Detail
                                label={t('improvements.fields.saving_mp')}
                                value={improvement.saving_mp}
                            />
                            <Detail
                                label={t('improvements.fields.quality_metric')}
                                value={improvement.quality_metric}
                            />
                            {improvement.links.length > 0 && (
                                <Detail
                                    label={t('improvements.fields.links')}
                                    value={
                                        <ul className="space-y-1">
                                            {improvement.links.map((link) => (
                                                <li key={link}>
                                                    <a
                                                        href={link}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="break-all underline underline-offset-2"
                                                    >
                                                        {link}
                                                    </a>
                                                </li>
                                            ))}
                                        </ul>
                                    }
                                />
                            )}
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('improvements.show.section_review')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {can.review ? (
                            <Form
                                {...ImprovementController.review.form(
                                    improvement.id,
                                )}
                                options={{ preserveScroll: true }}
                                className="grid gap-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <FieldBlock
                                                label={t(
                                                    'improvements.fields.status',
                                                )}
                                                error={errors.status}
                                            >
                                                <Select
                                                    name="status"
                                                    defaultValue={
                                                        improvement.status
                                                    }
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {options.statuses.map(
                                                            (v) => (
                                                                <SelectItem
                                                                    key={v}
                                                                    value={v}
                                                                >
                                                                    {improvementStatusLabel(
                                                                        t,
                                                                        v,
                                                                    )}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </FieldBlock>
                                            <FieldBlock
                                                label={t(
                                                    'improvements.fields.decision',
                                                )}
                                                error={errors.decision}
                                            >
                                                <Select
                                                    name="decision"
                                                    defaultValue={
                                                        improvement.decision
                                                    }
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {options.decisions.map(
                                                            (v) => (
                                                                <SelectItem
                                                                    key={v}
                                                                    value={v}
                                                                >
                                                                    {improvementDecisionLabel(
                                                                        t,
                                                                        v,
                                                                    )}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </FieldBlock>
                                            <FieldBlock
                                                label={t(
                                                    'improvements.fields.pi_owner',
                                                )}
                                                error={errors.pi_owner}
                                            >
                                                <Input
                                                    name="pi_owner"
                                                    defaultValue={
                                                        improvement.pi_owner
                                                    }
                                                />
                                            </FieldBlock>
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            {(
                                                [
                                                    'pi_feedback',
                                                    'pi_suggestion',
                                                    'pi_action',
                                                    'pi_reason',
                                                ] as const
                                            ).map((field) => (
                                                <FieldBlock
                                                    key={field}
                                                    label={t(
                                                        `improvements.fields.${field}`,
                                                    )}
                                                    error={errors[field]}
                                                >
                                                    <Textarea
                                                        name={field}
                                                        rows={3}
                                                        defaultValue={
                                                            improvement[
                                                                field
                                                            ] ?? ''
                                                        }
                                                    />
                                                </FieldBlock>
                                            ))}
                                        </div>
                                        <div className="grid gap-4 border-t pt-4 sm:grid-cols-2">
                                            <h3 className="text-sm font-semibold sm:col-span-2">
                                                {t(
                                                    'improvements.show.section_after',
                                                )}
                                            </h3>
                                            {(
                                                [
                                                    'after_condition',
                                                    'after_result',
                                                ] as const
                                            ).map((field) => (
                                                <FieldBlock
                                                    key={field}
                                                    label={t(
                                                        `improvements.fields.${field}`,
                                                    )}
                                                    error={errors[field]}
                                                >
                                                    <Textarea
                                                        name={field}
                                                        rows={3}
                                                        defaultValue={
                                                            improvement[
                                                                field
                                                            ] ?? ''
                                                        }
                                                    />
                                                </FieldBlock>
                                            ))}
                                            <FieldBlock
                                                label={t(
                                                    'improvements.fields.after_date',
                                                )}
                                                error={errors.after_date}
                                            >
                                                <Input
                                                    type="date"
                                                    name="after_date"
                                                    defaultValue={
                                                        improvement.after_date ??
                                                        ''
                                                    }
                                                />
                                            </FieldBlock>
                                            <FieldBlock
                                                label={t(
                                                    'improvements.fields.after_pic',
                                                )}
                                                error={errors.after_pic}
                                            >
                                                <Input
                                                    name="after_pic"
                                                    defaultValue={
                                                        improvement.after_pic
                                                    }
                                                />
                                            </FieldBlock>
                                        </div>
                                        <div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {t(
                                                    'improvements.show.review_save',
                                                )}
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Detail
                                    label={t('improvements.fields.pi_owner')}
                                    value={improvement.pi_owner}
                                />
                                <Detail
                                    label={t('improvements.fields.pi_feedback')}
                                    value={improvement.pi_feedback}
                                />
                                <Detail
                                    label={t(
                                        'improvements.fields.pi_suggestion',
                                    )}
                                    value={improvement.pi_suggestion}
                                />
                                <Detail
                                    label={t('improvements.fields.pi_action')}
                                    value={improvement.pi_action}
                                />
                                <Detail
                                    label={t('improvements.fields.pi_reason')}
                                    value={improvement.pi_reason}
                                />
                                {!improvement.pi_feedback &&
                                    !improvement.pi_suggestion &&
                                    !improvement.pi_action &&
                                    !improvement.pi_reason && (
                                        <p className="text-sm text-muted-foreground">
                                            {t('improvements.show.no_review')}
                                        </p>
                                    )}
                            </dl>
                        )}

                        {improvement.feedback_log.length > 0 && (
                            <div className="space-y-2">
                                <h3 className="text-sm font-semibold">
                                    {t('improvements.show.section_feedback')}
                                </h3>
                                <ul className="space-y-2 text-sm">
                                    {[...improvement.feedback_log]
                                        .reverse()
                                        .map((entry, i) => (
                                            <li
                                                key={`${entry.at}-${i}`}
                                                className="rounded-md border p-3"
                                            >
                                                <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                                                    <span>
                                                        {entry.by} ·{' '}
                                                        {improvementStatusLabel(
                                                            t,
                                                            entry.status,
                                                        )}{' '}
                                                        ·{' '}
                                                        {improvementDecisionLabel(
                                                            t,
                                                            entry.decision,
                                                        )}
                                                    </span>
                                                    <span>{entry.at}</span>
                                                </div>
                                                {entry.text && (
                                                    <p className="mt-1 whitespace-pre-line">
                                                        {entry.text}
                                                    </p>
                                                )}
                                            </li>
                                        ))}
                                </ul>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {!can.review && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('improvements.show.section_after')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {improvement.after_condition ||
                            improvement.after_result ||
                            improvement.after_date ? (
                                <dl className="grid gap-4 sm:grid-cols-2">
                                    <Detail
                                        label={t(
                                            'improvements.fields.after_condition',
                                        )}
                                        value={improvement.after_condition}
                                    />
                                    <Detail
                                        label={t(
                                            'improvements.fields.after_result',
                                        )}
                                        value={improvement.after_result}
                                    />
                                    <Detail
                                        label={t(
                                            'improvements.fields.after_date',
                                        )}
                                        value={improvement.after_date}
                                    />
                                    <Detail
                                        label={t(
                                            'improvements.fields.after_pic',
                                        )}
                                        value={improvement.after_pic}
                                    />
                                </dl>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {t('improvements.show.after_empty')}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {improvement.after_links.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('improvements.fields.after_links')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-1 text-sm">
                                {improvement.after_links.map((link) => (
                                    <li key={link.url}>
                                        <a
                                            href={link.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="break-all underline underline-offset-2"
                                        >
                                            {link.title || link.url}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('improvements.show.section_files')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {files.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('improvements.show.files_empty')}
                            </p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {files.map((f) => (
                                    <li
                                        key={f.id}
                                        className="flex flex-wrap items-center justify-between gap-2 border-b pb-2 last:border-0"
                                    >
                                        <div>
                                            {f.is_missing ? (
                                                <span className="text-muted-foreground line-through">
                                                    {f.file_name}
                                                </span>
                                            ) : (
                                                <a
                                                    href={f.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1 font-medium underline underline-offset-2"
                                                >
                                                    <Download className="size-3.5" />
                                                    {f.file_name}
                                                </a>
                                            )}
                                            <div className="text-xs text-muted-foreground">
                                                {f.phase === 'after' && (
                                                    <Badge
                                                        variant="outline"
                                                        className="mr-1.5 border-emerald-300 text-emerald-800 dark:text-emerald-200"
                                                    >
                                                        {t(
                                                            'improvements.show.file_phase_after',
                                                        )}
                                                    </Badge>
                                                )}
                                                {formatBytes(f.size_bytes)}
                                                {f.description
                                                    ? ` · ${f.description}`
                                                    : ''}
                                                {f.uploaded_at
                                                    ? ` · ${f.uploaded_at}`
                                                    : ''}
                                            </div>
                                        </div>
                                        {can.attach && (
                                            <ConfirmDialog
                                                trigger={
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                    >
                                                        {t(
                                                            'improvements.delete',
                                                        )}
                                                    </Button>
                                                }
                                                title={t('improvements.delete')}
                                                description={f.file_name}
                                                confirmLabel={t(
                                                    'improvements.delete',
                                                )}
                                                formProps={ImprovementFileController.destroy.form(
                                                    {
                                                        improvement:
                                                            improvement.id,
                                                        file: f.id,
                                                    },
                                                )}
                                            />
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {can.attach && (
                            <Form
                                {...ImprovementFileController.store.form(
                                    improvement.id,
                                )}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="grid gap-3 border-t pt-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <FieldBlock
                                            label={`${t('improvements.show.file')} (max ${uploadLimitMb} MB)`}
                                            error={errors.file}
                                        >
                                            <Input
                                                type="file"
                                                name="file"
                                                required
                                            />
                                        </FieldBlock>
                                        <FieldBlock
                                            label={t(
                                                'improvements.show.description',
                                            )}
                                            error={errors.description}
                                        >
                                            <Input name="description" />
                                        </FieldBlock>
                                        {can.review && (
                                            <Label className="flex items-center gap-2 sm:order-last sm:col-span-3">
                                                <Checkbox
                                                    name="phase"
                                                    value="after"
                                                />
                                                {t(
                                                    'improvements.show.upload_as_after',
                                                )}
                                            </Label>
                                        )}
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t('improvements.show.upload')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('improvements.log.title')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-2 text-sm">
                            {logs.map((l) => {
                                const key = `improvements.log.actions.${l.action}`;
                                const label = t(key);

                                return (
                                    <li
                                        key={l.id}
                                        className="flex flex-wrap items-baseline justify-between gap-2 border-b pb-2 last:border-0"
                                    >
                                        <span>
                                            <span className="font-medium">
                                                {label === key
                                                    ? l.action
                                                    : label}
                                            </span>
                                            {l.user_name && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · {l.user_name}
                                                </span>
                                            )}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {l.created_at}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </CardContent>
                </Card>
            </div>

            {editing && (
                <ImprovementDialog
                    open={editing}
                    onOpenChange={setEditing}
                    item={improvement}
                />
            )}
        </>
    );
}
