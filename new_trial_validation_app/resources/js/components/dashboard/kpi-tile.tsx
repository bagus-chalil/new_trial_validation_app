import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

type KpiTileProps = {
    label: string;
    value: React.ReactNode;
    caption?: React.ReactNode;
    icon: LucideIcon;
    accent?: boolean;
};

export function KpiTile({
    label,
    value,
    caption,
    icon: Icon,
    accent,
}: KpiTileProps) {
    return (
        <Card
            className={
                'gap-1 py-4' + (accent ? ' border-l-4 border-l-brand' : '')
            }
        >
            <CardContent className="flex items-start justify-between gap-2 px-4">
                <div className="min-w-0">
                    <span className="text-sm text-muted-foreground">
                        {label}
                    </span>
                    <div className="truncate text-2xl font-semibold">
                        {value}
                    </div>
                    {caption ? (
                        <div className="mt-0.5 truncate text-xs text-muted-foreground">
                            {caption}
                        </div>
                    ) : null}
                </div>
                <Icon className="size-5 shrink-0 text-muted-foreground" />
            </CardContent>
        </Card>
    );
}
