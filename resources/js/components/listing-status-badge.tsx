import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { ListingStatus } from '@/types';

const styles: Record<ListingStatus, string> = {
    queued: 'bg-muted text-muted-foreground',
    analyzing: 'bg-mint text-mint-foreground',
    generating: 'bg-mint text-mint-foreground',
    completed: 'bg-primary text-primary-foreground',
    partial: 'bg-saffron-wash text-saffron',
    failed: 'bg-destructive/10 text-destructive-foreground',
};

const processing: ListingStatus[] = ['queued', 'analyzing', 'generating'];

export function ListingStatusBadge({
    status,
    label,
    className,
}: {
    status: ListingStatus;
    label: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
                styles[status],
                className,
            )}
        >
            {processing.includes(status) && <Spinner className="size-3" />}
            {label}
        </span>
    );
}
