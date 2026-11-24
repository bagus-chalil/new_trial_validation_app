import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import ImprovementController from '@/actions/App/Http/Controllers/ImprovementController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';

export type ImprovementFormValues = {
    id: number;
    title: string;
    department: string;
    type: string;
    issue: string | null;
    background: string | null;
    purpose: string | null;
    goals: string | null;
    saving_mp: string | null;
    quality_metric: string | null;
    links: string[];
};

function Field({
    label,
    error,
    hint,
    children,
}: {
    readonly label: string;
    readonly error?: string;
    readonly hint?: string;
    readonly children: ReactNode;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

/** Create (item = null) or edit dialog for an improvement. */
export function ImprovementDialog({
    open,
    onOpenChange,
    item,
    defaultDepartment,
}: {
    readonly open: boolean;
    readonly onOpenChange: (open: boolean) => void;
    readonly item: ImprovementFormValues | null;
    readonly defaultDepartment?: string;
}) {
    const { t } = useTranslation();
    const formProps = item
        ? ImprovementController.update.form(item.id)
        : ImprovementController.store.form();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {t(
                            item
                                ? 'improvements.form.edit_title'
                                : 'improvements.form.create_title',
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
                                label={t('improvements.fields.title')}
                                error={errors.title}
                            >
                                <Input
                                    name="title"
                                    required
                                    defaultValue={item?.title ?? ''}
                                />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label={t('improvements.fields.department')}
                                    error={errors.department}
                                >
                                    <Input
                                        name="department"
                                        defaultValue={
                                            item?.department ??
                                            defaultDepartment ??
                                            ''
                                        }
                                    />
                                </Field>
                                <Field
                                    label={t('improvements.fields.type')}
                                    error={errors.type}
                                >
                                    <Input
                                        name="type"
                                        defaultValue={item?.type ?? ''}
                                    />
                                </Field>
                            </div>
                            <Field
                                label={t('improvements.fields.issue')}
                                error={errors.issue}
                            >
                                <Textarea
                                    name="issue"
                                    rows={3}
                                    required
                                    defaultValue={item?.issue ?? ''}
                                />
                            </Field>
                            <Field
                                label={t('improvements.fields.background')}
                                error={errors.background}
                            >
                                <Textarea
                                    name="background"
                                    rows={3}
                                    defaultValue={item?.background ?? ''}
                                />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label={t('improvements.fields.purpose')}
                                    error={errors.purpose}
                                >
                                    <Textarea
                                        name="purpose"
                                        rows={3}
                                        defaultValue={item?.purpose ?? ''}
                                    />
                                </Field>
                                <Field
                                    label={t('improvements.fields.goals')}
                                    error={errors.goals}
                                >
                                    <Textarea
                                        name="goals"
                                        rows={3}
                                        defaultValue={item?.goals ?? ''}
                                    />
                                </Field>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label={t('improvements.fields.saving_mp')}
                                    error={errors.saving_mp}
                                >
                                    <Input
                                        name="saving_mp"
                                        defaultValue={item?.saving_mp ?? ''}
                                    />
                                </Field>
                                <Field
                                    label={t(
                                        'improvements.fields.quality_metric',
                                    )}
                                    error={errors.quality_metric}
                                >
                                    <Input
                                        name="quality_metric"
                                        defaultValue={
                                            item?.quality_metric ?? ''
                                        }
                                    />
                                </Field>
                            </div>
                            <Field
                                label={t('improvements.fields.links')}
                                hint={t('improvements.fields.links_hint')}
                                error={errors.links}
                            >
                                <Textarea
                                    name="links"
                                    rows={2}
                                    defaultValue={(item?.links ?? []).join(
                                        '\n',
                                    )}
                                />
                            </Field>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                >
                                    {t('improvements.cancel')}
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {t(
                                        item
                                            ? 'improvements.save'
                                            : 'improvements.form.submit',
                                    )}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
