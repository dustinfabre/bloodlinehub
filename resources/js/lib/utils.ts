import { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
) {
    return toUrl(urlToCheck) === currentUrl;
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/** A link that is safe to put in href: http(s) only, anything else (javascript:, data:...) gives undefined. */
export function safeHttpUrl(
    url: string | null | undefined,
): string | undefined {
    const value = url?.trim();
    if (!value) return undefined;
    if (/^https?:\/\//i.test(value)) return value;
    // A bare domain typed before websites were checked, e.g. "club.ph"
    return /^[a-z][a-z0-9+.-]*:/i.test(value) ? undefined : `https://${value}`;
}
