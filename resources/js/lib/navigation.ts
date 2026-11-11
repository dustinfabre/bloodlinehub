import { dashboard } from '@/routes';
import { index as clubs } from '@/routes/clubs';
import { index as lineage } from '@/routes/lineage';
import { index as locations } from '@/routes/locations';
import { index as loftChecks } from '@/routes/loft-checks';
import { index as olrRaces } from '@/routes/olr-races';
import { index as pairingAnalysis } from '@/routes/pairing-analysis';
import { index as pairings } from '@/routes/pairings';
import { index as pigeons } from '@/routes/pigeons';
import { index as sales } from '@/routes/sales';
import type { NavItem } from '@/types';
import {
    Bird,
    ClipboardCheck,
    DollarSign,
    Flag,
    FlaskConical,
    GitFork,
    Heart,
    LayoutGrid,
    MapPin,
    Trophy,
} from 'lucide-vue-next';

export const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Pigeons', href: pigeons(), icon: Bird },
    // Counted on the phone in the loft after training, so it sits in the mobile bottom bar
    {
        title: 'Loft Check',
        href: loftChecks(),
        icon: ClipboardCheck,
        premium: true,
    },
    { title: 'Breeding', href: pairings(), icon: Heart },
    { title: 'Lineage', href: lineage(), icon: GitFork },
    {
        title: 'Pairing Analysis',
        href: pairingAnalysis(),
        icon: FlaskConical,
        premium: true,
    },
    { title: 'OLR Races', href: olrRaces(), icon: Trophy },
    { title: 'Clubs', href: clubs(), icon: Flag },
    { title: 'Sales & Auctions', href: sales(), icon: DollarSign },
    { title: 'Locations', href: locations(), icon: MapPin },
];

export const primaryMobileNavItems = mainNavItems.slice(0, 4);
export const secondaryMobileNavItems = mainNavItems.slice(4);
