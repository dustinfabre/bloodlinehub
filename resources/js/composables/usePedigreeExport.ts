import { PAPER_HEIGHT, PAPER_WIDTH } from '@/lib/pedigree';
import { ref } from 'vue';

// Last-resort stand-in for an image that fails to embed mid-export, so the export still succeeds
const TRANSPARENT_PIXEL =
    'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

const download = (href: string, filename: string) => {
    const link = document.createElement('a');
    link.href = href;
    link.download = filename;
    link.click();
};

// Images on hosts without CORS (e.g. the photo bucket) display fine but can't be copied into an export.
// Find them up front so they are left out instead of leaving an empty frame.
const findUnembeddableImages = async (paper: HTMLElement) => {
    const blocked = new Set<Node>();
    await Promise.all(
        Array.from(paper.querySelectorAll('img')).map(async (image) => {
            try {
                const response = await fetch(image.currentSrc || image.src, {
                    mode: 'cors',
                    cache: 'no-store',
                });
                if (!response.ok) blocked.add(image);
            } catch {
                blocked.add(image);
            }
        }),
    );
    return blocked;
};

export const usePedigreeExport = () => {
    const exporting = ref<'image' | 'pdf' | null>(null);

    const renderPng = async (paper: HTMLElement): Promise<string> => {
        // Loaded on demand to keep these libraries out of the main bundle
        const [{ toPng }, blocked] = await Promise.all([
            import('html-to-image'),
            findUnembeddableImages(paper),
        ]);
        const options = {
            width: PAPER_WIDTH,
            height: PAPER_HEIGHT,
            pixelRatio: 3,
            backgroundColor: '#ffffff',
            cacheBust: true,
            imagePlaceholder: TRANSPARENT_PIXEL,
            filter: (node: HTMLElement) => !blocked.has(node),
        };

        try {
            return await toPng(paper, options);
        } catch {
            // Web fonts can fail to embed (e.g. offline); fall back to system fonts instead of failing
            return await toPng(paper, { ...options, skipFonts: true });
        }
    };

    const saveImage = async (paper: HTMLElement, filename: string) => {
        exporting.value = 'image';
        try {
            download(await renderPng(paper), `${filename}.png`);
        } finally {
            exporting.value = null;
        }
    };

    const savePdf = async (paper: HTMLElement, filename: string) => {
        exporting.value = 'pdf';
        try {
            const [png, { jsPDF }] = await Promise.all([
                renderPng(paper),
                import('jspdf'),
            ]);
            const pdf = new jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: 'a4',
            });
            // Without compression jsPDF embeds raw pixels (~24 MB for this sheet)
            pdf.addImage(png, 'PNG', 0, 0, 297, 210, undefined, 'FAST');
            pdf.save(`${filename}.pdf`);
        } finally {
            exporting.value = null;
        }
    };

    return {
        exporting,
        saveImage,
        savePdf,
    };
};
