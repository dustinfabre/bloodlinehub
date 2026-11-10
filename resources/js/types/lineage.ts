import type { PigeonSearchItem } from '@/types/pigeon-search';

export interface Merit {
    // null for merits that are not stored rows: external ancestors' merits, suggestions, public pedigrees
    id: number | null;
    type: string;
    label: string;
    code: string;
    category: 'racing' | 'breeding';
    // Fixed chip color from MeritType::color()
    color: string;
    // MeritType::weight(): the heaviest merit colors a pedigree card's accent
    weight: number;
    level: string | null;
    level_label: string | null;
    year: number | null;
    detail: string | null;
    // Pedigree-style one-liner, e.g. "ACE Nat '24"
    short: string;
}

export interface MeritTypeOption {
    value: string;
    label: string;
    code: string;
    description: string;
    category: 'racing' | 'breeding';
    color: string;
    weight: number;
}

export interface MeritLevelOption {
    value: string;
    label: string;
}

export interface RaceRecord {
    flown: number;
    clocked: number;
    best: number | null;
    top10: number;
}

/** A bird in a lineage result: a loft bird, or an external ancestor (id null) recorded on a pedigree. */
export interface BirdSummary {
    key: string;
    id: number | null;
    external: boolean;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    gender: string | null;
    color: string | null;
    status: string | null;
    hatch_date: string | null;
    photo_url: string | null;
    bloodline: string | null;
    merits: Merit[];
    merit_score: number;
    results: RaceRecord | null;
}

export interface MiniBird {
    key: string;
    id: number | null;
    name: string | null;
    ring_number: string | null;
    external: boolean;
}

export interface DescentLine {
    generation: number;
    label: string;
    // Birds between the ancestor and the descendant, oldest first
    via: MiniBird[];
    // Expected share of the ancestor's genes (0..1)
    share: number;
}

export interface SharedDescendant {
    bird: BirdSummary;
    direct: boolean;
    from_a: DescentLine;
    from_b: DescentLine;
}

export interface SharedReport {
    total: number;
    direct: number;
    proven: number;
    birds: SharedDescendant[];
}

// A bird found by the Lineage / Pairing Analysis picker: the standard search details (see CLAUDE.md) plus its status
export interface PickerPigeon extends PigeonSearchItem {
    personal_number: string | null;
    gender: string | null;
    status: string | null;
    color: string | null;
    bloodline: string | null;
}

/** A merit worked out from race results or offspring, waiting to be added or dismissed. */
export interface MeritSuggestion {
    // "{pigeon id}:{type}:{source}", sent back to add or dismiss it
    key: string;
    pigeon_id: number;
    source: string;
    // Why it is suggested, e.g. "Clocked in all 5 laps"
    reason: string;
    merit: Merit;
}

/** A stored merit on an external ancestor, as edited in the pedigree editor. */
export interface MeritInput {
    type: string;
    level: string | null;
    year: number | null;
    detail: string | null;
}

export interface WinnerBird {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    merits: Merit[];
}

/** Golden Pair status: two or more youngsters with a winning merit. */
export interface GoldenPairInfo {
    offspring: number;
    winner_count: number;
    golden: boolean;
    winners: WinnerBird[];
}

export interface RaceTypeOption {
    value: string;
    label: string;
    description: string;
}

/** One season entry's laps and standing, with its open merit suggestions from that season. */
export interface SeasonStanding {
    laps: number;
    clocked: number;
    best: number | null;
    overall_position: number | null;
    suggestions: MeritSuggestion[];
}

export interface Assessment {
    level: 'outcross' | 'light' | 'linebreeding' | 'close' | 'inbreeding';
    label: string;
    equivalent: string;
}

/** A pair saved to the breeding plan. */
export interface PlannedPair {
    id: number;
    notes: string | null;
    created_at: string | null;
    sire: BirdSummary;
    dam: BirdSummary;
    coi: number;
    assessment: Assessment;
    golden: boolean;
    winner_count: number;
    sire_plans: number;
    dam_plans: number;
}

/** Pairing Analysis of a sire × dam: on the Pairing Analysis page, and on a pedigree for the bird's parents. */
export interface PairingAnalysisReport {
    sire: BirdSummary;
    dam: BirdSummary;
    coi: number;
    relationship: number;
    sire_coi: number;
    dam_coi: number;
    assessment: Assessment;
    common_ancestors: {
        bird: BirdSummary;
        contribution: number;
        // Positions in the youngster's pedigree of each pair of lines that meet at the ancestor
        lines: { sire: string; dam: string }[];
    }[];
    influential_ancestors: {
        bird: BirdSummary;
        share: number;
        positions: string[];
    }[];
    completeness: { generation: number; known: number; total: number }[];
    shared: SharedReport;
    golden_pair: GoldenPairInfo;
}

export interface DescendantRow {
    bird: BirdSummary;
    via: MiniBird[];
    other_parent: MiniBird | null;
    share: number;
}

/** A bird's family as shown by Lineage: its descendants, siblings and mates. */
export interface Family {
    sire: BirdSummary | null;
    dam: BirdSummary | null;
    descendants: {
        generation: number;
        label: string;
        birds: DescendantRow[];
    }[];
    siblings: {
        full: BirdSummary[];
        sire_side: BirdSummary[];
        dam_side: BirdSummary[];
    };
    mates: {
        bird: BirdSummary | null;
        offspring: number;
        proven: number;
        // Youngsters with a winning merit; two or more make a Golden Pair
        winners: number;
        golden: boolean;
    }[];
}
