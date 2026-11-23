import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import ProjectFileController from '@/actions/App/Http/Controllers/ProjectFileController';
import ProjectMilestoneController from '@/actions/App/Http/Controllers/ProjectMilestoneController';
import ProjectQuotationController from '@/actions/App/Http/Controllers/ProjectQuotationController';
import ProjectStageController from '@/actions/App/Http/Controllers/ProjectStageController';
import ProjectStageTrialController from '@/actions/App/Http/Controllers/ProjectStageTrialController';
import { Combobox } from '@/components/combobox';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    milestoneStatusLabel,
    projectStatusLabel,
    quotationStatusLabel,
    stageTypeLabel,
} from '@/lib/project';

export type Stage = {
    id: number;
    name: string;
    stage_type: string;
    pic_name: string;
    plan_start: string | null;
    plan_end: string | null;
    actual_start: string | null;
    actual_end: string | null;
    progress: number;
    is_parallel: boolean;
    status: string;
    note: string | null;
    trial_id: number | null;
    trial?: LinkedTrial | null;
};

export type LinkedTrial = {
    id: number;
    trial_code: string;
    product_name: string | null;
    progress_status: string;
    final_decision: string | null;
    is_deleted: boolean;
    can_view: boolean;
};

export type Milestone = {
    id: number;
    name: string;
    due_date: string | null;
    status: string;
    note: string | null;
};

export type ProjectFileItem = {
    id: number;
    stage_id: number | null;
    context: string;
    file_name: string;
    mime_type: string;
    size_bytes: number;
    description: string | null;
    uploaded_by_name: string;
    uploaded_at: string | null;
    is_missing: boolean;
    url: string;
};

export type Quotation = {
    id: number;
    vendor_name: string;
    quotation_no: string;
    amount: number;
    currency: string;
    quotation_date: string | null;
    valid_until: string | null;
    status: string;
    note: string | null;
    link: string;
    file: ProjectFileItem | null;
};

type DialogProps<T> = {
    readonly open: boolean;
    readonly onOpenChange: (open: boolean) => void;
    readonly projectId: number;
    readonly item: T | null;
};

