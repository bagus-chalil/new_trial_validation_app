import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { PaginationFooter } from '@/components/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { index as botQuestionsIndex } from '@/routes/admin/bot-questions';
import type { Paginated } from '@/types';

type View = 'unanswered' | 'downvoted' | 'all';

type BotRow = {
    id: number;
    question: string;
    locale: string;
    // Present on the "unanswered" view (grouped by question).
    asked_count?: number;
    last_asked_at?: string;
    // Present on the "downvoted" / "all" views (one row per interaction).
    intent?: string | null;
    matched?: boolean;
    answer_summary?: string | null;
    feedback?: number | null;
    created_at?: string;
};

type PageProps = {
    view: View;
    rows: Paginated<BotRow>;
    stats: {
        total: number;
        unmatched: number;
        upvoted: number;
        downvoted: number;
    };
};

const VIEWS: View[] = ['unanswered', 'downvoted', 'all'];

export default function AdminBotQuestionsIndex({
    view,
    rows,
    stats,
}: PageProps) {
    const { t, formatDate } = useTranslation();
    const grouped = view === 'unanswered';

    const statCards: { key: keyof PageProps['stats']; label: string }[] = [
        { key: 'total', label: t('admin.bot_questions.stats.total') },
        { key: 'unmatched', label: t('admin.bot_questions.stats.unmatched') },
        { key: 'upvoted', label: t('admin.bot_questions.stats.upvoted') },
        { key: 'downvoted', label: t('admin.bot_questions.stats.downvoted') },
    ];

    return (
        <>
            <Head title={t('admin.bot_questions.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('admin.bot_questions.heading')}
                    description={t('admin.bot_questions.description')}
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {statCards.map((card) => (
                        <Card key={card.key}>
                            <CardContent className="space-y-1">
                                <div className="text-sm text-muted-foreground">
                                    {card.label}
                                </div>
                                <div className="text-2xl font-semibold">
                                    {stats[card.key]}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap gap-2">
                            {VIEWS.map((option) => (
                                <Button
                                    key={option}
                                    asChild
                                    size="sm"
                                    variant={
                                        option === view ? 'default' : 'outline'
                                    }
                                >
                                    <Link
                                        href={botQuestionsIndex({
                                            query: { view: option },
                                        })}
                                    >
                                        {t(
                                            `admin.bot_questions.views.${option}`,
                                        )}
                                    </Link>
                                </Button>
                            ))}
                        </div>

                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('admin.bot_questions.question')}
                                    </TableHead>
                                    {grouped ? (
                                        <TableHead>
                                            {t(
                                                'admin.bot_questions.asked_count',
                                            )}
                                        </TableHead>
                                    ) : (
                                        <>
                                            <TableHead>
                                                {t(
                                                    'admin.bot_questions.intent',
                                                )}
                                            </TableHead>
                                            <TableHead>
                                                {t(
                                                    'admin.bot_questions.status',
                                                )}
                                            </TableHead>
                                        </>
                                    )}
                                    <TableHead>
                                        {t('admin.bot_questions.locale')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.bot_questions.last_asked')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="whitespace-normal">
                                            <div>{row.question}</div>
                                            {!grouped && row.answer_summary && (
                                                <div className="text-muted-foreground">
                                                    {row.answer_summary}
                                                </div>
                                            )}
                                        </TableCell>
                                        {grouped ? (
                                            <TableCell>
                                                {row.asked_count}
                                            </TableCell>
                                        ) : (
                                            <>
                                                <TableCell>
                                                    {row.intent ?? '-'}
                                                </TableCell>
                                                <TableCell className="space-x-1">
                                                    {row.matched === false && (
                                                        <Badge variant="outline">
                                                            {t(
                                                                'admin.bot_questions.unmatched',
                                                            )}
                                                        </Badge>
                                                    )}
                                                    {row.feedback === 1 && (
                                                        <Badge variant="outline">
                                                            {t(
                                                                'admin.bot_questions.rated_up',
                                                            )}
                                                        </Badge>
                                                    )}
                                                    {row.feedback === -1 && (
                                                        <Badge variant="destructive">
                                                            {t(
                                                                'admin.bot_questions.rated_down',
                                                            )}
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                            </>
                                        )}
                                        <TableCell>
                                            {row.locale.toUpperCase()}
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(
                                                (grouped
                                                    ? row.last_asked_at
                                                    : row.created_at) ?? '',
                                                true,
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {rows.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={grouped ? 4 : 5}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('admin.bot_questions.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={botQuestionsIndex().url}
                            query={{ view }}
                            currentPage={rows.current_page}
                            lastPage={rows.last_page}
                            total={rows.total}
                            itemLabel={t('admin.bot_questions.items')}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminBotQuestionsIndex.layout = {
    breadcrumbs: [{ title: 'Bot Questions', href: botQuestionsIndex() }],
};
