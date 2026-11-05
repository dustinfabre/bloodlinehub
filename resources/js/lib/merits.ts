import type { Merit } from '@/types/lineage';

// Pedigree navy; the dark choice for chip text
const INK = '#14243a';

const luminance = (hex: string): number => {
    const value = hex.replace('#', '');
    const [r, g, b] = [0, 2, 4].map((start) => {
        const channel = parseInt(value.slice(start, start + 2), 16) / 255;
        return channel <= 0.04045
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

/** White or navy text, whichever has more contrast (WCAG) on a merit color. */
export const meritTextColor = (hex: string): string => {
    const background = luminance(hex);
    const onWhite = 1.05 / (background + 0.05);
    const onInk = (background + 0.05) / (luminance(INK) + 0.05);
    return onWhite >= onInk ? '#ffffff' : INK;
};

/** Chip colors for a merit: its fixed fill (MeritType::color()) and readable text. */
export const meritChipStyle = (merit: Pick<Merit, 'color'>) => ({
    backgroundColor: merit.color,
    color: meritTextColor(merit.color),
});

/** Most significant first: by merit weight, then the most recent year. */
export const byImportance = <
    T extends Pick<Merit, 'weight'> & { year?: number | null },
>(
    merits: T[],
): T[] =>
    [...merits].sort(
        (a, b) => b.weight - a.weight || (b.year ?? 0) - (a.year ?? 0),
    );

/** The merit that colors a bird's accent (its heaviest), or null. */
export const topMerit = <T extends Pick<Merit, 'weight'>>(
    merits: T[] | null | undefined,
): T | null =>
    (merits ?? []).reduce<T | null>(
        (best, merit) => (!best || merit.weight > best.weight ? merit : best),
        null,
    );
