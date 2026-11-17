import { Head, Link } from '@inertiajs/react';
import { Download, FileWarning, Pencil, Plus, Upload } from 'lucide-react';
import { useState } from 'react';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import ProjectFileController from '@/actions/App/Http/Controllers/ProjectFileController';
import ProjectMilestoneController from '@/actions/App/Http/Controllers/ProjectMilestoneController';
import ProjectQuotationController from '@/actions/App/Http/Controllers/ProjectQuotationController';
import ProjectStageController from '@/actions/App/Http/Controllers/ProjectStageController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { ProgressBar } from '@/components/projects/progress-bar';
import {
    FileUploadDialog,
    MilestoneDialog,
    QuotationDialog,
    StageDialog,
} from '@/components/projects/project-dialogs';
import type {
    Milestone,
    ProjectFileItem,
    Quotation,
    Stage,
} from '@/components/projects/project-dialogs';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import {
    formatBytes,
    formatMoney,
    milestoneStatusLabel,
    projectPriorityLabel,
    projectStatusClassName,
    projectStatusLabel,
    quotationStatusClassName,
    quotationStatusLabel,
    stageTypeLabel,
} from '@/lib/project';
import { edit, index as projectsIndex } from '@/routes/projects';

type Project = {
    id: number;
    code: string;
    name: string;
    category: string;
    status: string;
    priority: string;
    pic_name: string;
    area: string;
    start_date: string | null;
    end_date: string | null;
    progress: number;
    description: string | null;
    vendor: string;
    po: string;
    is_overdue: boolean;
    created_by_name: string;
    updated_by_name: string;
    updated_at: string | null;
};

type LogItem = {
    id: number;
    user_name: string | null;
    action: string;
    detail: string | null;
    created_at: string | null;
};

type PageProps = {
    project: Project;
    stages: Stage[];
    milestones: Milestone[];
    quotations: Quotation[];
    files: ProjectFileItem[];
    logs: LogItem[];
    options: {
        projectStatuses: string[];
        stageStatuses: string[];
        stageTypes: string[];
        milestoneStatuses: string[];
        quotationStatuses: string[];
        vendors: string[];
    };
    can: { update: boolean; delete: boolean };
    uploadLimitMb: number;
    allowedExtensions: string[];
};

function SectionCard({
    title,
    description,
    action,
    children,
}: {
    readonly title: string;
    readonly description?: string;
    readonly action?: React.ReactNode;
    readonly children: React.ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex-row flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 className="font-semibold">{title}</h3>
                    {description && (
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {action}
            </CardHeader>
            <CardContent className="space-y-3">{children}</CardContent>
        </Card>
    );
}

function Empty({ text }: { readonly text: string }) {
    return <p className="text-sm text-muted-foreground">{text}</p>;
}

function Info({
    label,
    children,
}: {
    readonly label: string;
    readonly children: React.ReactNode;
}) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm break-words">{children || '-'}</dd>
        </div>
    );
}

function FileLink({ file }: { readonly file: ProjectFileItem }) {
    const { t } = useTranslation();

    if (file.is_missing) {
        return (
            <span className="inline-flex items-center gap-1 text-sm text-muted-foreground">
                <FileWarning className="size-4" />
                {file.file_name}
                <span className="text-xs">({t('projects.files.missing')})</span>
            </span>
        );
    }

    return (
        <a
            href={file.url}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-1 text-sm font-medium underline-offset-2 hover:underline"
        >
            <Download className="size-4" />
            {file.file_name}
        </a>
    );
}

