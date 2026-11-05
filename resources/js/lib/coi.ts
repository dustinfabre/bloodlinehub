import type { Assessment } from '@/types/lineage';
import {
    AlertTriangle,
    CircleCheck,
    GitMerge,
    OctagonAlert,
    type LucideIcon,
} from 'lucide-vue-next';

export type CoiLevel = Assessment['level'];

// Fill carries severity; the track is a lighter step of the same hue. Always shown with an icon and label.
export const COI_LEVEL_STYLES: Record<
    CoiLevel,
    { fill: string; track: string; text: string; icon: LucideIcon }
> = {
    outcross: {
        fill: 'bg-sky-600 dark:bg-sky-400',
        track: 'bg-sky-100 dark:bg-sky-950',
        text: 'text-sky-700 dark:text-sky-300',
        icon: CircleCheck,
    },
    light: {
        fill: 'bg-sky-600 dark:bg-sky-400',
        track: 'bg-sky-100 dark:bg-sky-950',
        text: 'text-sky-700 dark:text-sky-300',
        icon: GitMerge,
    },
    linebreeding: {
        fill: 'bg-sky-600 dark:bg-sky-400',
        track: 'bg-sky-100 dark:bg-sky-950',
        text: 'text-sky-700 dark:text-sky-300',
        icon: GitMerge,
    },
    close: {
        fill: 'bg-amber-500 dark:bg-amber-400',
        track: 'bg-amber-100 dark:bg-amber-950',
        text: 'text-amber-700 dark:text-amber-300',
        icon: AlertTriangle,
    },
    inbreeding: {
        fill: 'bg-red-600 dark:bg-red-400',
        track: 'bg-red-100 dark:bg-red-950',
        text: 'text-red-700 dark:text-red-300',
        icon: OctagonAlert,
    },
};
