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

/**
 * Calendar date only, for DATE columns that carry no time (e.g.
 * validation_date). The "YYYY-MM-DD" part is shown as-is; converting it as a
 * timestamp would invent a time-of-day (07:00 WIB from UTC midnight).
 */
export function formatDateOnly(
    dateString: string | null | undefined,
    intlLocale: string = 'id-ID',
): string {
    const match = dateString?.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (!match) {
        return dateString || '-';
    }

    const date = new Date(
        Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])),
    );

    return new Intl.DateTimeFormat(intlLocale, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        timeZone: 'UTC',
    }).format(date);
}

/**
 * Date + time in Jakarta time, written for the UI language (`intlLocale` is
 * a BCP 47 tag; components get a pre-bound version from `useTranslation()`).
 */
export function formatDate(
    dateString: string | null | undefined,
    intlLocale: string = 'id-ID',
    withSeconds: boolean = false,
): string {
    if (!dateString) {
        return '-';
    }

    try {
        const date = new Date(dateString);

        if (isNaN(date.getTime())) {
            return dateString;
        }

        const formatted = new Intl.DateTimeFormat(intlLocale, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            ...(withSeconds ? { second: '2-digit' as const } : {}),
            hourCycle: 'h23',
            timeZone: 'Asia/Jakarta',
        }).format(date);

        if (intlLocale === 'id-ID') {
            return formatted.replace('pukul ', '').replace(/\./g, ':') + ' WIB';
        }

        return formatted.replace(' at ', ', ') + ' WIB';
    } catch {
        return dateString;
    }
}
