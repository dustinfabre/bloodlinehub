import type { FeedbackCategory } from '@/types/billing';

// Admin feedback inbox: label colors and status styles (statuses themselves come from Feedback::STATUSES)

export interface FeedbackLabel {
    id: number;
    name: string;
    color: string;
    feedback_count?: number;
}

export type StatusMap = Record<FeedbackCategory, Record<string, string>>;

const LABEL_COLORS: Record<string, string> = {
    gray: 'border-border bg-muted text-muted-foreground',
    red: 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    orange: 'border-orange-500/30 bg-orange-500/10 text-orange-700 dark:text-orange-300',
    amber: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    green: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    teal: 'border-teal-500/30 bg-teal-500/10 text-teal-700 dark:text-teal-300',
    blue: 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    violet: 'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300',
    pink: 'border-pink-500/30 bg-pink-500/10 text-pink-700 dark:text-pink-300',
};

// The dot shown in the color picker
const LABEL_SWATCHES: Record<string, string> = {
    gray: 'bg-zinc-400',
    red: 'bg-red-500',
    orange: 'bg-orange-500',
    amber: 'bg-amber-500',
    green: 'bg-emerald-500',
    teal: 'bg-teal-500',
    blue: 'bg-sky-500',
    violet: 'bg-violet-500',
    pink: 'bg-pink-500',
};

export const labelClass = (color: string) =>
    LABEL_COLORS[color] ?? LABEL_COLORS.gray;

export const labelSwatch = (color: string) =>
    LABEL_SWATCHES[color] ?? LABEL_SWATCHES.gray;

// Under way, done well, or closed without doing it
const ACTIVE = ['in_progress', 'planned'];
const GOOD = ['fixed', 'done', 'answered'];

export const statusClass = (status: string) =>
    ACTIVE.includes(status)
        ? 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300'
        : GOOD.includes(status)
          ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
          : status === 'new'
            ? 'border-border text-muted-foreground'
            : 'border-border bg-muted text-muted-foreground';

// Statuses the sender can be emailed about (FeedbackStatusChanged)
export const NOTIFIABLE_STATUSES = [
    'in_progress',
    'fixed',
    'cant_reproduce',
    'planned',
    'done',
    'not_planned',
    'answered',
];
