import {
    familyName,
    familyPath,
    isAncestorRelation,
    OTHER_RELATIVES,
} from '@/lib/pedigree';

// One photo in a photo list on the pigeon form: either already saved on the pigeon or added now
export interface PhotoItem {
    // Stable key for rendering
    key: string;
    // What to show: the saved URL, or a local preview of a photo added now
    src: string;
    // The saved photo's URL; null for a photo added now
    saved: string | null;
    // The file to upload, for a photo added now
    file: File | null;
    // Certificates & relatives' photos only: who it shows, as an ancestor's chart position ('S' its sire,
    // 'SD' its sire's dam, ...) or one of OTHER_RELATIVES; null for a certificate or other document
    relation?: string | null;
    // Certificates & relatives' photos only: shown on the pedigree (otherwise only the owner sees it)
    public?: boolean;
}

// Who a relative's photo can show: parents, grandparents and great-grandparents
export const RELATIVE_OPTIONS = [
    'S',
    'D',
    'SS',
    'SD',
    'DS',
    'DD',
    'SSS',
    'SSD',
    'SDS',
    'SDD',
    'DSS',
    'DSD',
    'DDS',
    'DDD',
].map((position) => ({
    value: position,
    // "Father", "Grandmother (father's mother)"
    label:
        position.length > 1
            ? `${familyName(position)} (${familyPath(position).toLowerCase()})`
            : familyName(position),
}));

// Relatives outside its own line: full and half siblings, offspring and other family
export const OTHER_RELATIVE_OPTIONS = Object.entries(OTHER_RELATIVES).map(
    ([value, relative]) => ({ value, label: relative.option }),
);

let nextKey = 0;
const key = () => `photo-${++nextKey}`;

// A saved relation the form no longer offers (e.g. an older "half-sibling") counts as a relative, so the photo
// stays on the pedigree rather than turning private on the next save
const knownRelation = (relation: string | null | undefined): string | null => {
    if (!relation) return null;
    return isAncestorRelation(relation) || relation in OTHER_RELATIVES
        ? relation
        : 'relative';
};

export const savedPhotoItems = (
    urls: string[] | null | undefined,
    // For certificates & relatives' photos: image URL => who it shows, and the ones shown on the pedigree
    relations?: Record<string, string> | null,
    publicUrls?: string[] | null,
): PhotoItem[] =>
    (urls ?? []).map((url) => ({
        key: key(),
        src: url,
        saved: url,
        file: null,
        relation: knownRelation(relations?.[url]),
        public: publicUrls?.includes(url) ?? false,
    }));

export const newPhotoItem = (file: File): PhotoItem => ({
    key: key(),
    src: URL.createObjectURL(file),
    saved: null,
    file,
});

// The files to upload from a photo list, in order
export const photoFiles = (items: PhotoItem[]): File[] =>
    items.flatMap((item) => (item.file ? [item.file] : []));

// The photos to keep when editing, in order (JSON): saved URLs, and "new:N" for the Nth uploaded file
export const photoOrder = (items: PhotoItem[]): string => {
    let uploaded = 0;
    return JSON.stringify(
        items.map((item) => (item.file ? `new:${uploaded++}` : item.saved)),
    );
};

// Who each photo shows and whether it is on the pedigree (JSON list of {ref, relation, public}), refs as in photoOrder
export const photoDetails = (items: PhotoItem[]): string => {
    let uploaded = 0;
    return JSON.stringify(
        items.map((item) => ({
            ref: item.file ? `new:${uploaded++}` : item.saved,
            relation: item.relation ?? null,
            public: item.public ?? false,
        })),
    );
};

// Phone photos are often 5-10 MB, while the server keeps photos at most 1200 px wide. Shrinking them before upload
// keeps a save with many photos well within the server's upload limits.
const MAX_EDGE = 2000;
const QUALITY = 0.88;
const SMALL_ENOUGH = 1.5 * 1024 * 1024;

export const shrinkImage = async (file: File): Promise<File> => {
    if (!file.type.startsWith('image/')) return file;

    let bitmap: ImageBitmap;
    try {
        bitmap = await createImageBitmap(file);
    } catch {
        // A format the browser can't read: leave it to the server to accept or reject
        return file;
    }

    try {
        const scale = Math.min(
            1,
            MAX_EDGE / Math.max(bitmap.width, bitmap.height),
        );
        if (scale === 1 && file.size <= SMALL_ENOUGH) return file;

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        const context = canvas.getContext('2d');
        if (!context) return file;

        // Transparent PNGs get a white background rather than black
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', QUALITY),
        );
        if (!blob || blob.size >= file.size) return file;

        return new File([blob], `${file.name.replace(/\.[^.]+$/, '')}.jpg`, {
            type: 'image/jpeg',
            lastModified: file.lastModified,
        });
    } catch {
        return file;
    } finally {
        bitmap.close();
    }
};
