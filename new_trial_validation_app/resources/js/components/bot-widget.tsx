import { Link } from '@inertiajs/react';
import { Bot, Send, ThumbsDown, ThumbsUp, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import TrialReportController from '@/actions/App/Http/Controllers/TrialReportController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type BotReply = {
    id: number;
    intent: string;
    text: string;
    trials: { id: number; trial_code: string }[];
    suggestions: string[];
};

type Rating = 1 | -1 | null;

type Message =
    | { role: 'user'; text: string }
    | { role: 'bot'; reply: BotReply; rated: Rating }
    | { role: 'error'; text: string };

const EXAMPLE_KEYS = [
    'my_queue',
    'trials_by_status',
    'stuck_trials',
    'not_ok_hotspots',
];

function xsrfToken(): string {
    const match = /(?:^|; )XSRF-TOKEN=([^;]+)/.exec(document.cookie);

    return match ? decodeURIComponent(match[1]) : '';
}

async function post<T>(url: string, body: unknown): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(String(response.status));
    }

    return (await response.json()) as T;
}

function SuggestionList({
    title,
    items,
    onPick,
}: {
    readonly title: string;
    readonly items: string[];
    readonly onPick: (text: string) => void;
}) {
    return (
        <div className="space-y-1">
            <p className="text-xs text-muted-foreground">{title}</p>
            {items.map((s) => (
                <button
                    key={s}
                    type="button"
                    onClick={() => onPick(s)}
                    className="block w-full rounded-md border px-2 py-1 text-left text-xs hover:bg-muted"
                >
                    {s}
                </button>
            ))}
        </div>
    );
}

