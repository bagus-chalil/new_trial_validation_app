import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import BotQuestionController from '@/actions/App/Http/Controllers/Admin/BotQuestionController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PaginationFooter } from '@/components/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { trialStatusLabel } from '@/lib/trial-status';
import { index as botQuestionsIndex } from '@/routes/admin/bot-questions';
import type { Paginated } from '@/types';

type View = 'unanswered' | 'downvoted' | 'all' | 'rules';

type BotRow = {
    id: number;
    question?: string;
    locale?: string;
    // "unanswered" view (grouped by question).
    asked_count?: number;
    last_asked_at?: string;
    variants?: { question: string; asked_count: number }[];
    suggestion?: { target: string; clicked: boolean; score: number } | null;
    // "downvoted" / "all" views (one row per interaction).
    intent?: string | null;
    matched?: boolean;
    answer_summary?: string | null;
    feedback?: number | null;
    created_at?: string;
    // "rules" view.
    phrase?: string;
    target?: string;
    status?: string | null;
};

type PageProps = {
    view: View;
    rows: Paginated<BotRow>;
    targets: string[];
    statuses: string[];
    stats: {
        total: number;
        unmatched: number;
        upvoted: number;
        downvoted: number;
    };
};

const VIEWS: View[] = ['unanswered', 'downvoted', 'all', 'rules'];
const ANY_STATUS = '__any';

function normalizePhrase(text: string): string {
    return text.toLowerCase().replace(/[\s?!.]+$/u, '');
}

