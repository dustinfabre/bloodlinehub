import { byImportance } from '@/lib/merits';
import type { Merit } from '@/types/lineage';
import type {
    PedigreeNode,
    PedigreeSlot,
    PedigreeVisibility,
} from '@/types/pedigree';

// A4 landscape at 96 CSS px per inch (a hair under 297 x 210 mm so print never spills onto a second page)
export const PAPER_WIDTH = 1122;
export const PAPER_HEIGHT = 793;

// Subject + 4 ancestor generations; depth 4 is the last column
export const MAX_DEPTH = 4;

export const GENERATION_LABELS = [
    'Bird',
    'Parents',
    'Grandparents',
    'Great-grandparents',
    'Great-great-grandparents',
];

// Singular name of a bird in each generation, by sex
const RELATION_NAMES: Record<number, [string, string]> = {
    1: ['Sire', 'Dam'],
    2: ['Grandsire', 'Granddam'],
    3: ['Great-grandsire', 'Great-granddam'],
    4: ['Great-great-grandsire', 'Great-great-granddam'],
};

// e.g. "SD" -> "Granddam"
export const relationName = (key: string): string => {
    const [sire, dam] = RELATION_NAMES[key.length] ?? ['Bird', 'Bird'];
    return key.endsWith('S') ? sire : dam;
};

// e.g. "SD" -> ["Sire", "Dam"], to show which line a bird sits on
export const lineageSteps = (key: string): string[] =>
    [...key].map((step) => (step === 'S' ? 'Sire' : 'Dam'));

export const VISIBILITY_LABELS: Record<PedigreeVisibility, string> = {
    public: 'Public pedigree',
    password: 'Password protected',
    private: 'Private pedigree',
};

// Every position on the chart, parents after their offspring. Known birds always get both parent slots
// (shown as "Unknown" when missing); unknown birds end their branch.
export const flattenPedigree = (root: PedigreeNode): PedigreeSlot[] => {
    const slots: PedigreeSlot[] = [];
    const visit = (
        node: PedigreeNode | null,
        depth: number,
        index: number,
        key: string,
        child: PedigreeNode | null,
    ) => {
        const side = key ? (key.endsWith('S') ? 'sire' : 'dam') : null;
        slots.push({ key, depth, index, side, node, child });
        if (!node || depth >= MAX_DEPTH) return;
        visit(node.sire, depth + 1, index * 2, `${key}S`, node);
        visit(node.dam, depth + 1, index * 2 + 1, `${key}D`, node);
    };
    visit(root, 0, 0, '', null);
    return slots;
};

// The chart is a grid of 16 rows (one per bird in the last generation); each bird spans the rows of its ancestors
export const GRID_ROWS = 2 ** MAX_DEPTH;

export const slotGridArea = (slot: PedigreeSlot) => {
    const span = 2 ** (MAX_DEPTH - slot.depth);
    return {
        gridColumn: `${slot.depth + 1}`,
        gridRow: `${slot.index * span + 1} / span ${span}`,
    };
};

// Ring numbers are matched the way Lineage matches them: ignoring case, spaces and punctuation
export const normalizeRing = (ring: string | null | undefined): string =>
    (ring ?? '').toUpperCase().replace(/[^A-Z0-9]/g, '');

// "A", "A and B", "A, B and C", "A, B and 2 more"
export const listLabels = (labels: string[], max = 3): string => {
    if (labels.length <= 1) return labels[0] ?? '';
    if (labels.length > max) {
        return `${labels.slice(0, max - 1).join(', ')} and ${labels.length - max + 1} more`;
    }
    return `${labels.slice(0, -1).join(', ')} and ${labels[labels.length - 1]}`;
};

export const sexSymbol = (gender: string | null): string => {
    if (gender === 'male') return '♂';
    if (gender === 'female') return '♀';
    return '';
};

export const formatPedigreeDate = (value: string | null): string => {
    if (!value) return '';
    // Dates arrive as Y-m-d; parse as local time so the day doesn't shift by timezone
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};

export type MeritKeyItem = Pick<
    Merit,
    'type' | 'code' | 'label' | 'color' | 'weight'
>;

/** The merit types used anywhere on a chart, for the color key in the footer (most significant first). */
export const collectMerits = (root: PedigreeNode | null): MeritKeyItem[] => {
    const found = new Map<string, MeritKeyItem>();
    const visit = (node: PedigreeNode | null) => {
        if (!node) return;
        for (const merit of node.merits ?? []) {
            found.set(merit.type, {
                type: merit.type,
                code: merit.code,
                label: merit.label,
                color: merit.color,
                weight: merit.weight,
            });
        }
        visit(node.sire);
        visit(node.dam);
    };
    visit(root);
    return byImportance([...found.values()]);
};

/** Merit chip text on a card: the full one-liner near the subject, code and year further out. */
export const meritChip = (merit: Merit, depth: number): string =>
    depth <= 1
        ? merit.short
        : [merit.code, merit.year ? `'${String(merit.year).slice(-2)}` : null]
              .filter(Boolean)
              .join(' ');

export const pedigreeFilename = (
    ringNumber: string | null,
    serial: string,
): string =>
    `pedigree-${(ringNumber || serial).replace(/[^A-Za-z0-9-]+/g, '-').replace(/^-+|-+$/g, '')}`;
