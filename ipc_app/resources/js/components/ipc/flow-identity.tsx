import { cn } from '@/lib/utils';
import { Droplets, Layers3, PackageCheck } from 'lucide-react';
import { type CSSProperties } from 'react';

const FLOWS = {
    filling: {
        label: 'Filling saja',
        description: 'Pemeriksaan filling tanpa Packing dan Finished Good.',
        color: '#0f766e',
        darkColor: '#2dd4bf',
        badge: 'border-teal-200 bg-teal-50 text-teal-800 dark:border-teal-800 dark:bg-teal-950 dark:text-teal-200',
        icon: Droplets,
    },
    packing_fg: {
        label: 'Packing + FG',
        description: 'Pemeriksaan packing dan produk jadi, tanpa Filling Check.',
        color: '#7c3aed',
        darkColor: '#a78bfa',
        badge: 'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-800 dark:bg-violet-950 dark:text-violet-200',
        icon: PackageCheck,
    },
    full: {
        label: 'Fillpack + FG',
        description: 'Alur lengkap: Filling, Packing, dan Finished Good.',
        color: '#2f6fed',
        darkColor: '#60a5fa',
        badge: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200',
        icon: Layers3,
    },
};

export function flowIdentity(type?: string | null) {
    return FLOWS[type === 'filling' || type === 'packing_fg' ? type : 'full'];
}

export function flowTheme(type?: string | null): CSSProperties {
    const flow = flowIdentity(type);
    return { '--flow-accent': flow.color, '--flow-accent-dark': flow.darkColor } as CSSProperties;
}

export function FlowBadge({ type, className }: { type?: string | null; className?: string }) {
    const flow = flowIdentity(type);
    const Icon = flow.icon;
    return (
        <span className={cn('inline-flex w-fit items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10.5px] font-bold', flow.badge, className)}>
            <Icon className="size-3 shrink-0" />
            <span>{flow.label}</span>
        </span>
    );
}

export function FlowBanner({ type }: { type?: string | null }) {
    const flow = flowIdentity(type);
    return (
        <div
            className="border-primary/15 bg-primary/[0.04] flex shrink-0 flex-wrap items-center gap-x-3 gap-y-1.5 border-b px-5 py-2.5 md:px-6"
            aria-label={`Alur batch: ${flow.label}`}
        >
            <FlowBadge type={type} />
            <p className="text-muted-foreground text-[11.5px]">{flow.description}</p>
        </div>
    );
}
