import type { Merit } from '@/types/lineage';
import type { PigeonSearchItem } from '@/types/pigeon-search';

export interface PedigreeNode {
    // Set for birds in the loft (owner charts only)
    id: number | null;
    external: boolean;
    // Where an external bird is stored: its nearest loft bird and the path from it, e.g. "SD" (owner charts only)
    anchor_id: number | null;
    path: string | null;
    name: string | null;
    ring_number: string | null;
    color: string | null;
    gender: string | null;
    hatch_date: string | null;
    bloodline: string | null;
    remarks: string | null;
    merits: Merit[];
    sire: PedigreeNode | null;
    dam: PedigreeNode | null;
}

export type PedigreeVisibility = 'private' | 'password' | 'public';

export interface PedigreeContact {
    phone: string | null;
    email: string | null;
    address: string | null;
}

export interface PedigreeMeta {
    serial: string;
    is_public: boolean;
    visibility: PedigreeVisibility;
    share_url: string;
    qr_svg: string;
    breeder: string;
    // Breeder contact details the breeder chose to show (null when hidden or not filled in)
    contact: PedigreeContact;
    photo_url: string | null;
    generated_at: string;
}

export type PedigreeSide = 'sire' | 'dam';

export interface PedigreeSharing {
    visibility: PedigreeVisibility;
    has_password: boolean;
    password_expires_at: string | null;
    password_expired: boolean;
}

// One position on the chart grid
export interface PedigreeSlot {
    // Position from the subject: '' for the bird itself, 'S' its sire, 'SD' its sire's dam, ...
    key: string;
    depth: number;
    // Row within its generation, top to bottom
    index: number;
    side: PedigreeSide | null;
    node: PedigreeNode | null;
    // The bird this slot is a parent of (null for the subject)
    child: PedigreeNode | null;
}

// A chart position being added or edited in the pedigree editor
export interface AncestorTarget {
    entry: PedigreeSlot;
    anchor_id: number;
    path: string;
    // The loft bird this ancestor is saved on, when it isn't the pigeon being edited
    anchor: PedigreeNode | null;
    // How many recorded ancestors sit behind this one (removed along with it)
    behind: number;
    // Loft birds whose pedigrees hold another record of this bird (same ring number)
    alsoIn: string[];
    // The same for the bird in front of this position (its offspring in the tree), and that bird's label
    offspringAlsoIn: string[];
    offspringLabel: string | null;
}

// Every record of one outside bird in the loft, keyed by normalised ring number
export type PedigreeLinks = Record<
    string,
    { anchor_id: number; path: string; label: string }[]
>;

// Which other pedigrees a save or removal also changed, and which were left alone
export interface PedigreeSyncReport {
    updated: string[];
    skipped: string[];
}

// Where another pigeon's pedigree holds the same bird as this one: its position there ("from") and here ("to")
export interface PedigreeRelation {
    from: string;
    to: string;
}

// A bird offered by "Copy from another pigeon": the standard search details, and where it is related
export interface PedigreeCopySource extends PigeonSearchItem {
    related: PedigreeRelation[];
}

// One bird picked to copy: its position in the other pigeon's pedigree, and where it goes in this one
export interface PedigreeCopyChoice {
    from: string;
    to: string | null;
}
