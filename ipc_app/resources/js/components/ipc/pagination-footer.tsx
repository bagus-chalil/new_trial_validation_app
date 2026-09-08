import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export function PaginationFooter({ links, lastPage }: { links: PaginationLink[]; lastPage: number }) {
    if (lastPage <= 1) return null;

    return (
        <div className="flex flex-wrap gap-1">
            {links.map((link, i) => (
                <Button
                    key={i}
                    type="button"
                    size="sm"
                    variant={link.active ? 'default' : 'outline'}
                    disabled={!link.url}
                    onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}
