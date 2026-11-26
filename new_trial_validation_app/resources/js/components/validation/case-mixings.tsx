import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { mixingStatusClassName, mixingUrl } from '@/lib/validation-steps';

export type CaseMixing = {
    id: number;
    mixing_code: string;
    batch_no: string;
    mixing_date: string | null;
    progress_status: string;
    batch_record_revision: string;
};

export function CaseMixings({
    caseId,
    mixings,
    canCreate,
}: {
    caseId: number;
    mixings: CaseMixing[];
    canCreate: boolean;
}) {
    const { t } = useTranslation();

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-2">
                <CardTitle>{t('mixing.journey.title')}</CardTitle>
                {canCreate && (
                    <Button size="sm" asChild>
                        <Link href={`${mixingUrl.create()}?case=${caseId}`}>
                            {t('mixing.journey.create')}
                        </Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {mixings.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('mixing.journey.empty')}
                    </p>
                ) : (
                    <ul className="divide-y text-sm">
                        {mixings.map((m) => (
                            <li
                                key={m.id}
                                className="flex flex-wrap items-center justify-between gap-2 py-2"
                            >
                                <Link
                                    href={mixingUrl.show(m.id)}
                                    className="font-medium underline-offset-4 hover:underline"
                                >
                                    {m.mixing_code}
                                </Link>
                                <span className="text-muted-foreground">
                                    {m.batch_no}
                                    {m.mixing_date ? ` · ${m.mixing_date}` : ''}
                                    {m.batch_record_revision
                                        ? ` · ${t('mixing.journey.revision')} ${m.batch_record_revision}`
                                        : ''}
                                </span>
                                <Badge
                                    className={mixingStatusClassName(
                                        m.progress_status,
                                    )}
                                >
                                    {t(`mixing.status.${m.progress_status}`)}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
