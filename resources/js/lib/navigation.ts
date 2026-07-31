import { dashboard } from '@/routes';
import { index as clubs } from '@/routes/clubs';
import { index as locations } from '@/routes/locations';
import { index as olrRaces } from '@/routes/olr-races';
import { index as pairings } from '@/routes/pairings';
import { index as pigeons } from '@/routes/pigeons';
import { index as sales } from '@/routes/sales';
import type { NavItem } from '@/types';
import {
    Bird,
    DollarSign,
    Flag,
    Heart,
    LayoutGrid,
    MapPin,
    Trophy,
} from 'lucide-vue-next';

export const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Pigeons', href: pigeons(), icon: Bird },
    { title: 'Breeding', href: pairings(), icon: Heart },
    { title: 'OLR Races', href: olrRaces(), icon: Trophy },
    { title: 'Clubs', href: clubs(), icon: Flag },
    { title: 'Sales & Auctions', href: sales(), icon: DollarSign },
    { title: 'Locations', href: locations(), icon: MapPin },
];

export const primaryMobileNavItems = mainNavItems.slice(0, 3);
export const secondaryMobileNavItems = mainNavItems.slice(3);
