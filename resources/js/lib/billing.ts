import type { FeedbackCategory, PaymentStatus } from '@/types/billing';

// "₱1,500" / "$30.00"; whole peso amounts drop the centavos
export const formatMoney = (
    amount: string | number | null | undefined,
    currency: string,
): string => {
    const value = Number(amount ?? 0);
    const whole = Number.isInteger(value);

    return new Intl.NumberFormat(currency === 'PHP' ? 'en-PH' : 'en-US', {
        style: 'currency',
        currency,
        minimumFractionDigits: whole && currency === 'PHP' ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(value);
};

export const formatDate = (value: string | null | undefined): string =>
    value
        ? new Date(value).toLocaleDateString('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          })
        : '';

export const formatDateTime = (value: string | null | undefined): string =>
    value
        ? new Date(value).toLocaleString('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          })
        : '';

// "month", "year", "3 months": what one payment of the price covers, after "per" or "/"
export const perPeriod = (months: number): string =>
    months === 1 ? 'month' : months === 12 ? 'year' : `${months} months`;

// Whole days from now until a date, at least 0
export const daysLeft = (until: string | null | undefined): number =>
    until
        ? Math.max(
              0,
              Math.ceil((new Date(until).getTime() - Date.now()) / 86_400_000),
          )
        : 0;

// "1 month", "12 months"
export const durationLabel = (months: number): string =>
    months % 12 === 0 && months >= 12
        ? `${months / 12} ${months === 12 ? 'year' : 'years'}`
        : `${months} ${months === 1 ? 'month' : 'months'}`;

export const ACCOUNT_TYPES = {
    checking: 'Checking',
    savings: 'Savings',
} as const;

export const isPremium = (premiumUntil: string | null | undefined): boolean =>
    !!premiumUntil && new Date(premiumUntil).getTime() > Date.now();

export const PAYMENT_STATUS: Record<
    PaymentStatus,
    { label: string; class: string }
> = {
    pending: {
        label: 'Waiting for review',
        class: 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    approved: {
        label: 'Approved',
        class: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    },
    rejected: {
        label: 'Rejected',
        class: 'border-destructive/40 bg-destructive/10 text-destructive',
    },
};

export const FEEDBACK_CATEGORIES: Record<
    FeedbackCategory,
    { label: string; hint: string }
> = {
    bug: { label: 'Something is broken', hint: 'Bug report' },
    idea: { label: 'An idea or request', hint: 'Idea' },
    question: { label: 'A question', hint: 'Question' },
    other: { label: 'Something else', hint: 'Other' },
};
