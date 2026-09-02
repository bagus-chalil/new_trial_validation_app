export const STAGE_LABELS: Record<string, string> = {
    startup: 'Startup Check',
    filling: 'Filling Check',
    packing: 'Packing Check',
    finished: 'Finished Good',
    approval: 'Approval',
    print: 'Print',
    completed: 'Selesai',
};

export function stageLabel(stage: string): string {
    return STAGE_LABELS[stage] ?? stage;
}

// Exact badge colors from the redesign mockup (one per stage, not a generic accent tint).
export const STAGE_BADGE_COLORS: Record<string, { bg: string; fg: string }> = {
    startup: { bg: '#DBEAFE', fg: '#1D4ED8' },
    filling: { bg: '#EDE9FE', fg: '#6D28D9' },
    packing: { bg: '#FFEDD5', fg: '#C2410C' },
    finished: { bg: '#CCFBF1', fg: '#0F766E' },
    approval: { bg: '#FEF3C7', fg: '#B45309' },
    print: { bg: '#E4E4E7', fg: '#3F3F46' },
    completed: { bg: '#DCFCE7', fg: '#15803D' },
};

export function stageBadgeStyle(stage: string): { backgroundColor: string; color: string } {
    const colors = STAGE_BADGE_COLORS[stage] ?? { bg: '#F4F4F5', fg: '#71717A' };
    return { backgroundColor: colors.bg, color: colors.fg };
}
