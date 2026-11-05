import type { Merit } from '@/types/lineage';

export interface PedigreeHighlight {
    // Color tag id (owner charts only)
    id: number | null;
    name: string;
    color: string;
}

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
    highlight: PedigreeHighlight | null;
    merits: Merit[];
    sire: PedigreeNode | null;
    dam: PedigreeNode | null;
}

export type PedigreeVisibility = 'private' | 'password' | 'public';

export interface PedigreeMeta {
    serial: string;
    is_public: boolean;
    visibility: PedigreeVisibility;
    share_url: string;
    qr_svg: string;
    breeder: string;
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
}
