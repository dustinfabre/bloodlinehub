import type { PigeonSearchItem } from '@/types/pigeon-search';

// What a loft check counts (LoftCheckScope::toArray() on the server)
export interface LoftCheckScope {
    // "all", "none" (no location) or a location id
    location: string;
    // "all", "none" (no ring series), "digits" (rings of digits only) or a series such as "PHA-2026"
    series: string;
    status: string[];
}

export interface LoftCheckSummary {
    id: number;
    label: string;
    note: string | null;
    scope: LoftCheckScope;
    expected_count: number;
    present_count: number;
    missing_count: number;
    unchecked_count: number;
    returned_count: number;
    created_at: string;
}

// A bird on the check board: the standard search result details, plus its ring series and number in the series
export interface LoftCheckBird extends PigeonSearchItem {
    status: string;
    location: string | null;
    series: string;
    number: string | null;
    // Missing birds only: when it went missing, if recorded
    status_since?: string | null;
}

export interface RingSeriesOption {
    value: string;
    label: string;
    count: number;
    from: string | null;
    to: string | null;
}

// A bird in a saved check, with where it is now
export interface LoftCheckResultBird extends PigeonSearchItem {
    status: string;
    result: 'present' | 'missing' | 'unchecked' | 'returned';
}

// The query that opens the check board on the same group again
export const scopeQuery = (scope: LoftCheckScope, inLoftStatuses?: string[]) => ({
    location: scope.location === 'all' ? undefined : scope.location,
    series: scope.series === 'all' ? undefined : scope.series,
    status:
        inLoftStatuses && scope.status.length === inLoftStatuses.length ? undefined : scope.status,
});

// "Sat, Nov 7 · 6:42 PM"
export const formatCheckDate = (iso: string) => {
    const date = new Date(iso);
    const day = date.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: date.getFullYear() === new Date().getFullYear() ? undefined : 'numeric' });
    const time = date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    return `${day} · ${time}`;
};

// "Nov 7, 2026"
export const formatDay = (iso: string) =>
    new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });

// "Nov 7"
export const formatShortDay = (iso: string) =>
    new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
