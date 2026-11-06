// A parent as shown in pigeon search results: a linked loft bird or an outside bird recorded on the pigeon
export interface PigeonSearchParent {
    ring_number: string | null;
    name: string | null;
}

// The standard details of a pigeon in every pigeon search and picker (built by Pigeon::searchSummary() on the
// server, shown by PigeonSearchResult.vue). See "Pigeon search results" in CLAUDE.md.
export interface PigeonSearchItem {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number?: string | null;
    gender?: string | null;
    color?: string | null;
    bloodline?: string | null;
    // Private notes; only ever sent to the bird's owner
    notes?: string | null;
    remarks?: string | null;
    sire?: PigeonSearchParent | null;
    dam?: PigeonSearchParent | null;
}
