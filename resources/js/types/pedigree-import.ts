import type { MeritInput } from '@/types/lineage';
import type { PigeonSearchItem } from '@/types/pigeon-search';

// pending → reading → ready (or failed) → saved; discarded imports are not listed
export type PedigreeImportStatus =
    | 'pending'
    | 'reading'
    | 'ready'
    | 'failed'
    | 'saved';

// One uploaded sheet on the import page
export interface PedigreeImportItem {
    id: number;
    status: PedigreeImportStatus;
    file_name: string | null;
    // The sheet itself, while the import is open
    file_url: string | null;
    is_pdf: boolean;
    error: string | null;
    can_retry: boolean;
    // The bird read from the sheet
    bird: { ring_number: string | null; name: string | null } | null;
    ancestors: number;
    // What the reader could not read
    note: string | null;
    // The pigeon it was saved to
    pigeon: {
        id: number;
        ring_number: string | null;
        name: string | null;
    } | null;
}

export interface PedigreeImportAllowance {
    // Sheets that can still be imported now
    left: number;
    // Free accounts only: imports left of free_limit (null with Premium)
    free_left: number | null;
    free_limit: number;
    daily_limit: number;
}

// An ancestor as read from the sheet, and as the review form sends it back
export interface ImportBird {
    ring_number: string | null;
    name: string | null;
    color: string | null;
    bloodline: string | null;
    remarks: string | null;
    merits: MeritInput[];
}

// The bird the pedigree is for
export interface ImportSubject extends ImportBird {
    gender: 'male' | 'female' | null;
    bred_by: string | null;
}

export interface ImportDraft {
    bird: ImportSubject;
    // By place in the pedigree: "S" the sire, "SD" the sire's dam, ...
    ancestors: Record<string, ImportBird>;
    note: string | null;
}

// A bird on the sheet that is already in the loft (matched by ring number)
export interface ImportMatch extends PigeonSearchItem {
    // A sire must be a cock and a dam a hen to be linked
    linkable: boolean;
}

// What the last save did, to tell the user
export interface ImportSaved {
    pigeon: { id: number; label: string | null };
    ancestors: number;
    // Places where a different bird was already recorded, kept as they were
    kept: string[];
    // Other loft birds whose pedigrees gained ancestors of the same outside birds
    also_in: string[];
    sheet_kept: boolean;
    sheet_failed: boolean;
}
