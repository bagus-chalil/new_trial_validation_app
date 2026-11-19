import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

type PaginationFooterProps = {
    url: string;
    query?: Record<string, string | number | undefined>;
    currentPage: number;
    lastPage: number;
    total: number;
    itemLabel: string;
};

/** 1 … 4 5 [6] 7 8 … 20 — always first/last, a window around the current page. */
function pageList(current: number, last: number): (number | 'gap')[] {
    const pages = new Set<number>([1, last]);

    for (let p = current - 2; p <= current + 2; p++) {
        if (p >= 1 && p <= last) {
            pages.add(p);
        }
    }

    const sorted = [...pages].sort((a, b) => a - b);
    const out: (number | 'gap')[] = [];

    sorted.forEach((p, i) => {
        if (i > 0 && p - sorted[i - 1] > 1) {
            out.push('gap');
        }

        out.push(p);
    });

    return out;
}

export function PaginationFooter({
    url,
    query = {},
    currentPage,
    lastPage,
    total,
    itemLabel,
}: PaginationFooterProps) {
    const { t } = useTranslation();

    function goToPage(page: number) {
        router.get(
            url,
            { ...query, page },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <div className="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
            <span>
                {t('common.pagination.summary', {
                    current: currentPage,
                    last: lastPage,
                    total,
                    items: itemLabel,
                })}
            </span>
            {lastPage > 1 && (
                <nav className="flex flex-wrap items-center gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={currentPage <= 1}
                        onClick={() => goToPage(currentPage - 1)}
                    >
                        {t('common.actions.previous')}
                    </Button>
                    {pageList(currentPage, lastPage).map((p, i) =>
                        p === 'gap' ? (
                            <span key={`gap-${i}`} className="px-1">
                                …
                            </span>
                        ) : (
                            <Button
                                key={p}
                                variant={
                                    p === currentPage ? 'default' : 'outline'
                                }
                                size="sm"
                                className="min-w-8"
                                aria-current={
                                    p === currentPage ? 'page' : undefined
                                }
                                onClick={() => p !== currentPage && goToPage(p)}
                            >
                                {p}
                            </Button>
                        ),
                    )}
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={currentPage >= lastPage}
                        onClick={() => goToPage(currentPage + 1)}
                    >
                        {t('common.actions.next')}
                    </Button>
                </nav>
            )}
        </div>
    );
}
