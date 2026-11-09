// Pigeon statuses in display order, matching Pigeon::STATUSES on the server
export interface PigeonStatusOption {
    value: string;
    label: string;
    // Badge colors for light and dark mode
    className: string;
    // A small colored dot, for menus and filters
    dotClass: string;
    // No longer in the loft (Pigeon::GONE_STATUSES)
    gone: boolean;
}

export const PIGEON_STATUSES: PigeonStatusOption[] = [
    {
        value: 'stock',
        label: 'In Stock',
        className: 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
        dotClass: 'bg-blue-500',
        gone: false,
    },
    {
        value: 'racing',
        label: 'Racing',
        className: 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300',
        dotClass: 'bg-green-500',
        gone: false,
    },
    {
        value: 'breeding',
        label: 'Breeding',
        className: 'bg-pink-100 text-pink-700 dark:bg-pink-500/15 dark:text-pink-300',
        dotClass: 'bg-pink-500',
        gone: false,
    },
    {
        value: 'injured',
        label: 'Injured',
        className: 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
        dotClass: 'bg-orange-500',
        gone: false,
    },
    {
        value: 'deceased',
        label: 'Deceased',
        className: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
        dotClass: 'bg-red-500',
        gone: true,
    },
    {
        value: 'missing',
        label: 'Missing',
        className: 'bg-purple-100 text-purple-700 dark:bg-purple-500/15 dark:text-purple-300',
        dotClass: 'bg-purple-500',
        gone: true,
    },
];

export const statusStyles: Record<string, { className: string; label: string }> = Object.fromEntries(
    PIGEON_STATUSES.map((status) => [status.value, { className: status.className, label: status.label }]),
);

// Badge style for a status, with a neutral fallback for anything unexpected
export const statusStyle = (status: string | null | undefined) =>
    (status && statusStyles[status]) || { className: 'bg-muted text-muted-foreground', label: status ?? 'Unknown' };