export function BotWidget() {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [question, setQuestion] = useState('');
    const [busy, setBusy] = useState(false);
    const [messages, setMessages] = useState<Message[]>([]);
    const endRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        endRef.current?.scrollIntoView({ block: 'end' });
    }, [messages, open]);

    function setRating(index: number, rated: Rating) {
        setMessages((m) =>
            m.map((msg, i) =>
                i === index && msg.role === 'bot' ? { ...msg, rated } : msg,
            ),
        );
    }

    async function ask(text: string, from?: number) {
        const trimmed = text.trim();

        if (!trimmed || busy) {
            return;
        }

        setQuestion('');
        setBusy(true);
        setMessages((m) => [...m, { role: 'user', text: trimmed }]);

        try {
            const reply = await post<BotReply>('/bot/ask', {
                question: trimmed,
                ...(from ? { from } : {}),
            });
            setMessages((m) => [...m, { role: 'bot', reply, rated: null }]);
        } catch {
            setMessages((m) => [
                ...m,
                { role: 'error', text: t('ai.widget.error') },
            ]);
        } finally {
            setBusy(false);
        }
    }

    async function rate(index: number, id: number, feedback: 1 | -1) {
        setRating(index, feedback);

        try {
            await post(`/bot/${id}/rate`, { feedback });
        } catch {
            setRating(index, null);
        }
    }

    if (!open) {
        return (
            <Button
                type="button"
                size="icon"
                aria-label={t('ai.widget.open')}
                title={t('ai.widget.open')}
                onClick={() => setOpen(true)}
                className="fixed right-4 bottom-4 z-40 size-12 rounded-full bg-brand text-white shadow-lg hover:bg-brand/90 print:hidden"
            >
                <Bot className="size-6" />
            </Button>
        );
    }

    return (
        <section
            aria-label={t('ai.title')}
            className="fixed right-4 bottom-4 z-40 flex h-[32rem] max-h-[80vh] w-[22rem] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-xl border bg-background shadow-xl print:hidden"
        >
            <header className="flex items-center gap-2 bg-brand px-3 py-2 text-white">
                <Bot className="size-4" />
                <h2 className="flex-1 text-sm font-semibold">
                    {t('ai.title')}
                </h2>
                <button
                    type="button"
                    aria-label={t('ai.widget.close')}
                    onClick={() => setOpen(false)}
                    className="rounded p-1 hover:bg-white/20"
                >
                    <X className="size-4" />
                </button>
            </header>

            <div className="flex-1 space-y-3 overflow-y-auto p-3 text-sm">
                {messages.length === 0 && (
                    <>
                        <p className="text-muted-foreground">
                            {t('ai.widget.greeting')}
                        </p>
                        <SuggestionList
                            title={t('ai.widget.try_asking')}
                            items={EXAMPLE_KEYS.map((k) =>
                                t(`ai.examples.${k}`),
                            )}
                            onPick={(s) => void ask(s)}
                        />
                    </>
                )}

                {messages.map((msg, i) => {
                    if (msg.role === 'user') {
                        return (
                            <div key={i} className="flex justify-end">
                                <p className="max-w-[85%] rounded-lg bg-brand px-3 py-2 text-white">
                                    {msg.text}
                                </p>
                            </div>
                        );
                    }

                    if (msg.role === 'error') {
                        return (
                            <p key={i} className="text-destructive">
                                {msg.text}
                            </p>
                        );
                    }

                    const { reply, rated } = msg;

                    return (
                        <div key={i} className="space-y-2">
                            <p className="rounded-lg bg-muted px-3 py-2 whitespace-pre-line">
                                {reply.text}
                            </p>

                            {reply.trials.length > 0 && (
                                <ul className="flex flex-wrap gap-1.5">
                                    {reply.trials.map((trial) => (
                                        <li key={trial.id}>
                                            <Link
                                                href={
                                                    TrialReportController.show(
                                                        trial.id,
                                                    ).url
                                                }
                                                title={t(
                                                    'ai.widget.open_trial',
                                                )}
                                                className="rounded-full border px-2 py-0.5 text-xs font-medium hover:bg-muted"
                                            >
                                                {trial.trial_code}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {reply.suggestions.length > 0 && (
                                <SuggestionList
                                    title={t('ai.widget.try_asking')}
                                    items={reply.suggestions}
                                    onPick={(s) =>
                                        void ask(
                                            s,
                                            reply.intent === 'unknown'
                                                ? reply.id
                                                : undefined,
                                        )
                                    }
                                />
                            )}

                            <div className="flex items-center gap-1">
                                {([1, -1] as const).map((value) => {
                                    const label = t(
                                        value === 1
                                            ? 'ai.widget.helpful'
                                            : 'ai.widget.not_helpful',
                                    );

                                    return (
                                        <button
                                            key={value}
                                            type="button"
                                            disabled={rated !== null}
                                            aria-pressed={rated === value}
                                            aria-label={label}
                                            title={label}
                                            onClick={() =>
                                                void rate(i, reply.id, value)
                                            }
                                            className={cn(
                                                'rounded p-1 text-muted-foreground hover:bg-muted disabled:cursor-default disabled:hover:bg-transparent',
                                                rated === value &&
                                                    'text-foreground',
                                                rated !== null &&
                                                    rated !== value &&
                                                    'opacity-30',
                                            )}
                                        >
                                            {value === 1 ? (
                                                <ThumbsUp className="size-3.5" />
                                            ) : (
                                                <ThumbsDown className="size-3.5" />
                                            )}
                                        </button>
                                    );
                                })}
                                {rated !== null && (
                                    <span className="text-xs text-muted-foreground">
                                        {t('ai.widget.thanks')}
                                    </span>
                                )}
                            </div>
                        </div>
                    );
                })}
                <div ref={endRef} />
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    void ask(question);
                }}
                className="flex items-center gap-2 border-t p-2"
            >
                <Input
                    value={question}
                    maxLength={500}
                    placeholder={t('ai.widget.placeholder')}
                    onChange={(e) => setQuestion(e.target.value)}
                />
                <Button
                    type="submit"
                    size="icon"
                    disabled={busy || question.trim() === ''}
                    aria-label={t('ai.widget.send')}
                >
                    <Send className="size-4" />
                </Button>
            </form>
        </section>
    );
}