function Field({
    label,
    error,
    className,
    children,
}: {
    readonly label: string;
    readonly error?: string;
    readonly className?: string;
    readonly children: ReactNode;
}) {
    return (
        <div className={`grid min-w-0 gap-2 ${className ?? ''}`}>
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function StatusSelect({
    name,
    defaultValue,
    values,
    label,
}: {
    readonly name: string;
    readonly defaultValue: string;
    readonly values: string[];
    readonly label: (value: string) => string;
}) {
    return (
        <Select name={name} defaultValue={defaultValue}>
            <SelectTrigger className="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {values.map((value) => (
                    <SelectItem key={value} value={value}>
                        {label(value)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export function StageDialog({
    open,
    onOpenChange,
    projectId,
    item,
    statuses,
    types,
}: DialogProps<Stage> & {
    readonly statuses: string[];
    readonly types: string[];
}) {
    const { t } = useTranslation();
    const [parallel, setParallel] = useState(item?.is_parallel ?? false);
    const formProps = item
        ? ProjectStageController.update.form({
              project: projectId,
              stage: item.id,
          })
        : ProjectStageController.store.form(projectId);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {t(item ? 'projects.stage.edit' : 'projects.stage.add')}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    {...formProps}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4 md:grid-cols-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label={t('projects.stage.name')}
                                error={errors.name}
                                className="md:col-span-2"
                            >
                                <Input
                                    name="name"
                                    required
                                    defaultValue={item?.name ?? ''}
                                />
                            </Field>
                            <Field
                                label={t('projects.stage.type')}
                                error={errors.stage_type}
                            >
                                <StatusSelect
                                    name="stage_type"
                                    defaultValue={item?.stage_type ?? 'General'}
                                    values={types}
                                    label={(v) => stageTypeLabel(t, v)}
                                />
                            </Field>
                            <Field
                                label={t('projects.stage.pic')}
                                error={errors.pic_name}
                            >
                                <Input
                                    name="pic_name"
                                    defaultValue={item?.pic_name ?? ''}
                                />
                            </Field>
                            <Field
                                label={t('projects.stage.plan_start')}
                                error={errors.plan_start}
                            >
                                <Input
                                    type="date"
                                    name="plan_start"
                                    defaultValue={item?.plan_start ?? ''}
                                />
                            </Field>
                            <Field
                                label={t('projects.stage.plan_end')}
                                error={errors.plan_end}
                            >
                                <Input
                                    type="date"
                                    name="plan_end"
                                    defaultValue={item?.plan_end ?? ''}
                                />
                            </Field>
                            <Field
                                label={t('projects.stage.actual_start')}
                                error={errors.actual_start}
                            >
                                <Input
                                    type="date"
                                    name="actual_start"
                                    defaultValue={item?.actual_start ?? ''}
                                />
                            </Field>
                            <Field
                                label={t('projects.stage.actual_end')}
                                error={errors.actual_end}
                            >
                                <Input
                                    type="date"
                                    name="actual_end"
                                    defaultValue={item?.actual_end ?? ''}
                                />
                            </Field>
                            <Field
                                label={t('projects.fields.status')}
                                error={errors.status}
                            >
                                <StatusSelect
                                    name="status"
                                    defaultValue={item?.status ?? 'Not Started'}
                                    values={statuses}
                                    label={(v) => projectStatusLabel(t, v)}
                                />
                            </Field>
                            <Field
                                label={t('projects.stage.progress')}
                                error={errors.progress}
                            >
                                <Input
                                    type="number"
                                    name="progress"
                                    min={0}
                                    max={100}
                                    required
                                    defaultValue={item?.progress ?? 0}
                                />
                            </Field>
                            <div className="flex items-center gap-2 md:col-span-2">
                                <Checkbox
                                    id="stage-parallel"
                                    name="is_parallel"
                                    value="1"
                                    checked={parallel}
                                    onCheckedChange={(v) =>
                                        setParallel(v === true)
                                    }
                                />
                                <Label htmlFor="stage-parallel">
                                    {t('projects.stage.parallel')}
                                </Label>
                            </div>
                            <Field
                                label={t('projects.stage.note')}
                                error={errors.note}
                                className="md:col-span-2"
                            >
                                <Textarea
                                    name="note"
                                    rows={3}
                                    defaultValue={item?.note ?? ''}
                                />
                            </Field>
                            <DialogFooter className="md:col-span-2">
                                <Button type="submit" disabled={processing}>
                                    {t('projects.actions.save')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** Projects module Fase 4: link an existing Trial to a Validation stage. */
export function StageTrialLinkDialog({
    open,
    onOpenChange,
    projectId,
    stage,
    trials,
}: {
    readonly open: boolean;
    readonly onOpenChange: (open: boolean) => void;
    readonly projectId: number;
    readonly stage: Stage | null;
    readonly trials: { value: string; label: string }[];
}) {
    const { t } = useTranslation();
    const [trialId, setTrialId] = useState('');

    if (!stage) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('projects.trial_link.link_title')}
                    </DialogTitle>
                    <DialogDescription>
                        {stage.name} ·{' '}
                        {t('projects.trial_link.link_description')}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...ProjectStageTrialController.store.form({
                        project: projectId,
                        stage: stage.id,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label={t('projects.trial_link.trial')}
                                error={errors.trial_id}
                            >
                                <Combobox
                                    options={trials}
                                    value={trialId}
                                    onChange={setTrialId}
                                    placeholder={t(
                                        'projects.trial_link.link_placeholder',
                                    )}
                                    emptyMessage={t(
                                        'projects.trial_link.no_linkable',
                                    )}
                                />
                                <input
                                    type="hidden"
                                    name="trial_id"
                                    value={trialId}
                                />
                            </Field>
                            <DialogFooter>
                                <Button
                                    type="submit"
                                    disabled={processing || trialId === ''}
                                >
                                    {t('projects.trial_link.link')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function MilestoneDialog({
    open,
    onOpenChange,
    projectId,
    item,
    statuses,
}: DialogProps<Milestone> & { readonly statuses: string[] }) {
    const { t } = useTranslation();
    const formProps = item
        ? ProjectMilestoneController.update.form({
              project: projectId,
              milestone: item.id,
          })
        : ProjectMilestoneController.store.form(projectId);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t(
                            item
                                ? 'projects.milestone.edit'
                                : 'projects.milestone.add',
                        )}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    {...formProps}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label={t('projects.milestone.name')}
                                error={errors.name}
                            >
                                <Input
                                    name="name"
                                    required
                                    defaultValue={item?.name ?? ''}
                                />
                            </Field>
                            <Field
                                label={t('projects.milestone.due_date')}
                                error={errors.due_date}
                            >
                                <Input
                                    type="date"
                                    name="due_date"
                                    defaultValue={item?.due_date ?? ''}
                                />
                            </Field>
                            <Field
                                label={t('projects.fields.status')}
                                error={errors.status}
                            >
                                <StatusSelect
                                    name="status"
                                    defaultValue={item?.status ?? 'Pending'}
                                    values={statuses}
                                    label={(v) => milestoneStatusLabel(t, v)}
                                />
                            </Field>
                            <Field
                                label={t('projects.milestone.note')}
                                error={errors.note}
                            >
                                <Textarea
                                    name="note"
                                    rows={3}
                                    defaultValue={item?.note ?? ''}
                                />
                            </Field>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {t('projects.actions.save')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function QuotationDialog({
    open,
    onOpenChange,
    projectId,
    item,
    statuses,
    vendors,
}: DialogProps<Quotation> & {
    readonly statuses: string[];
    readonly vendors: string[];
}) {
    const { t } = useTranslation();
    const formProps = item
        ? ProjectQuotationController.update.form({
              project: projectId,
              quotation: item.id,
          })
        : ProjectQuotationController.store.form(projectId);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {t(
                            item
                                ? 'projects.quotation.edit'
                                : 'projects.quotation.add',
                        )}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    {...formProps}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4 md:grid-cols-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label={t('projects.quotation.vendor')}
                                error={errors.vendor_name}
                            >
                                <Input
                                    name="vendor_name"
                                    list="quotation-vendors"
                                    required
                                    defaultValue={item?.vendor_name ?? ''}
                                />
                                <datalist id="quotation-vendors">
                                    {vendors.map((v) => (
                                        <option key={v} value={v} />
                                    ))}
                                </datalist>
                            </Field>
                            <Field
                                label={t('projects.quotation.number')}
                                error={errors.quotation_no}
                            >
                                <Input
                                    name="quotation_no"
                                    defaultValue={item?.quotation_no ?? ''}
                                />
                            </Field>
                            <Field
                                label={t('projects.quotation.amount')}
                                error={errors.amount}
                            >
                                <Input
                                    type="number"
                                    name="amount"
                                    min={0}
                                    step="any"
                                    defaultValue={item?.amount ?? 0}
                                />
                            </Field>
                            <Field
                                label={t('projects.quotation.currency')}
                                error={errors.currency}
                            >
                                <Input
                                    name="currency"
                                    maxLength={8}
                                    required
                                    defaultValue={item?.currency ?? 'IDR'}
                                />
                            </Field>
                            <Field
                                label={t('projects.quotation.date')}
                                error={errors.quotation_date}
                            >
                                <Input
                                    type="date"
                                    name="quotation_date"
                                    defaultValue={item?.quotation_date ?? ''}
                                />
                            </Field>
                            <Field
                                label={t('projects.quotation.valid_until')}
                                error={errors.valid_until}
                            >
                                <Input
                                    type="date"
                                    name="valid_until"
                                    defaultValue={item?.valid_until ?? ''}
                                />
                            </Field>
                            <Field
                                label={t('projects.fields.status')}
                                error={errors.status}
                            >
                                <StatusSelect
                                    name="status"
                                    defaultValue={item?.status ?? 'Diterima'}
                                    values={statuses}
                                    label={(v) => quotationStatusLabel(t, v)}
                                />
                            </Field>
                            <Field
                                label={t('projects.quotation.link')}
                                error={errors.link}
                            >
                                <Input
                                    type="url"
                                    name="link"
                                    placeholder="https://"
                                    defaultValue={item?.link ?? ''}
                                />
                            </Field>
                            <Field
                                label={t('projects.quotation.note')}
                                error={errors.note}
                                className="md:col-span-2"
                            >
                                <Textarea
                                    name="note"
                                    rows={3}
                                    defaultValue={item?.note ?? ''}
                                />
                            </Field>
                            <DialogFooter className="md:col-span-2">
                                <Button type="submit" disabled={processing}>
                                    {t('projects.actions.save')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function FileUploadDialog({
    open,
    onOpenChange,
    projectId,
    stages,
    quotations,
    limitMb,
    extensions,
    presetContext,
    presetTargetId,
}: Omit<DialogProps<never>, 'item'> & {
    readonly stages: Stage[];
    readonly quotations: Quotation[];
    readonly limitMb: number;
    readonly extensions: string[];
    readonly presetContext?: 'project' | 'stage' | 'quotation';
    readonly presetTargetId?: number | null;
}) {
    const { t } = useTranslation();
    const [context, setContext] = useState<string>(presetContext ?? 'project');
    const [stageId, setStageId] = useState(
        String(presetContext === 'stage' ? (presetTargetId ?? '') : ''),
    );
    const [quotationId, setQuotationId] = useState(
        String(presetContext === 'quotation' ? (presetTargetId ?? '') : ''),
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('projects.files.add')}</DialogTitle>
                </DialogHeader>
                <Form
                    {...ProjectFileController.store.form(projectId)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label={t('projects.files.context')}
                                error={errors.context}
                            >
                                <Select
                                    name="context"
                                    value={context}
                                    onValueChange={setContext}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {['project', 'stage', 'quotation'].map(
                                            (c) => (
                                                <SelectItem key={c} value={c}>
                                                    {t(
                                                        `projects.files.contexts.${c}`,
                                                    )}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </Field>

                            {context === 'stage' && (
                                <Field
                                    label={t('projects.files.target_stage')}
                                    error={errors.stage_id}
                                >
                                    <Select
                                        name="stage_id"
                                        value={stageId}
                                        onValueChange={setStageId}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {stages.map((s) => (
                                                <SelectItem
                                                    key={s.id}
                                                    value={String(s.id)}
                                                >
                                                    {s.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                            )}

                            {context === 'quotation' && (
                                <Field
                                    label={t('projects.files.target_quotation')}
                                    error={errors.quotation_id}
                                >
                                    <Select
                                        name="quotation_id"
                                        value={quotationId}
                                        onValueChange={setQuotationId}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {quotations.map((q) => (
                                                <SelectItem
                                                    key={q.id}
                                                    value={String(q.id)}
                                                >
                                                    {q.vendor_name}
                                                    {q.quotation_no
                                                        ? ` · ${q.quotation_no}`
                                                        : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                            )}

                            <Field
                                label={t('projects.files.file')}
                                error={errors.file}
                            >
                                <Input type="file" name="file" required />
                                <p className="text-xs text-muted-foreground">
                                    {t('projects.files.hint', {
                                        mb: limitMb,
                                        types: extensions.join(', '),
                                    })}
                                </p>
                            </Field>
                            <Field
                                label={t('projects.files.description_label')}
                                error={errors.description}
                            >
                                <Input name="description" />
                            </Field>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {t('projects.files.upload')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
