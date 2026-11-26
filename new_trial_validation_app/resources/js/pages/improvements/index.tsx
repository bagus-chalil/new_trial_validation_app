import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { FilterBar } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { ImprovementDialog } from '@/components/improvements/improvement-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import {
    improvementDecisionLabel,
    improvementStatusClassName,
    improvementStatusLabel,
} from '@/lib/project';
import {
    index as improvementsIndex,
    show,
} from '@/routes/projects/improvements';

type Item = {
    id: number;
    title: string;
    department: string;
    type: string;
    status: string;
    decision: string;
    submitted_by_name: string;
    submitted_at: string | null;
    number?: string | null;
    project_id: number | null;
};

type PageProps = {
    items: Item[];
    statuses: string[];
    filters: { q: string };
    defaults: { department: string };
    can: { create: boolean };
};

export default function ImprovementBoard({
    items,
    statuses,
    filters,
    defaults,
    can,
}: PageProps) {
    const { t } = useTranslation();
    const [q, setQ] = useState(filters.q);
    const [open, setOpen] = useState(false);
    const url = improvementsIndex().url;

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, { q }, { preserveState: true, replace: true });
    }

    function reset() {
        setQ('');
        router.get(url);
    }

    return (
        <>
            <Head title={t('improvements.head_title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('improvements.title')}
                        description={t('improvements.description')}
                    />
                    {can.create && (
                        <Button onClick={() => setOpen(true)}>
                            <Plus />
                            {t('improvements.new')}
                        </Button>
                    )}
                </div>

                <FilterBar
                    searchValue={q}
                    onSearchChange={setQ}
                    searchPlaceholder={t('improvements.search_placeholder')}
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={filters.q !== ''}
                />

                {items.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            {t('improvements.empty')}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                        {statuses.map((status) => {
                            const column = items.filter(
                                (i) => i.status === status,
                            );

                            return (
                                <Card key={status} className="gap-3">
                                    <CardHeader className="flex-row items-center justify-between space-y-0">
                                        <CardTitle className="text-sm">
                                            {improvementStatusLabel(t, status)}
                                        </CardTitle>
                                        <Badge variant="outline">
                                            {column.length}
                                        </Badge>
                                    </CardHeader>
                                    <CardContent className="space-y-2">
                                        {column.length === 0 && (
                                            <p className="text-xs text-muted-foreground">
                                                {t('improvements.column_empty')}
                                            </p>
                                        )}
                                        {column.map((item) => (
                                            <Link
                                                key={item.id}
                                                href={show(item.id).url}
                                                className="block space-y-1.5 rounded-md border p-3 text-sm transition-colors hover:bg-muted/50"
                                            >
                                                <div className="font-medium">
                                                    {item.number && (
                                                        <span className="mr-2 text-xs text-muted-foreground">
                                                            {item.number}
                                                        </span>
                                                    )}
                                                    {item.title}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {item.submitted_by_name}
                                                    {item.department &&
                                                        ` · ${item.department}`}
                                                </div>
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <Badge
                                                        variant="outline"
                                                        className={improvementStatusClassName(
                                                            item.status,
                                                        )}
                                                    >
                                                        {improvementDecisionLabel(
                                                            t,
                                                            item.decision,
                                                        )}
                                                    </Badge>
                                                    {item.submitted_at && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {item.submitted_at}
                                                        </span>
                                                    )}
                                                </div>
                                            </Link>
                                        ))}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>

            {open && (
                <ImprovementDialog
                    open={open}
                    onOpenChange={setOpen}
                    item={null}
                    defaultDepartment={defaults.department}
                />
            )}
        </>
    );
}