function TeachFields({
    phrase,
    variants,
    defaultTarget,
    targets,
    statuses,
    errors,
}: {
    phrase: string;
    variants: string[];
    defaultTarget: string;
    targets: string[];
    statuses: string[];
    errors: Record<string, string>;
}) {
    const { t } = useTranslation();
    const [target, setTarget] = useState(defaultTarget);
    const [status, setStatus] = useState(ANY_STATUS);

    return (
        <div className="mt-4 space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="alias-phrase">
                    {t('admin.bot_questions.dialog.phrase')}
                </Label>
                <Input
                    id="alias-phrase"
                    name="phrase"
                    defaultValue={phrase}
                    maxLength={200}
                />
                <p className="text-xs text-muted-foreground">
                    {t('admin.bot_questions.dialog.phrase_hint')}
                </p>
                <InputError message={errors.phrase} />
            </div>

            <div className="grid gap-2">
                <Label>{t('admin.bot_questions.dialog.meaning')}</Label>
                <Select name="target" value={target} onValueChange={setTarget}>
                    <SelectTrigger className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {[...targets, 'ignore'].map((option) => (
                            <SelectItem key={option} value={option}>
                                {t(`admin.bot_questions.targets.${option}`)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.target} />
            </div>

            {variants.length > 0 && (
                <div className="grid gap-2">
                    <Label>{t('admin.bot_questions.suggestion_also')}</Label>
                    <p className="text-xs text-muted-foreground">
                        {t('admin.bot_questions.suggestion_variants_hint')}
                    </p>
                    {variants.map((variant) => (
                        <label
                            key={variant}
                            className="flex items-start gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                name="also[]"
                                value={variant}
                                defaultChecked
                                className="mt-1"
                            />
                            <span>{variant}</span>
                        </label>
                    ))}
                </div>
            )}

            {target === 'trials_by_status' && (
                <div className="grid gap-2">
                    <Label>{t('admin.bot_questions.dialog.status')}</Label>
                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY_STATUS}>
                                {t('admin.bot_questions.dialog.status_any')}
                            </SelectItem>
                            {statuses.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {trialStatusLabel(t, option)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {status !== ANY_STATUS && (
                        <input type="hidden" name="status" value={status} />
                    )}
                    <InputError message={errors.status} />
                </div>
            )}
        </div>
    );
}

export default function AdminBotQuestionsIndex({
    view,
    rows,
    targets,
    statuses,
    stats,
}: PageProps) {
    const { t, formatDate } = useTranslation();
    const grouped = view === 'unanswered';
    const rules = view === 'rules';

    const statCards: { key: keyof PageProps['stats']; label: string }[] = [
        { key: 'total', label: t('admin.bot_questions.stats.total') },
        { key: 'unmatched', label: t('admin.bot_questions.stats.unmatched') },
        { key: 'upvoted', label: t('admin.bot_questions.stats.upvoted') },
        { key: 'downvoted', label: t('admin.bot_questions.stats.downvoted') },
    ];

    const columnCount = rules ? 4 : 5;

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
                                        {t(
                                            rules
                                                ? 'admin.bot_questions.dialog.phrase'
                                                : 'admin.bot_questions.question',
                                        )}
                                    </TableHead>
                                    {rules && (
                                        <>
                                            <TableHead>
                                                {t(
                                                    'admin.bot_questions.rule_target',
                                                )}
                                            </TableHead>
                                            <TableHead>
                                                {t(
                                                    'admin.bot_questions.rule_status',
                                                )}
                                            </TableHead>
                                        </>
                                    )}
                                    {grouped && (
                                        <TableHead>
                                            {t(
                                                'admin.bot_questions.asked_count',
                                            )}
                                        </TableHead>
                                    )}
                                    {!grouped && !rules && (
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
                                    {!rules && (
                                        <>
                                            <TableHead>
                                                {t(
                                                    'admin.bot_questions.locale',
                                                )}
                                            </TableHead>
                                            <TableHead>
                                                {t(
                                                    'admin.bot_questions.last_asked',
                                                )}
                                            </TableHead>
                                        </>
                                    )}
                                    {(grouped || rules) && (
                                        <TableHead>
                                            {t('admin.bot_questions.action')}
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="whitespace-normal">
                                            <div>
                                                {rules
                                                    ? row.phrase
                                                    : row.question}
                                            </div>
                                            {grouped &&
                                                (row.variants ?? []).length >
                                                    0 && (
                                                    <details className="text-xs text-muted-foreground">
                                                        <summary className="cursor-pointer">
                                                            {t(
                                                                'admin.bot_questions.suggestion_variants',
                                                            )}{' '}
                                                            (
                                                            {
                                                                row.variants
                                                                    ?.length
                                                            }
                                                            )
                                                        </summary>
                                                        <ul className="mt-1 list-disc pl-4">
                                                            {row.variants?.map(
                                                                (variant) => (
                                                                    <li
                                                                        key={
                                                                            variant.question
                                                                        }
                                                                    >
                                                                        {
                                                                            variant.question
                                                                        }{' '}
                                                                        ×
                                                                        {
                                                                            variant.asked_count
                                                                        }
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    </details>
                                                )}
                                            {grouped && row.suggestion && (
                                                <Form
                                                    {...BotQuestionController.storeAlias.form()}
                                                    className="mt-1 flex flex-wrap items-center gap-2 text-xs"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="phrase"
                                                        value={normalizePhrase(
                                                            row.question ?? '',
                                                        )}
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="target"
                                                        value={
                                                            row.suggestion
                                                                .target
                                                        }
                                                    />
                                                    {(row.variants ?? []).map(
                                                        (variant) => (
                                                            <input
                                                                key={
                                                                    variant.question
                                                                }
                                                                type="hidden"
                                                                name="also[]"
                                                                value={
                                                                    variant.question
                                                                }
                                                            />
                                                        ),
                                                    )}
                                                    <span className="text-muted-foreground">
                                                        {t(
                                                            'admin.bot_questions.suggestion_suggest',
                                                        )}
                                                        :
                                                    </span>
                                                    <Badge variant="outline">
                                                        {t(
                                                            `admin.bot_questions.targets.${row.suggestion.target}`,
                                                        )}
                                                    </Badge>
                                                    {row.suggestion.clicked && (
                                                        <Badge>
                                                            {t(
                                                                'admin.bot_questions.suggestion_clicked',
                                                            )}
                                                        </Badge>
                                                    )}
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        {t(
                                                            'admin.bot_questions.suggestion_agree',
                                                        )}
                                                    </Button>
                                                </Form>
                                            )}
                                            {!grouped &&
                                                !rules &&
                                                row.answer_summary && (
                                                    <div className="text-muted-foreground">
                                                        {row.answer_summary}
                                                    </div>
                                                )}
                                        </TableCell>

                                        {rules && (
                                            <>
                                                <TableCell>
                                                    {t(
                                                        `admin.bot_questions.targets.${row.target}`,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {row.status
                                                        ? trialStatusLabel(
                                                              t,
                                                              row.status,
                                                          )
                                                        : '-'}
                                                </TableCell>
                                            </>
                                        )}

                                        {grouped && (
                                            <TableCell>
                                                {row.asked_count}
                                            </TableCell>
                                        )}

                                        {!grouped && !rules && (
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

                                        {!rules && (
                                            <>
                                                <TableCell>
                                                    {(
                                                        row.locale ?? ''
                                                    ).toUpperCase()}
                                                </TableCell>
                                                <TableCell>
                                                    {formatDate(
                                                        (grouped
                                                            ? row.last_asked_at
                                                            : row.created_at) ??
                                                            '',
                                                        true,
                                                    )}
                                                </TableCell>
                                            </>
                                        )}

                                        {grouped && (
                                            <TableCell className="space-x-2 whitespace-nowrap">
                                                <ConfirmDialog
                                                    trigger={
                                                        <Button size="sm">
                                                            {t(
                                                                'admin.bot_questions.answer',
                                                            )}
                                                        </Button>
                                                    }
                                                    title={t(
                                                        'admin.bot_questions.dialog.title',
                                                    )}
                                                    description={t(
                                                        'admin.bot_questions.dialog.description',
                                                    )}
                                                    confirmLabel={t(
                                                        'admin.bot_questions.dialog.save',
                                                    )}
                                                    confirmVariant="default"
                                                    formProps={BotQuestionController.storeAlias.form()}
                                                >
                                                    {({ errors }) => (
                                                        <TeachFields
                                                            phrase={normalizePhrase(
                                                                row.question ??
                                                                    '',
                                                            )}
                                                            variants={(
                                                                row.variants ??
                                                                []
                                                            ).map(
                                                                (variant) =>
                                                                    variant.question,
                                                            )}
                                                            defaultTarget={
                                                                row.suggestion
                                                                    ?.target ??
                                                                'trials_by_status'
                                                            }
                                                            targets={targets}
                                                            statuses={statuses}
                                                            errors={errors}
                                                        />
                                                    )}
                                                </ConfirmDialog>
                                                <ConfirmDialog
                                                    trigger={
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            {t(
                                                                'admin.bot_questions.ignore',
                                                            )}
                                                        </Button>
                                                    }
                                                    title={t(
                                                        'admin.bot_questions.ignore_dialog.title',
                                                    )}
                                                    description={t(
                                                        'admin.bot_questions.ignore_dialog.description',
                                                    )}
                                                    confirmLabel={t(
                                                        'admin.bot_questions.ignore',
                                                    )}
                                                    confirmVariant="default"
                                                    formProps={BotQuestionController.storeAlias.form()}
                                                >
                                                    {() => (
                                                        <>
                                                            <input
                                                                type="hidden"
                                                                name="phrase"
                                                                value={
                                                                    row.question ??
                                                                    ''
                                                                }
                                                            />
                                                            <input
                                                                type="hidden"
                                                                name="target"
                                                                value="ignore"
                                                            />
                                                        </>
                                                    )}
                                                </ConfirmDialog>
                                            </TableCell>
                                        )}

                                        {rules && (
                                            <TableCell>
                                                <ConfirmDialog
                                                    trigger={
                                                        <Button
                                                            size="sm"
                                                            variant="destructive"
                                                        >
                                                            {t(
                                                                'admin.actions.delete',
                                                            )}
                                                        </Button>
                                                    }
                                                    title={t(
                                                        'admin.bot_questions.delete_title',
                                                    )}
                                                    description={t(
                                                        'admin.bot_questions.delete_description',
                                                        {
                                                            phrase:
                                                                row.phrase ??
                                                                '',
                                                        },
                                                    )}
                                                    confirmLabel={t(
                                                        'admin.actions.delete',
                                                    )}
                                                    formProps={BotQuestionController.destroyAlias.form(
                                                        row.id,
                                                    )}
                                                />
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                                {rows.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={columnCount}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t(
                                                rules
                                                    ? 'admin.bot_questions.rules_empty'
                                                    : 'admin.bot_questions.empty',
                                            )}
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
