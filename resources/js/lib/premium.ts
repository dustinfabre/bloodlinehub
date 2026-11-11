import { show as premiumFeature } from '@/routes/premium';
import type { AppPageProps } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// What only Premium accounts get, as in app/Support/PremiumFeature.php. Everything else is free.
export type PremiumFeatureKey =
    | 'pairing-analysis'
    | 'loft-check'
    | 'pedigree-sharing'
    | 'pedigree-export'
    | 'pedigree-contact'
    | 'pedigree-copy';

export const PREMIUM_FEATURES: { key: PremiumFeatureKey; title: string }[] = [
    { key: 'pairing-analysis', title: 'Pairing Analysis and breeding plan' },
    { key: 'loft-check', title: 'Loft Check' },
    { key: 'pedigree-sharing', title: 'Online pedigrees with a QR code' },
    { key: 'pedigree-export', title: 'Save pedigrees as an image or PDF' },
    { key: 'pedigree-contact', title: 'Your contact details on pedigrees' },
    { key: 'pedigree-copy', title: 'Copy a pedigree from another bird' },
];

// What every account gets
export const FREE_FEATURES = [
    'Unlimited pigeons and pairings',
    'Printable pedigrees',
    'Lineage',
    'Locations',
    'OLR, Clubs, Sales & Auctions',
];

// Printed on pedigrees made without Premium, where the QR code would be
export const GENERATED_AT = 'bloodlinehub.com';

export const usePremium = () => {
    const page = usePage<AppPageProps>();
    const premium = computed(() => !!page.props.auth?.premium);

    // Run `action` with Premium; without it, open the page that explains the feature and how to get Premium
    const withPremium = (feature: PremiumFeatureKey, action: () => void) => {
        if (premium.value) action();
        else router.visit(premiumFeature(feature).url);
    };

    return {
        premium,
        withPremium,
        featureUrl: (feature: PremiumFeatureKey) => premiumFeature(feature).url,
    };
};
