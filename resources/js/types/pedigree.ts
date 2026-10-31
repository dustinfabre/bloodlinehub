export interface PedigreeHighlight {
    name: string;
    color: string;
}

export interface PedigreeNode {
    id: number | null;
    external: boolean;
    name: string | null;
    ring_number: string | null;
    color: string | null;
    gender: string | null;
    hatch_date: string | null;
    bloodline: string | null;
    remarks: string | null;
    highlight: PedigreeHighlight | null;
    sire: PedigreeNode | null;
    dam: PedigreeNode | null;
}

export interface PedigreeMeta {
    serial: string;
    is_public: boolean;
    share_url: string;
    qr_svg: string;
    breeder: string;
    photo_url: string | null;
    generated_at: string;
}

export type PedigreeSide = 'sire' | 'dam';
