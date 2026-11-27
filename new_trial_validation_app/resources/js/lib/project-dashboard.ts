import type { TranslateFn } from '@/hooks/use-translation';

/** Shared by the Project dashboard page and the overview on the main Dashboard. */
export type ProjectRef = {
    id: number;
    code: string;
    name: string;
    pic_name: string;
};

export type AttentionItem = {
    project: ProjectRef;
    sev: number;
    tag:
        | 'late'
        | 'at_risk'
        | 'on_hold'
        | 'deadline'
        | 'late_stage'
        | 'late_start';
    days: number | null;
    progress: number;
    planned: number;
    items: string[];
};

export type ProjectKpi = {
    total: number;
    on_progress: number;
    completed: number;
    delayed: number;
    on_hold: number;
    not_started: number;
    avg_progress: number;
};

export const ATTENTION_TAG_COLOR: Record<AttentionItem['tag'], string> = {
    late: 'bg-red-600',
    at_risk: 'bg-orange-500',
    on_hold: 'bg-amber-500',
    deadline: 'bg-sky-600',
    late_stage: 'bg-red-500',
    late_start: 'bg-orange-500',
};

export function attentionText(t: TranslateFn, a: AttentionItem): string {
    const base = {
        days: a.days ?? 0,
        progress: a.progress,
        planned: a.planned,
        items: a.items.join(', '),
    };

    if (a.tag === 'late' && a.days === null) {
        return t('projects.dashboard.attention.text.late_flagged', base);
    }

    return t(`projects.dashboard.attention.text.${a.tag}`, base);
}
