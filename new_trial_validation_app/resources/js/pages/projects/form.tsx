import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import { Combobox } from '@/components/combobox';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
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
import { projectPriorityLabel, projectStatusLabel } from '@/lib/project';
import { index as projectsIndex, show } from '@/routes/projects';

type ProjectData = {
    id: number;
    code: string;
    name: string;
    category: string;
    status: string;
    priority: string;
    pic_user_id: number | null;
    area: string;
    start_date: string | null;
    end_date: string | null;
    progress: number;
    description: string | null;
    vendor: string;
    po: string;
    has_stages: boolean;
};

type PageProps = {
    project: ProjectData | null;
    options: {
        statuses: string[];
        priorities: string[];
        categories: string[];
        users: { value: string; label: string }[];
    };
    defaults: { pic_user_id: number } | null;
};

export default function ProjectForm({ project, options, defaults }: PageProps) {
    const { t } = useTranslation();
    const [pic, setPic] = useState(
        String(project?.pic_user_id ?? defaults?.pic_user_id ?? ''),
    );
    const formProps = project
        ? ProjectController.update.form(project.id)
        : ProjectController.store.form();

    return (
        <>
            <Head
                title={t(
                    project
                        ? 'projects.form.edit_title'
                        : 'projects.form.create_title',
                )}
            />

            <div className="space-y-6 p-4">
                <Heading
                    title={t(
                        project
                            ? 'projects.form.edit_title'
                            : 'projects.form.create_title',
                    )}
                    description={
                        project
                            ? undefined
                            : t('projects.form.create_description')
                    }
                />

                <Form {...formProps} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <h3 className="font-semibold">
                                        {t('projects.form.section_info')}
                                    </h3>
                                </CardHeader>
                                <CardContent className="grid gap-4 md:grid-cols-2">
                                    <div className="grid min-w-0 gap-2 md:col-span-2">
                                        <Label htmlFor="name">
                                            {t('projects.fields.name')}
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            defaultValue={project?.name ?? ''}
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="code">
                                            {t('projects.fields.code')}
                                        </Label>
                                        <Input
                                            id="code"
                                            name="code"
                                            defaultValue={project?.code ?? ''}
                                        />
                                        <InputError message={errors.code} />
                                    </div>

                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="category">
                                            {t('projects.fields.category')}
                                        </Label>
                                        <Input
                                            id="category"
                                            name="category"
                                            list="project-categories"
                                            required
                                            placeholder={t(
                                                'projects.form.category_placeholder',
                                            )}
                                            defaultValue={
                                                project?.category ?? ''
                                            }
                                        />
                                        <datalist id="project-categories">
                                            {options.categories.map((c) => (
                                                <option key={c} value={c} />
                                            ))}
                                        </datalist>
                                        <InputError message={errors.category} />
                                    </div>

                                    <div className="grid min-w-0 gap-2">
                                        <Label>
                                            {t('projects.fields.pic')}
                                        </Label>
                                        <Combobox
                                            options={options.users}
                                            value={pic}
                                            onChange={setPic}
                                            placeholder={t(
                                                'projects.form.pic_placeholder',
                                            )}
                                        />
                                        <input
                                            type="hidden"
                                            name="pic_user_id"
                                            value={pic}
                                        />
                                        <InputError
                                            message={errors.pic_user_id}
                                        />
                                    </div>

                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="area">
                                            {t('projects.fields.area')}
                                        </Label>
                                        <Input
                                            id="area"
                                            name="area"
                                            defaultValue={project?.area ?? ''}
                                        />
                                        <InputError message={errors.area} />
                                    </div>

                                    <div className="grid min-w-0 gap-2 md:col-span-2">
                                        <Label htmlFor="description">
                                            {t('projects.fields.description')}
                                        </Label>
                                        <Textarea
                                            id="description"
                                            name="description"
                                            rows={4}
                                            defaultValue={
                                                project?.description ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.description}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <h3 className="font-semibold">
                                        {t('projects.form.section_schedule')}
                                    </h3>
                                </CardHeader>
                                <CardContent className="grid gap-4 md:grid-cols-2">
                                    <div className="grid min-w-0 gap-2">
                                        <Label>
                                            {t('projects.fields.status')}
                                        </Label>
                                        <Select
                                            name="status"
                                            defaultValue={
                                                project?.status ?? 'Not Started'
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.statuses.map((s) => (
                                                    <SelectItem
                                                        key={s}
                                                        value={s}
                                                    >
                                                        {projectStatusLabel(
                                                            t,
                                                            s,
                                                        )}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.status} />
                                    </div>

                                    <div className="grid min-w-0 gap-2">
                                        <Label>
                                            {t('projects.fields.priority')}
                                        </Label>
                                        <Select
                                            name="priority"
                                            defaultValue={
                                                project?.priority ?? 'Medium'
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.priorities.map((p) => (
                                                    <SelectItem
                                                        key={p}
                                                        value={p}
                                                    >
                                                        {projectPriorityLabel(
                                                            t,
                                                            p,
                                                        )}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.priority} />
                                    </div>

                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="start_date">
                                            {t('projects.fields.start_date')}
                                        </Label>
                                        <Input
                                            id="start_date"
                                            name="start_date"
                                            type="date"
                                            defaultValue={
                                                project?.start_date ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.start_date}
                                        />
                                    </div>

                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="end_date">
                                            {t('projects.fields.end_date')}
                                        </Label>
                                        <Input
                                            id="end_date"
                                            name="end_date"
                                            type="date"
                                            defaultValue={
                                                project?.end_date ?? ''
                                            }
                                        />
                                        <InputError message={errors.end_date} />
                                    </div>

                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="progress">
                                            {t('projects.fields.progress')}
                                        </Label>
                                        {project?.has_stages ? (
                                            <p className="text-sm text-muted-foreground">
                                                {t(
                                                    'projects.form.progress_auto',
                                                    {
                                                        value: project.progress,
                                                    },
                                                )}
                                            </p>
                                        ) : (
                                            <>
                                                <Input
                                                    id="progress"
                                                    name="progress"
                                                    type="number"
                                                    min={0}
                                                    max={100}
                                                    defaultValue={
                                                        project?.progress ?? 0
                                                    }
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    {t(
                                                        'projects.form.progress_hint',
                                                    )}
                                                </p>
                                            </>
                                        )}
                                        <InputError message={errors.progress} />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <h3 className="font-semibold">
                                        {t('projects.form.section_extra')}
                                    </h3>
                                </CardHeader>
                                <CardContent className="grid gap-4 md:grid-cols-2">
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="vendor">
                                            {t('projects.fields.vendor')}
                                        </Label>
                                        <Input
                                            id="vendor"
                                            name="vendor"
                                            defaultValue={project?.vendor ?? ''}
                                        />
                                        <InputError message={errors.vendor} />
                                    </div>
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="po">
                                            {t('projects.fields.po')}
                                        </Label>
                                        <Input
                                            id="po"
                                            name="po"
                                            defaultValue={project?.po ?? ''}
                                        />
                                        <InputError message={errors.po} />
                                    </div>
                                </CardContent>
                            </Card>

                            <div className="flex flex-wrap justify-end gap-2">
                                <Button type="button" variant="outline" asChild>
                                    <Link
                                        href={
                                            project
                                                ? show(project.id).url
                                                : projectsIndex().url
                                        }
                                    >
                                        {t('projects.form.cancel')}
                                    </Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {t('projects.form.save')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
