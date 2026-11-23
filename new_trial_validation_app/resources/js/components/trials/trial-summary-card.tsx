import { LoaderCircle, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';

export type TrialSummaryPoint = { tone: 'finding' | 'info'; text: string };

export type TrialSummary = {
    points: TrialSummaryPoint[];
    aiEnabled: boolean;
};

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Approver / reviewer digest of one trial. The rule-based points are computed on the server from the
 * recorded data and are always shown; "Write AI summary" asks the Vision Service to word the same
 * points as a short paragraph (in the user's UI language). Advisory only: it never recommends a
 * decision and nothing here changes data.
 */
export function TrialSummaryCard({
    trialId,
    summary,
}: {
    trialId: number;
    summary: TrialSummary;
}) {
    const { t } = useTranslation();
    const [state, setState] = useState<{
        loading: boolean;
        text?: string;
        error?: string;
    }>({ loading: false });

    const run = async () => {
        setState({ loading: true });

        try {
            const response = await fetch(`/trials/${trialId}/ai-summary`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
            });
            const data = (await response.json().catch(() => ({}))) as {
                ok?: boolean;
                text?: string;
                error?: string;
            };

            setState(
                data.ok && data.text
                    ? { loading: false, text: data.text }
                    : {
                          loading: false,
                          error: data.error ?? t('insight.errors.failed'),
                      },
            );
        } catch {
            setState({ loading: false, error: t('insight.errors.network') });
        }
    };

    return (
        <Card className="print:hidden">
            <CardHeader className="flex flex-row flex-wrap items-start gap-3">
                <div className="min-w-0 flex-1 space-y-1">
                    <CardTitle className="text-base">
                        {t('insight.title')}
                    </CardTitle>
                    <p className="text-xs text-muted-foreground">
                        {t('insight.subtitle')}
                    </p>
                </div>
                {summary.aiEnabled && (
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => void run()}
                        disabled={state.loading}
                        className="gap-1.5"
                    >
                        {state.loading ? (
                            <LoaderCircle className="size-3.5 animate-spin" />
                        ) : (
                            <Sparkles className="size-3.5" />
                        )}
                        {state.loading
                            ? t('insight.button_busy')
                            : t('insight.button')}
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-3">
                {state.loading && (
                    <p className="text-xs text-muted-foreground">
                        {t('insight.wait_hint')}
                    </p>
                )}
                {state.error && (
                    <p className="text-sm font-medium text-red-600">
                        {state.error}
                    </p>
                )}
                {state.text && (
                    <div className="rounded-xl bg-primary/[0.06] p-3.5">
                        <p className="mb-1 flex items-center gap-1.5 text-[10.5px] font-bold tracking-wide text-primary uppercase">
                            <Sparkles className="size-3" />{' '}
                            {t('insight.ai_label')}
                        </p>
                        <p className="text-sm leading-relaxed whitespace-pre-wrap">
                            {state.text}
                        </p>
                    </div>
                )}

                <ul className="flex list-disc flex-col gap-1.5 pl-5 text-sm leading-relaxed">
                    {summary.points.map((point) => (
                        <li
                            key={point.text}
                            className={
                                point.tone === 'finding'
                                    ? 'font-medium text-red-700 dark:text-red-400'
                                    : undefined
                            }
                        >
                            {point.text}
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
