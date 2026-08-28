import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function formatDate(dateString: string | null | undefined): string {
    if (!dateString) {
return '-';
}

    try {
        const date = new Date(dateString);

        if (isNaN(date.getTime())) {
return dateString;
}

        const formatted = new Intl.DateTimeFormat('id-ID', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'Asia/Jakarta'
        }).format(date);

        return formatted.replace('pukul ', '').replace(/\./g, ':') + ' WIB';
    } catch {
        return dateString;
    }
}

