import type { Assessment, Merit } from '@/types/lineage';
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
    // Loft birds only (null for outside ancestors and sample youngsters)
    photo_url: string | null;
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
    // The owner has Premium. Without it the sheet has no QR code, online link, contact details or COI, and says
    // where it was generated instead.
    premium: boolean;
    is_public: boolean;
    visibility: PedigreeVisibility;
    share_url: string;
    qr_svg: string;
    breeder: string;
    // Breeder contact details the breeder chose to show (null when hidden or not filled in)
    contact: PedigreeContact;
    photo_url: string | null;
    // The user is the bird's breeder when both parents are their birds, otherwise its owner
    role: 'breeder' | 'owner';
    // Who bred the bird: the user's loft, or whoever they entered (null when not known)
    bred_by: string | null;
    // The bird's inbreeding coefficient, printed at the foot of the sheet (null when a parent is unknown)
    coi: PedigreeCoi | null;
    // Every photo of the bird, the main one first
    photos: string[];
    // Photos of relatives uploaded on the bird: an ancestor's chart position ('S', 'SD', ...) or e.g. 'sibling'
    relative_photos: { url: string; relation: string }[];
    // Certificates and documents the owner chose to show, of the bird ('') or of a loft parent ('S' or 'D')
    documents: { url: string; of: '' | 'S' | 'D' }[];
    // Related loft birds off the chart the owner features below it
    notable_relatives: NotableRelative[];
    generated_at: string;
}

/** The COI on a pedigree sheet (PairingAnalysis::sheet()). */
export interface PedigreeCoi {
    coefficient: number;
    assessment: Assessment;
    // The biggest contributors, largest first
    common_ancestors: {
        name: string | null;
        ring_number: string | null;
        contribution: number;
    }[];
    common_count: number;
    // Generations of ancestors searched, and how many of them are fully known
    generations: number;
    complete_generations: number;
}

/** Links from a pedigree's Pairing Analysis and Lineage sections to the full reports in the app (owner only). */
export interface PedigreeReportLinks {
    // Null unless both parents are loft birds
    pairing_analysis: string | null;
    lineage: string;
    edit: string;
}

export type PedigreeSide = 'sire' | 'dam';

// A related loft bird shown below a pedigree (id is null on public pedigrees)
export interface NotableRelative {
    id: number | null;
    name: string | null;
    ring_number: string | null;
    gender: string | null;
    color: string | null;
    // 'sibling', 'half_sibling_sire', 'offspring', 'aunt_uncle_dam', ... (see OTHER_RELATIVES)
    relation: string;
    photo_url: string | null;
    merits: Merit[];
}

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

// A bird offered by "Copy ancestors": the standard search details, and where it is related. "own" is the pigeon
// itself, whose related pairs are the places where the same bird appears twice in its own pedigree (inbred birds).
export interface PedigreeCopySource extends PigeonSearchItem {
    own: boolean;
    related: PedigreeRelation[];
}

// One bird picked to copy: its position in the other pigeon's pedigree, and where it goes in this one
export interface PedigreeCopyChoice {
    from: string;
    to: string | null;
}
