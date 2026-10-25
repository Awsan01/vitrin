import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { cn, plural } from '@/lib/utils';
import { index as creditsIndex } from '@/routes/credits';
import { show } from '@/routes/listings';
import type { Costs, CreditTransaction } from '@/types';

const dateFormat = new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
});

const reasonLabels: Record<string, string> = {
    signup: 'Welcome credits',
    generation: 'Generation',
    refund: 'Refund',
    grant: 'Added credits',
};

export default function Credits({
    balance,
    transactions,
    costs,
}: {
    balance: number;
    transactions: CreditTransaction[];
    costs: Costs;
}) {
    return (
        <>
            <Head title="Credits" />

            <h1 className="sr-only">Credits</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Credits"
                    description="Credits pay for generations. Anything that fails is refunded automatically."
                />

                <div className="flex flex-wrap items-end gap-x-10 gap-y-4 rounded-xl bg-mint/60 p-5">
                    <div>
                        <p className="text-sm text-mint-foreground">Balance</p>
                        <p className="text-4xl font-semibold tracking-tight text-mint-foreground tabular-nums">
                            {balance}
                        </p>
                    </div>
                    <dl className="grid grid-cols-[auto_auto] gap-x-4 gap-y-1 text-sm text-mint-foreground">
                        <dt>One listing (a marketplace in a language)</dt>
                        <dd className="font-medium tabular-nums">
                            {plural(costs.copy, 'credit')}
                        </dd>
                        <dt>One photo</dt>
                        <dd className="font-medium tabular-nums">
                            {plural(costs.photo, 'credit')}
                        </dd>
                    </dl>
                </div>
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="History"
                    description="Your last 50 credit changes."
                />

                {transactions.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nothing yet. Credits you spend or receive will show up
                        here.
                    </p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="sr-only">
                            <tr>
                                <th>Change</th>
                                <th>Amount</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {transactions.map((t) => (
                                <tr key={t.id}>
                                    <td className="py-2.5 pe-4">
                                        <span className="block font-medium">
                                            {reasonLabels[t.reason] ?? t.reason}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {t.description !==
                                                reasonLabels[t.reason] &&
                                                t.description}
                                            {t.listing_id && (
                                                <>
                                                    {t.description &&
                                                    t.description !==
                                                        reasonLabels[t.reason]
                                                        ? ', '
                                                        : ''}
                                                    <Link
                                                        href={show(
                                                            t.listing_id,
                                                        )}
                                                        className="underline underline-offset-2"
                                                    >
                                                        view listing
                                                    </Link>
                                                </>
                                            )}
                                        </span>
                                        {t.created_at && (
                                            <time
                                                dateTime={t.created_at}
                                                className="block text-xs text-muted-foreground"
                                            >
                                                {dateFormat.format(
                                                    new Date(t.created_at),
                                                )}
                                            </time>
                                        )}
                                    </td>
                                    <td
                                        className={cn(
                                            'py-2.5 text-end font-medium tabular-nums',
                                            t.amount > 0 && 'text-primary',
                                        )}
                                    >
                                        {t.amount > 0
                                            ? `+${t.amount}`
                                            : t.amount}
                                    </td>
                                    <td className="py-2.5 ps-4 text-end text-muted-foreground tabular-nums">
                                        {t.balance_after}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </>
    );
}

Credits.layout = {
    breadcrumbs: [
        {
            title: 'Credits',
            href: creditsIndex(),
        },
    ],
};
