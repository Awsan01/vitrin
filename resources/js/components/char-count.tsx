import { cn } from '@/lib/utils';

/**
 * Character count against a marketplace limit. Counts code points so Arabic
 * and Turkish letters are counted the way marketplaces count them.
 */
export function charLength(text: string): number {
    return Array.from(text).length;
}

export function CharCount({
    text,
    max,
    className,
}: {
    text: string;
    max: number;
    className?: string;
}) {
    const length = charLength(text);
    const over = length > max;

    return (
        <span
            className={cn(
                'text-xs tabular-nums',
                over
                    ? 'font-medium text-destructive-foreground'
                    : 'text-muted-foreground',
                className,
            )}
            title={`${length} of ${max} characters`}
        >
            {length}/{max}
        </span>
    );
}
