import type { ReactNode } from 'react';
import { cn, plural } from '@/lib/utils';
import type { Costs, CreditPack } from '@/types';

/**
 * One credit pack: what it holds, roughly what it buys, its price and an
 * action (buy, or sign up on the public page).
 */
export function CreditPackCard({
    pack,
    costs,
    action,
    className,
}: {
    pack: CreditPack;
    costs: Costs;
    action: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'relative flex h-full flex-col rounded-xl border p-4',
                pack.popular && 'border-primary ring-1 ring-primary',
                className,
            )}
        >
            {pack.popular && (
                <span className="absolute -top-2.5 left-4 rounded-full bg-primary px-2 py-0.5 text-xs font-medium text-primary-foreground">
                    Most popular
                </span>
            )}
            <p className="font-medium">{pack.name}</p>
            <p className="mt-1 text-2xl font-semibold tracking-tight tabular-nums">
                {plural(pack.credits, 'credit')}
            </p>
            <p className="mt-1 text-xs opacity-75">
                Up to {plural(Math.floor(pack.credits / costs.copy), 'listing')}{' '}
                or {plural(Math.floor(pack.credits / costs.photo), 'photo')}
            </p>
            <div className="mt-auto flex items-center justify-between gap-3 pt-5">
                <span className="text-lg font-semibold tabular-nums">
                    {pack.price}
                </span>
                {action}
            </div>
        </div>
    );
}