export default function ProjectShow({
    project,
    stages,
    milestones,
    quotations,
    files,
    logs,
    options,
    can,
    uploadLimitMb,
    allowedExtensions,
}: PageProps) {
    const { t, formatDateOnly, intlLocale } = useTranslation();
    const [stageDialog, setStageDialog] = useState<{
        open: boolean;
        item: Stage | null;
    }>({ open: false, item: null });
    const [milestoneDialog, setMilestoneDialog] = useState<{
        open: boolean;
        item: Milestone | null;
    }>({ open: false, item: null });
    const [quotationDialog, setQuotationDialog] = useState<{
        open: boolean;
        item: Quotation | null;
    }>({ open: false, item: null });
    const [fileDialog, setFileDialog] = useState<{
        open: boolean;
        context: 'project' | 'stage' | 'quotation';
        targetId: number | null;
        key: number;
    }>({ open: false, context: 'project', targetId: null, key: 0 });

    function openUpload(
        context: 'project' | 'stage' | 'quotation',
        targetId: number | null = null,
    ) {
        setFileDialog((prev) => ({
            open: true,
            context,
            targetId,
            key: prev.key + 1,
        }));
    }

    const stageFiles = (stageId: number) =>
        files.filter((f) => f.stage_id === stageId);
    const projectFiles = files.filter((f) => f.context !== 'stage');
    const allStageFiles = files.filter((f) => f.context === 'stage');

    return (
        <>
            <Head
                title={`${t('projects.show.head_title')} · ${project.name}`}
            />

            <div className="space-y-6 p-4">
                {/* Overview: read first, act afterwards. */}
                <Card>
                    <CardContent className="space-y-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0 space-y-2">
                                <h2 className="text-xl font-semibold tracking-tight">
                                    {project.name}
                                </h2>
                                <div className="flex flex-wrap items-center gap-2">
                                    {project.code && (
                                        <Badge variant="secondary">
                                            {project.code}
                                        </Badge>
                                    )}
                                    <Badge
                                        variant="outline"
                                        className={projectStatusClassName(
                                            project.status,
                                        )}
                                    >
                                        {projectStatusLabel(t, project.status)}
                                    </Badge>
                                    <Badge variant="outline">
                                        {projectPriorityLabel(
                                            t,
                                            project.priority,
                                        )}
                                    </Badge>
                                    {project.is_overdue && (
                                        <Badge
                                            variant="outline"
                                            className="border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
                                        >
                                            {t('projects.table.overdue')}
                                        </Badge>
                                    )}
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button variant="outline" asChild>
                                    <Link href={projectsIndex().url}>
                                        {t('projects.show.back')}
                                    </Link>
                                </Button>
                                {can.update && (
                                    <Button asChild>
                                        <Link href={edit(project.id).url}>
                                            <Pencil />
                                            {t('projects.show.edit')}
                                        </Link>
                                    </Button>
                                )}
                                {can.delete && (
                                    <ConfirmDialog
                                        trigger={
                                            <Button variant="destructive">
                                                {t('projects.show.delete')}
                                            </Button>
                                        }
                                        title={t('projects.show.delete_title')}
                                        description={t(
                                            'projects.show.delete_description',
                                            { name: project.name },
                                        )}
                                        confirmLabel={t('projects.show.delete')}
                                        formProps={ProjectController.destroy.form(
                                            project.id,
                                        )}
                                    />
                                )}
                            </div>
                        </div>

                        <ProgressBar value={project.progress} />

                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Info label={t('projects.fields.pic')}>
                                {project.pic_name}
                            </Info>
                            <Info label={t('projects.fields.category')}>
                                {project.category}
                            </Info>
                            <Info label={t('projects.fields.area')}>
                                {project.area}
                            </Info>
                            <Info label={t('projects.fields.period')}>
                                {formatDateOnly(project.start_date)} –{' '}
                                {formatDateOnly(project.end_date)}
                            </Info>
                            <Info label={t('projects.fields.vendor')}>
                                {project.vendor}
                            </Info>
                            <Info label={t('projects.fields.po')}>
                                {project.po}
                            </Info>
                            <Info label={t('projects.show.created_by')}>
                                {project.created_by_name}
                            </Info>
                            <Info label={t('projects.show.updated_by')}>
                                {project.updated_by_name}
                                {project.updated_at
                                    ? ` · ${project.updated_at}`
                                    : ''}
                            </Info>
                        </dl>

                        <p className="text-sm whitespace-pre-line text-muted-foreground">
                            {project.description ||
                                t('projects.show.no_description')}
                        </p>

                        {!can.update && (
                            <Alert>
                                <AlertDescription>
                                    {t('projects.show.readonly')}
                                </AlertDescription>
                            </Alert>
                        )}
                    </CardContent>
                </Card>

                <SectionCard
                    title={t('projects.stage.title')}
                    description={t('projects.stage.description')}
                    action={
                        can.update && (
                            <Button
                                size="sm"
                                onClick={() =>
                                    setStageDialog({ open: true, item: null })
                                }
                            >
                                <Plus />
                                {t('projects.stage.add')}
                            </Button>
                        )
                    }
                >
                    {stages.length === 0 ? (
                        <Empty text={t('projects.stage.empty')} />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('projects.stage.name')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.stage.pic')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.stage.plan')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.stage.actual')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.fields.status')}
                                    </TableHead>
                                    <TableHead className="w-40">
                                        {t('projects.stage.progress')}
                                    </TableHead>
                                    {can.update && (
                                        <TableHead>
                                            <span className="sr-only">
                                                {t('projects.actions.edit')}
                                            </span>
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {stages.map((s) => (
                                    <TableRow key={s.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {s.name}
                                            </div>
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                {s.stage_type ===
                                                    'Validation' && (
                                                    <Badge variant="secondary">
                                                        {stageTypeLabel(
                                                            t,
                                                            s.stage_type,
                                                        )}
                                                    </Badge>
                                                )}
                                                {s.is_parallel && (
                                                    <Badge variant="outline">
                                                        {t(
                                                            'projects.stage.parallel_badge',
                                                        )}
                                                    </Badge>
                                                )}
                                            </div>
                                            {stageFiles(s.id).map((f) => (
                                                <div key={f.id}>
                                                    <FileLink file={f} />
                                                </div>
                                            ))}
                                        </TableCell>
                                        <TableCell>
                                            {s.pic_name || '-'}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {formatDateOnly(s.plan_start)}
                                            <br />
                                            {formatDateOnly(s.plan_end)}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {formatDateOnly(s.actual_start)}
                                            <br />
                                            {formatDateOnly(s.actual_end)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={projectStatusClassName(
                                                    s.status,
                                                )}
                                            >
                                                {projectStatusLabel(
                                                    t,
                                                    s.status,
                                                )}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <ProgressBar value={s.progress} />
                                        </TableCell>
                                        {can.update && (
                                            <TableCell>
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            openUpload(
                                                                'stage',
                                                                s.id,
                                                            )
                                                        }
                                                        aria-label={t(
                                                            'projects.files.add',
                                                        )}
                                                    >
                                                        <Upload />
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setStageDialog({
                                                                open: true,
                                                                item: s,
                                                            })
                                                        }
                                                    >
                                                        {t(
                                                            'projects.actions.edit',
                                                        )}
                                                    </Button>
                                                    <ConfirmDialog
                                                        trigger={
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                            >
                                                                {t(
                                                                    'projects.actions.delete',
                                                                )}
                                                            </Button>
                                                        }
                                                        title={t(
                                                            'projects.stage.delete_title',
                                                        )}
                                                        description={t(
                                                            'projects.stage.delete_description',
                                                            { name: s.name },
                                                        )}
                                                        confirmLabel={t(
                                                            'projects.actions.delete',
                                                        )}
                                                        formProps={ProjectStageController.destroy.form(
                                                            {
                                                                project:
                                                                    project.id,
                                                                stage: s.id,
                                                            },
                                                        )}
                                                    />
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </SectionCard>

                <SectionCard
                    title={t('projects.milestone.title')}
                    description={t('projects.milestone.description')}
                    action={
                        can.update && (
                            <Button
                                size="sm"
                                onClick={() =>
                                    setMilestoneDialog({
                                        open: true,
                                        item: null,
                                    })
                                }
                            >
                                <Plus />
                                {t('projects.milestone.add')}
                            </Button>
                        )
                    }
                >
                    {milestones.length === 0 ? (
                        <Empty text={t('projects.milestone.empty')} />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('projects.milestone.name')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.milestone.due_date')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.fields.status')}
                                    </TableHead>
                                    {can.update && (
                                        <TableHead>
                                            <span className="sr-only">
                                                {t('projects.actions.edit')}
                                            </span>
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {milestones.map((m) => (
                                    <TableRow key={m.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {m.name}
                                            </div>
                                            {m.note && (
                                                <div className="text-xs text-muted-foreground">
                                                    {m.note}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {formatDateOnly(m.due_date)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={projectStatusClassName(
                                                    m.status === 'Done'
                                                        ? 'Completed'
                                                        : 'Not Started',
                                                )}
                                            >
                                                {milestoneStatusLabel(
                                                    t,
                                                    m.status,
                                                )}
                                            </Badge>
                                        </TableCell>
                                        {can.update && (
                                            <TableCell>
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setMilestoneDialog({
                                                                open: true,
                                                                item: m,
                                                            })
                                                        }
                                                    >
                                                        {t(
                                                            'projects.actions.edit',
                                                        )}
                                                    </Button>
                                                    <ConfirmDialog
                                                        trigger={
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                            >
                                                                {t(
                                                                    'projects.actions.delete',
                                                                )}
                                                            </Button>
                                                        }
                                                        title={t(
                                                            'projects.milestone.delete_title',
                                                        )}
                                                        description={t(
                                                            'projects.milestone.delete_description',
                                                            { name: m.name },
                                                        )}
                                                        confirmLabel={t(
                                                            'projects.actions.delete',
                                                        )}
                                                        formProps={ProjectMilestoneController.destroy.form(
                                                            {
                                                                project:
                                                                    project.id,
                                                                milestone: m.id,
                                                            },
                                                        )}
                                                    />
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </SectionCard>

                <SectionCard
                    title={t('projects.quotation.title')}
                    description={t('projects.quotation.description')}
                    action={
                        can.update && (
                            <Button
                                size="sm"
                                onClick={() =>
                                    setQuotationDialog({
                                        open: true,
                                        item: null,
                                    })
                                }
                            >
                                <Plus />
                                {t('projects.quotation.add')}
                            </Button>
                        )
                    }
                >
                    {quotations.length === 0 ? (
                        <Empty text={t('projects.quotation.empty')} />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('projects.quotation.vendor')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.quotation.number')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('projects.quotation.amount')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.quotation.valid_until')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.fields.status')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.quotation.file')}
                                    </TableHead>
                                    {can.update && (
                                        <TableHead>
                                            <span className="sr-only">
                                                {t('projects.actions.edit')}
                                            </span>
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {quotations.map((q) => (
                                    <TableRow key={q.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {q.vendor_name}
                                            </div>
                                            {q.link && (
                                                <a
                                                    href={q.link}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-xs underline"
                                                >
                                                    {t(
                                                        'projects.quotation.link',
                                                    )}
                                                </a>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {q.quotation_no || '-'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(
                                                q.amount,
                                                q.currency,
                                                intlLocale,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {formatDateOnly(q.valid_until)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={quotationStatusClassName(
                                                    q.status,
                                                )}
                                            >
                                                {quotationStatusLabel(
                                                    t,
                                                    q.status,
                                                )}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {q.file ? (
                                                <FileLink file={q.file} />
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    {t(
                                                        'projects.quotation.no_file',
                                                    )}
                                                </span>
                                            )}
                                        </TableCell>
                                        {can.update && (
                                            <TableCell>
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            openUpload(
                                                                'quotation',
                                                                q.id,
                                                            )
                                                        }
                                                        aria-label={t(
                                                            'projects.files.add',
                                                        )}
                                                    >
                                                        <Upload />
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setQuotationDialog({
                                                                open: true,
                                                                item: q,
                                                            })
                                                        }
                                                    >
                                                        {t(
                                                            'projects.actions.edit',
                                                        )}
                                                    </Button>
                                                    <ConfirmDialog
                                                        trigger={
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                            >
                                                                {t(
                                                                    'projects.actions.delete',
                                                                )}
                                                            </Button>
                                                        }
                                                        title={t(
                                                            'projects.quotation.delete_title',
                                                        )}
                                                        description={t(
                                                            'projects.quotation.delete_description',
                                                            {
                                                                name: q.vendor_name,
                                                            },
                                                        )}
                                                        confirmLabel={t(
                                                            'projects.actions.delete',
                                                        )}
                                                        formProps={ProjectQuotationController.destroy.form(
                                                            {
                                                                project:
                                                                    project.id,
                                                                quotation: q.id,
                                                            },
                                                        )}
                                                    />
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </SectionCard>

                <SectionCard
                    title={t('projects.files.title')}
                    description={t('projects.files.description')}
                    action={
                        can.update && (
                            <Button
                                size="sm"
                                onClick={() => openUpload('project')}
                            >
                                <Upload />
                                {t('projects.files.add')}
                            </Button>
                        )
                    }
                >
                    {projectFiles.length + allStageFiles.length === 0 ? (
                        <Empty text={t('projects.files.empty')} />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('projects.files.file')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.files.context')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.files.uploaded_by')}
                                    </TableHead>
                                    {can.update && (
                                        <TableHead>
                                            <span className="sr-only">
                                                {t('projects.actions.delete')}
                                            </span>
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {[...projectFiles, ...allStageFiles].map(
                                    (f) => (
                                        <TableRow key={f.id}>
                                            <TableCell>
                                                <FileLink file={f} />
                                                <div className="text-xs text-muted-foreground">
                                                    {formatBytes(f.size_bytes)}
                                                    {f.description
                                                        ? ` · ${f.description}`
                                                        : ''}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {t(
                                                    `projects.files.contexts.${f.context}`,
                                                )}
                                                {f.context === 'stage' &&
                                                    ` · ${stages.find((s) => s.id === f.stage_id)?.name ?? ''}`}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                {f.uploaded_by_name || '-'}
                                                <br />
                                                {f.uploaded_at}
                                            </TableCell>
                                            {can.update && (
                                                <TableCell className="text-right">
                                                    <ConfirmDialog
                                                        trigger={
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                            >
                                                                {t(
                                                                    'projects.actions.delete',
                                                                )}
                                                            </Button>
                                                        }
                                                        title={t(
                                                            'projects.files.delete_title',
                                                        )}
                                                        description={t(
                                                            'projects.files.delete_description',
                                                            {
                                                                name: f.file_name,
                                                            },
                                                        )}
                                                        confirmLabel={t(
                                                            'projects.actions.delete',
                                                        )}
                                                        formProps={ProjectFileController.destroy.form(
                                                            {
                                                                project:
                                                                    project.id,
                                                                file: f.id,
                                                            },
                                                        )}
                                                    />
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ),
                                )}
                            </TableBody>
                        </Table>
                    )}
                </SectionCard>

                <SectionCard title={t('projects.log.title')}>
                    {logs.length === 0 ? (
                        <Empty text={t('projects.log.empty')} />
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {logs.map((l) => (
                                <li
                                    key={l.id}
                                    className="flex flex-wrap items-baseline justify-between gap-2 border-b pb-2 last:border-0"
                                >
                                    <span>
                                        <span className="font-medium">
                                            {(() => {
                                                const key = `projects.log.actions.${l.action}`;
                                                const label = t(key);

                                                return label === key
                                                    ? l.action
                                                    : label;
                                            })()}
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
                            ))}
                        </ul>
                    )}
                </SectionCard>
            </div>

            {can.update && (
                <>
                    <StageDialog
                        key={`stage-${stageDialog.item?.id ?? 'new'}-${stageDialog.open}`}
                        open={stageDialog.open}
                        onOpenChange={(open) =>
                            setStageDialog((p) => ({ ...p, open }))
                        }
                        projectId={project.id}
                        item={stageDialog.item}
                        statuses={options.stageStatuses}
                        types={options.stageTypes}
                    />
                    <MilestoneDialog
                        key={`milestone-${milestoneDialog.item?.id ?? 'new'}-${milestoneDialog.open}`}
                        open={milestoneDialog.open}
                        onOpenChange={(open) =>
                            setMilestoneDialog((p) => ({ ...p, open }))
                        }
                        projectId={project.id}
                        item={milestoneDialog.item}
                        statuses={options.milestoneStatuses}
                    />
                    <QuotationDialog
                        key={`quotation-${quotationDialog.item?.id ?? 'new'}-${quotationDialog.open}`}
                        open={quotationDialog.open}
                        onOpenChange={(open) =>
                            setQuotationDialog((p) => ({ ...p, open }))
                        }
                        projectId={project.id}
                        item={quotationDialog.item}
                        statuses={options.quotationStatuses}
                        vendors={options.vendors}
                    />
                    <FileUploadDialog
                        key={`file-${fileDialog.key}`}
                        open={fileDialog.open}
                        onOpenChange={(open) =>
                            setFileDialog((p) => ({ ...p, open }))
                        }
                        projectId={project.id}
                        stages={stages}
                        quotations={quotations}
                        limitMb={uploadLimitMb}
                        extensions={allowedExtensions}
                        presetContext={fileDialog.context}
                        presetTargetId={fileDialog.targetId}
                    />
                </>
            )}
        </>
    );
}
