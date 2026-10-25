import { Link, usePage } from '@inertiajs/react';
import { Coins } from 'lucide-react';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { plural } from '@/lib/utils';
import { index as creditsIndex } from '@/routes/credits';

/**
 * The credit balance in the sidebar footer, linking to the usage history.
 */
export function NavCredits() {
    const { credits } = usePage().props;

    if (credits === null || credits === undefined) {
        return null;
    }

    const label = plural(credits, 'credit');

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton asChild tooltip={{ children: label }}>
                    <Link href={creditsIndex()} prefetch>
                        <Coins className="text-saffron" />
                        <span className="tabular-nums">{label}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
