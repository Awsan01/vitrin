import { ImagePlus, LayoutGrid } from 'lucide-react';
import { dashboard } from '@/routes';
import { create as createListing } from '@/routes/listings';
import type { NavItem } from '@/types';

/**
 * Main navigation, shared by the sidebar and header layouts.
 */
export const mainNavItems: NavItem[] = [
    {
        title: 'Listings',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'New listing',
        href: createListing(),
        icon: ImagePlus,
    },
];
