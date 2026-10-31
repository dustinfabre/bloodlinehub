import type { PedigreeHighlight, PedigreeNode } from '@/types/pedigree';

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

// Readable text color on top of a highlight color
export const contrastText = (hex: string): string => {
    const value = hex.replace('#', '');
    const r = parseInt(value.slice(0, 2), 16);
    const g = parseInt(value.slice(2, 4), 16);
    const b = parseInt(value.slice(4, 6), 16);
    return (r * 299 + g * 587 + b * 114) / 1000 > 160 ? '#1d2a2c' : '#ffffff';
};

// Unique highlight colors used anywhere in the tree, for the chart legend
export const collectHighlights = (
    root: PedigreeNode | null,
): PedigreeHighlight[] => {
    const found = new Map<string, PedigreeHighlight>();
    const visit = (node: PedigreeNode | null) => {
        if (!node) return;
        if (node.highlight)
            found.set(
                `${node.highlight.name}|${node.highlight.color}`,
                node.highlight,
            );
        visit(node.sire);
        visit(node.dam);
    };
    visit(root);
    return [...found.values()];
};

export const pedigreeFilename = (
    ringNumber: string | null,
    serial: string,
): string =>
    `pedigree-${(ringNumber || serial).replace(/[^A-Za-z0-9-]+/g, '-').replace(/^-+|-+$/g, '')}`;
