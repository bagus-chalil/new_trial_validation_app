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
        <div className="flex items-center justify-between text-sm text-muted-foreground">
            <span>
                {t('common.pagination.summary', {
                    current: currentPage,
                    last: lastPage,
                    total,
                    items: itemLabel,
                })}
            </span>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={currentPage <= 1}
                    onClick={() => goToPage(currentPage - 1)}
                >
                    {t('common.actions.previous')}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={currentPage >= lastPage}
                    onClick={() => goToPage(currentPage + 1)}
                >
                    {t('common.actions.next')}
                </Button>
            </div>
        </div>
    );
}
