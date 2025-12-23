import { onMounted, ref } from 'vue';

type Appearance = 'light';

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

export function updateTheme(): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.remove('dark');
}

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    updateTheme();

    try {
        window.localStorage.setItem('appearance', 'light');
    } catch (_) {
        // no-op if storage unavailable
    }

    setCookie('appearance', 'light');
}

const appearance = ref<Appearance>('light');

export function useAppearance() {
    onMounted(() => {
        appearance.value = 'light';
        updateTheme();

        try {
            window.localStorage.setItem('appearance', 'light');
        } catch (_) {
            // no-op if storage unavailable
        }
    });

    function updateAppearance(): void {
        appearance.value = 'light';
        updateTheme();

        try {
            window.localStorage.setItem('appearance', 'light');
        } catch (_) {
            // no-op if storage unavailable
        }

        setCookie('appearance', 'light');
    }

    return {
        appearance,
        updateAppearance,
    };
}
