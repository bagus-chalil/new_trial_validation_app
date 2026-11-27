import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/project';

export type FeatureKind = 'improvement' | 'tooling' | 'quotation' | 'po';

export type FeatureRow = {
    id: number;
    title: string;
    subtitle: string;
    status?: string;
    progress?: number;
    extra?: string;
    critical?: boolean;
    amount?: number;
    currency?: string;
    date: string | null;
};

type Props = {
    readonly kind: FeatureKind | null;
    readonly rows: FeatureRow[];
    readonly onClose: () => void;
    readonly hrefFor: (kind: FeatureKind, row: FeatureRow) => string;
    readonly fullHrefFor: (kind: FeatureKind) => string;
    readonly statusLabel: (kind: FeatureKind, status: string) => string;
    readonly statusClassName: (kind: FeatureKind, status: string) => string;
};

/**
 * The old tracker's "summary progress" pop-up behind each dashboard card:
 * every improvement / check tooling / quotation / PO with status, progress
 * and a link to its detail page, filterable by status and searchable.
 */
export function FeatureListDialog({
    kind,
    rows,
    onClose,
    hrefFor,
    fullHrefFor,
    statusLabel,
    statusClassName,
}: Props) {
    const { t, intlLocale } = useTranslation();
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('');

    const statuses = useMemo(() => {
        const counts = new Map<string, number>();
        rows.forEach((r) => {
            if (r.status) {
                counts.set(r.status, (counts.get(r.status) ?? 0) + 1);
            }
        });

        return [...counts.entries()];
    }, [rows]);

    const needle = query.trim().toLowerCase();
    const visible = rows.filter(
        (r) =>
            (status === '' || r.status === status) &&
            (needle === '' ||
                `${r.title} ${r.subtitle} ${r.extra ?? ''}`
                    .toLowerCase()
                    .includes(needle)),
    );

    return (
        <Dialog
            open={kind !== null}
            onOpenChange={(open) => {
                if (!open) {
                    setQuery('');
                    setStatus('');
                    onClose();
                }
            }}
        >
            <DialogContent className="flex max-h-[85vh] flex-col sm:max-w-3xl">
                {kind && (
                    <>
                        <DialogTitle>
                            {t(`projects.dashboard.dialog.title.${kind}`)}
                        </DialogTitle>
                        <DialogDescription>
                            {t('projects.dashboard.dialog.count', {
                                shown: visible.length,
                                total: rows.length,
                            })}
                        </DialogDescription>

                        <div className="relative">
                            <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input
                                className="pl-9"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder={t(
                                    'projects.dashboard.dialog.search',
                                )}
                                aria-label={t(
                                    'projects.dashboard.dialog.search',
                                )}
                            />
                        </div>

                        {statuses.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        status === '' ? 'default' : 'outline'
                                    }
                                    onClick={() => setStatus('')}
                                >
                                    {t('projects.dashboard.dialog.all')} (
                                    {rows.length})
                                </Button>
                                {statuses.map(([s, count]) => (
                                    <Button
                                        key={s}
                                        type="button"
                                        size="sm"
                                        variant={
                                            status === s ? 'default' : 'outline'
                                        }
                                        onClick={() => setStatus(s)}
                                    >
                                        {statusLabel(kind, s)} ({count})
                                    </Button>
                                ))}
                            </div>
                        )}

                        <div className="min-h-0 flex-1 overflow-y-auto rounded-md border">
                            {visible.length === 0 ? (
                                <p className="p-6 text-center text-sm text-muted-foreground">
                                    {t('projects.dashboard.dialog.empty')}
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {visible.map((r) => (
                                        <li key={r.id}>
                                            <Link
                                                href={hrefFor(kind, r)}
                                                className="grid gap-2 p-3 hover:bg-muted/50 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                                            >
                                                <div className="min-w-0">
                                                    <b className="block truncate text-sm">
                                                        {r.title}
                                                        {r.critical && (
                                                            <Badge
                                                                variant="destructive"
                                                                className="ml-2"
                                                            >
                                                                {t(
                                                                    'projects.dashboard.reminders.critical',
                                                                )}
                                                            </Badge>
                                                        )}
                                                    </b>
                                                    <span className="block truncate text-xs text-muted-foreground">
                                                        {r.subtitle || '-'}
                                                        {r.date &&
                                                            ` · ${r.date}`}
                                                        {r.extra &&
                                                            ` · ${r.extra}`}
                                                    </span>
                                                    {r.progress !==
                                                        undefined && (
                                                        <div
                                                            className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted"
                                                            role="progressbar"
                                                            aria-valuenow={
                                                                r.progress
                                                            }
                                                            aria-valuemin={0}
                                                            aria-valuemax={100}
                                                        >
                                                            <div
                                                                className="h-full rounded-full bg-brand"
                                                                style={{
                                                                    width: `${r.progress}%`,
                                                                }}
                                                            />
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-2 sm:justify-end">
                                                    {r.amount !== undefined &&
                                                        r.amount > 0 && (
                                                            <span className="text-sm tabular-nums">
                                                                {formatMoney(
                                                                    r.amount,
                                                                    r.currency ||
                                                                        'IDR',
                                                                    intlLocale,
                                                                )}
                                                            </span>
                                                        )}
                                                    {r.progress !==
                                                        undefined && (
                                                        <span className="w-10 text-right text-xs text-muted-foreground tabular-nums">
                                                            {r.progress}%
                                                        </span>
                                                    )}
                                                    {r.status && (
                                                        <Badge
                                                            variant="outline"
                                                            className={statusClassName(
                                                                kind,
                                                                r.status,
                                                            )}
                                                        >
                                                            {statusLabel(
                                                                kind,
                                                                r.status,
                                                            )}
                                                        </Badge>
                                                    )}
                                                </div>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <div className="flex justify-end">
                            <Button variant="outline" asChild>
                                <Link href={fullHrefFor(kind)}>
                                    {t('projects.dashboard.dialog.open_full')}
                                </Link>
                            </Button>
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
