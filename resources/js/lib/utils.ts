import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/**
 * Parse a plain 'YYYY-MM-DD' calendar date as a *local* date.
 *
 * `new Date('2027-03-02')` is parsed as UTC midnight, which renders as the
 * previous day for any viewer west of Greenwich — so a conference slot on
 * March 2 would show as March 1. Building the Date from parts avoids that.
 */
export function parseCalendarDate(date: string): Date {
    const [year, month, day] = date.split('T')[0].split('-').map(Number);

    return new Date(year, month - 1, day);
}

/** Format a plain calendar date for display, timezone-safe. */
export function formatCalendarDate(
    date: string,
    options: Intl.DateTimeFormatOptions = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' },
): string {
    return parseCalendarDate(date).toLocaleDateString(undefined, options);
}
