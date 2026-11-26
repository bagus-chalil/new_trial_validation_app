import { Check, ChevronDown, Clock, PenLine, Undo2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type ImSignoff = {
    id: number;
    round: number;
    stage: string;
    role_label: string;
    user_name: string;
    status: string;
    comment: string | null;
    signed_at: string | null;
};

type Props = {
    signoffs: ImSignoff[];
    currentRound: number;
    /** Sign-off rows the viewer may sign right now (review or knowledge). */
    actionableIds: number[];
};

const STAGES = ['prepared', 'review', 'knowledge', 'approved'] as const;

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    return (parts[0][0] + (parts[1]?.[0] ?? '')).toUpperCase();
}

function StatusIcon({ status }: { status: string }) {
    if (status === 'Signed') {
        return <Check className="size-3.5" />;
    }

    if (status === 'Returned') {
        return <Undo2 className="size-3.5" />;
    }

    return <Clock className="size-3.5" />;
}

const STATUS_TONE: Record<string, string> = {
    Signed: 'bg-emerald-600 text-white',
    Returned: 'bg-amber-500 text-white',
    Pending: 'bg-muted text-muted-foreground',
};

/**
 * Visual QA sign-off sheet: overall progress, one column per stage of the paper
 * IM (Prepared / Review / Knowledge / Approved), a highlighted "your turn" row
 * and click-to-expand comments. Earlier rounds are folded behind a toggle.
 */
export function ImSignoffTimeline({
    signoffs,
    currentRound,
    actionableIds,
}: Props) {
    const { t } = useTranslation();
    const [showHistory, setShowHistory] = useState(false);
    const [open, setOpen] = useState<number | null>(null);

    const current = useMemo(
        () => signoffs.filter((s) => s.round === currentRound),
        [signoffs, currentRound],
    );
    const older = useMemo(
        () => signoffs.filter((s) => s.round !== currentRound),
        [signoffs, currentRound],
    );

    // Knowledge rows are informative, so they do not count toward progress.
    const counted = current.filter((s) => s.stage !== 'knowledge');
    const done = counted.filter((s) => s.status === 'Signed').length;
    const pct =
        counted.length === 0 ? 0 : Math.round((done / counted.length) * 100);

    if (signoffs.length === 0) {
        return null;
    }

    const renderRow = (s: ImSignoff) => {
        const mine = actionableIds.includes(s.id);
        const expanded = open === s.id;
        const signed = s.status === 'Signed';

        return (
            <li key={s.id}>
                <button
                    type="button"
                    onClick={() => setOpen(expanded ? null : s.id)}
                    aria-expanded={expanded}
                    className={cn(
                        'flex w-full items-center gap-2 rounded-lg border p-2 text-left transition hover:bg-muted/50',
                        mine && 'border-brand ring-1 ring-brand/40',
                    )}
                >
                    <span
                        className={cn(
                            'flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                            signed
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
                                : 'bg-muted text-muted-foreground',
                        )}
                    >
                        {signed ? initials(s.user_name) : '·'}
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-medium">
                            {s.user_name || (
                                <span className="text-muted-foreground">
                                    {t('im_master.chain.waiting_team', {
                                        team: s.role_label,
                                    })}
                                </span>
                            )}
                        </span>
                        <span className="block truncate text-xs text-muted-foreground">
                            {s.role_label}
                            {s.signed_at ? ` · ${s.signed_at}` : ''}
                        </span>
                    </span>
                    {mine && (
                        <Badge className="gap-1 bg-brand text-white">
                            <PenLine className="size-3" />
                            {t('im_master.chain.sign_here')}
                        </Badge>
                    )}
                    <span
                        className={cn(
                            'flex size-6 shrink-0 items-center justify-center rounded-full',
                            STATUS_TONE[s.status] ?? STATUS_TONE.Pending,
                        )}
                        title={t(`im_master.chain.status.${s.status}`)}
                    >
                        <StatusIcon status={s.status} />
                    </span>
                    {s.comment && (
                        <ChevronDown
                            className={cn(
                                'size-4 shrink-0 text-muted-foreground transition-transform',
                                expanded && 'rotate-180',
                            )}
                        />
                    )}
                </button>
                {expanded && s.comment && (
                    <p className="mt-1 ml-10 rounded-md bg-muted/60 p-2 text-xs whitespace-pre-line">
                        {s.comment}
                    </p>
                )}
            </li>
        );
    };

    const grid = (rows: ImSignoff[]) => (
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            {STAGES.map((stage) => {
                const items = rows.filter((s) => s.stage === stage);

                return (
                    <section key={stage} className="space-y-2">
                        <h4 className="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {t(`im_master.chain.${stage}`)}
                            {items.length > 0 &&
                                items.every((s) => s.status === 'Signed') && (
                                    <Check className="size-3.5 text-emerald-600" />
                                )}
                        </h4>
                        {items.length === 0 ? (
                            <p className="rounded-lg border border-dashed p-2 text-xs text-muted-foreground">
                                —
                            </p>
                        ) : (
                            <ul className="space-y-1.5">
                                {items.map(renderRow)}
                            </ul>
                        )}
                    </section>
                );
            })}
        </div>
    );

    return (
        <div className="space-y-4">
            <div className="space-y-1.5">
                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        {t('im_master.chain.progress', {
                            done: String(done),
                            total: String(counted.length),
                        })}
                    </span>
                    <span>{pct}%</span>
                </div>
                <div
                    className="h-2 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    aria-valuenow={pct}
                    aria-valuemin={0}
                    aria-valuemax={100}
                >
                    <div
                        className="h-full rounded-full bg-emerald-600 transition-all duration-500"
                        style={{ width: `${pct}%` }}
                    />
                </div>
            </div>

            {grid(current)}

            {current.some((s) => s.stage === 'knowledge') && (
                <p className="text-xs text-muted-foreground">
                    {t('im_master.chain.knowledge_hint')}
                </p>
            )}

            {older.length > 0 && (
                <div>
                    <button
                        type="button"
                        onClick={() => setShowHistory(!showHistory)}
                        className="text-xs text-muted-foreground underline underline-offset-2"
                    >
                        {showHistory ? '▾' : '▸'} #{currentRound - 1}…
                    </button>
                    {showHistory && (
                        <div className="mt-2 opacity-70">{grid(older)}</div>
                    )}
                </div>
            )}
        </div>
    );
}
