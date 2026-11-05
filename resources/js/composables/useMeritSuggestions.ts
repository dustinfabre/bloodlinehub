import { useToast } from '@/composables/useToast';
import { accept, dismiss } from '@/routes/merit-suggestions';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/** Add or dismiss merit suggestions by key; the page reloads its suggestions afterwards. */
export const useMeritSuggestions = () => {
    const { success, error } = useToast();
    const busy = ref<string | null>(null);

    const send = (
        url: string,
        keys: string[],
        done: string,
        busyKey: string,
    ) => {
        if (!keys.length) return;
        router.post(
            url,
            { keys },
            {
                preserveScroll: true,
                onStart: () => (busy.value = busyKey),
                onFinish: () => (busy.value = null),
                onSuccess: () => success(done),
                onError: () => error('Something went wrong. Try again.'),
            },
        );
    };

    const add = (keys: string[], busyKey = keys.join()) =>
        send(
            accept().url,
            keys,
            keys.length === 1 ? 'Merit added' : `${keys.length} merits added`,
            busyKey,
        );

    const skip = (keys: string[], busyKey = `x:${keys.join()}`) =>
        send(dismiss().url, keys, 'Suggestion dismissed', busyKey);

    return { add, skip, busy };
};
