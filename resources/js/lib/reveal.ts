import type { Directive } from 'vue';

// One observer shared by every revealed element on the page
let observer: IntersectionObserver | null = null;

const getObserver = (): IntersectionObserver => {
    observer ??= new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) {
                    continue;
                }

                (entry.target as HTMLElement).dataset.reveal = 'shown';
                observer?.unobserve(entry.target);
            }
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.12 },
    );

    return observer;
};

/**
 * Fades an element up once when it scrolls into view: `v-reveal` or `v-reveal="120"` (delay in ms, for staggering).
 * Elements already on screen at load are left alone, so nothing flashes on first paint or after SSR.
 */
export const vReveal: Directive<HTMLElement, number | undefined> = {
    mounted(el, binding) {
        if (
            typeof window === 'undefined' ||
            !('IntersectionObserver' in window) ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        if (el.getBoundingClientRect().top < window.innerHeight) {
            return;
        }

        if (binding.value) {
            el.style.setProperty('--reveal-delay', `${binding.value}ms`);
        }

        el.dataset.reveal = 'pending';
        getObserver().observe(el);
    },
    unmounted(el) {
        observer?.unobserve(el);
    },
};
