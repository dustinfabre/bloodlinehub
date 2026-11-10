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
}

let nextKey = 0;
const key = () => `photo-${++nextKey}`;

export const savedPhotoItems = (
    urls: string[] | null | undefined,
): PhotoItem[] =>
    (urls ?? []).map((url) => ({
        key: key(),
        src: url,
        saved: url,
        file: null,
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
