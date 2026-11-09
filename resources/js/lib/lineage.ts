import type { RaceRecord } from '@/types/lineage';

type Named = {
    name: string | null;
    ring_number: string | null;
    personal_number?: string | null;
    id?: number | null;
};

export const birdLabel = (bird: Named) =>
    bird.name ||
    bird.ring_number ||
    bird.personal_number ||
    (bird.id ? `Pigeon #${bird.id}` : 'Unknown');

const percentFormat = new Intl.NumberFormat('en-US', {
    style: 'percent',
    maximumFractionDigits: 2,
});

/** 0.125 -> "12.5%", 0.0625 -> "6.25%" */
export const percent = (value: number) => percentFormat.format(value);

export const genderName = (gender: string | null) =>
    gender === 'male' ? 'Cock' : gender === 'female' ? 'Hen' : null;

/** One-sided relation for a single descendant, e.g. 2 -> "Grandchild". */
export const relationName = (generation: number) =>
    generation === 1
        ? 'Child'
        : generation === 2
          ? 'Grandchild'
          : generation === 3
            ? 'Great-grandchild'
            : `${generation - 2}× great-grandchild`;

const ordinal = (n: number) => {
    const suffix =
        n % 100 >= 11 && n % 100 <= 13
            ? 'th'
            : (({ 1: 'st', 2: 'nd', 3: 'rd' } as Record<number, string>)[
                  n % 10
              ] ?? 'th');
    return `${n}${suffix}`;
};

/** "12 races · 9 clocked · best 3rd · 2× top 10" */
export const raceRecordText = (record: RaceRecord | null) => {
    if (!record || record.flown === 0) return null;
    return [
        `${record.flown} ${record.flown === 1 ? 'race' : 'races'}`,
        `${record.clocked} clocked`,
        record.best ? `best ${ordinal(record.best)}` : null,
        record.top10 ? `${record.top10}× top 10` : null,
    ]
        .filter(Boolean)
        .join(' · ');
};

/** Pedigree position code to words: "SDS" -> "sire's dam's sire". */
export const positionWords = (position: string) =>
    position
        .split('')
        .map((letter) => (letter === 'S' ? 'sire' : 'dam'))
        .join("'s ");

export { statusStyles } from '@/lib/pigeon-status';
