import { Head, Link, router } from '@inertiajs/react';
import { CheckoutEventNames } from '@paddle/paddle-js';
import type { CheckoutOpenOptions } from '@paddle/paddle-js';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { CreditPackCard } from '@/components/credit-pack-card';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useAppearance } from '@/hooks/use-appearance';
import { openPaddleCheckout, purchaseIdOf } from '@/lib/paddle';
import { cn, plural } from '@/lib/utils';
import { index as creditsIndex } from '@/routes/credits';
import {
    confirm as confirmPurchase,
    invoice as purchaseInvoice,
    store as startPurchase,
} from '@/routes/credits/purchases';
import { refunds, terms } from '@/routes/legal';
import { show } from '@/routes/listings';
import type {
    Costs,
    CreditPack,
    CreditPurchase,
    CreditTransaction,
    PaddleConfig,
} from '@/types';

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
    purchase: 'Credits bought',
    purchase_reversal: 'Purchase refunded',
};

// After a payment, the server checks it with Paddle after each of these
// waits until the credits are in, about a minute in all. Paddle usually
// completes a payment within seconds, and its webhook adds the credits too.
const CONFIRM_DELAYS_MS = [1500, 2500, 4000, 6000, 10_000, 15_000, 20_000];

const SLOW_CREDITS =
    'Your payment went through. The credits can take a few minutes to appear; refresh this page shortly.';

/** A payment the checkout reported, waiting for its credits. */
type PaidCheckout = { purchaseId: string; transactionId: string };

export default function Credits({
    balance,
    transactions,
    costs,
    packs,
    purchases,
    paddle,
    missingSettings,
}: {
    balance: number;
    transactions: CreditTransaction[];
    costs: Costs;
    packs: CreditPack[];
    purchases: CreditPurchase[];
    paddle: PaddleConfig | null;
    /** Settings payments still need; only sent while APP_DEBUG is on. */
    missingSettings: string[] | null;
}) {
    const { resolvedAppearance } = useAppearance();
    const [pendingPack, setPendingPack] = useState<string | null>(null);
    const [paid, setPaid] = useState<PaidCheckout | null>(null);

    // After a payment, ask the server to confirm it until the purchase
    // shows up as paid. The browser only sends the transaction ID; the
    // server reads the payment itself from Paddle.
    useEffect(() => {
        if (!paid) {
            return;
        }

        let attempt = 0;
        let finished = false;
        let timer: number | undefined;

        const check = () => {
            router.post(
                confirmPurchase(paid.purchaseId),
                { transaction_id: paid.transactionId },
                {
                    preserveScroll: true,
                    only: ['balance', 'transactions', 'purchases', 'credits'],
                    onSuccess: (page) => {
                        const purchase = (
                            page.props.purchases as CreditPurchase[]
                        ).find((p) => p.id === paid.purchaseId);

                        if (purchase) {
                            finished = true;
                            setPaid(null);
                            toast.success(
                                `${plural(purchase.credits, 'credit')} added. Thank you!`,
                            );
                        }
                    },
                    onFinish: () => {
                        if (finished) {
                            return;
                        }

                        attempt++;

                        if (attempt < CONFIRM_DELAYS_MS.length) {
                            timer = window.setTimeout(
                                check,
                                CONFIRM_DELAYS_MS[attempt],
                            );
                        } else {
                            finished = true;
                            setPaid(null);
                            toast.info(SLOW_CREDITS);
                        }
                    },
                },
            );
        };

        timer = window.setTimeout(check, CONFIRM_DELAYS_MS[0]);

        return () => {
            finished = true;
            window.clearTimeout(timer);
        };
    }, [paid]);

    const openCheckout = useCallback(
        async (options: CheckoutOpenOptions) => {
            if (!paddle) {
                return;
            }

            const opened = await openPaddleCheckout(
                paddle,
                {
                    ...options,
                    settings: {
                        displayMode: 'overlay',
                        theme: resolvedAppearance,
                        allowLogout: false,
                    },
                },
                (event) => {
                    if (
                        event.name !== CheckoutEventNames.CHECKOUT_COMPLETED ||
                        !event.data
                    ) {
                        return;
                    }

                    const purchaseId = purchaseIdOf(event.data);

                    if (purchaseId) {
                        setPaid({
                            purchaseId,
                            transactionId: event.data.transaction_id,
                        });
                    } else {
                        toast.info(SLOW_CREDITS);
                    }
                },
            );

            if (!opened) {
                toast.error(
                    "The checkout couldn't load. Check your connection or turn off content blockers for this site, then try again.",
                );
            }
        },
        [paddle, resolvedAppearance],
    );

    // Paddle's payment links (sent in receipts and reminder emails) point at
    // this page with ?_ptxn=txn_..., which reopens that transaction.
    const openedLink = useRef(false);

    useEffect(() => {
        const transactionId = new URLSearchParams(window.location.search).get(
            '_ptxn',
        );

        if (transactionId && !openedLink.current) {
            openedLink.current = true;
            void openCheckout({ transactionId });
        }
    }, [openCheckout]);

    function buy(pack: CreditPack) {
        let opening = false;
        setPendingPack(pack.key);

        router.post(
            startPurchase(),
            { pack: pack.key },
            {
                preserveScroll: true,
                onFlash: (flash) => {
                    if (!flash.checkout) {
                        return;
                    }

                    opening = true;
                    void openCheckout({
                        items: [
                            { priceId: flash.checkout.price_id, quantity: 1 },
                        ],
                        customer: { email: flash.checkout.email },
                        customData: { purchase_id: flash.checkout.purchase_id },
                    }).finally(() => setPendingPack(null));
                },
                onFinish: () => {
                    if (!opening) {
                        setPendingPack(null);
                    }
                },
            },
        );
    }

    const forSale = packs.filter((pack) => pack.purchasable);

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

                {paid && (
                    <p
                        role="status"
                        className="flex items-center gap-2 rounded-lg border px-4 py-3 text-sm"
                    >
                        <Spinner />
                        Payment received. Adding your credits…
                    </p>
                )}
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Buy credits"
                    description="One-time packs. Credits don't expire."
                />

                {forSale.length === 0 ? (
                    <div className="space-y-3 text-sm text-muted-foreground">
                        <p>Buying credits online isn't available yet.</p>
                        {missingSettings && missingSettings.length > 0 && (
                            <p className="rounded-lg border border-dashed px-4 py-3">
                                Setting up the site? Payments stay off until
                                these are set in <code>.env</code>:{' '}
                                <code className="font-medium text-foreground">
                                    {missingSettings.join(', ')}
                                </code>
                                . Then run <code>php artisan paddle:check</code>{' '}
                                to test the Paddle setup. This note only shows
                                while APP_DEBUG is on.
                            </p>
                        )}
                    </div>
                ) : (
                    <>
                        <ul className="grid gap-3 sm:grid-cols-3">
                            {forSale.map((pack) => (
                                <li key={pack.key}>
                                    <CreditPackCard
                                        pack={pack}
                                        costs={costs}
                                        action={
                                            <Button
                                                size="sm"
                                                variant={
                                                    pack.popular
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                disabled={
                                                    pendingPack !== null ||
                                                    paid !== null
                                                }
                                                onClick={() => buy(pack)}
                                            >
                                                {pendingPack === pack.key && (
                                                    <Spinner />
                                                )}
                                                Buy
                                            </Button>
                                        }
                                    />
                                </li>
                            ))}
                        </ul>
                        <p className="text-xs text-muted-foreground">
                            Payments are handled by Paddle, our reseller and
                            merchant of record, which also sends your receipt.
                            The checkout shows the final price for your country,
                            including any tax. See the{' '}
                            <Link
                                href={terms()}
                                className="underline underline-offset-2"
                            >
                                terms
                            </Link>{' '}
                            and{' '}
                            <Link
                                href={refunds()}
                                className="underline underline-offset-2"
                            >
                                refund policy
                            </Link>
                            .
                        </p>
                    </>
                )}
            </div>

            {purchases.length > 0 && (
                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Purchases"
                        description="Receipts are emailed to you by Paddle."
                    />
                    <ul className="divide-y text-sm">
                        {purchases.map((purchase) => (
                            <li
                                key={purchase.id}
                                className="flex items-baseline justify-between gap-4 py-2.5"
                            >
                                <span>
                                    <span className="block font-medium">
                                        {purchase.pack} pack,{' '}
                                        {plural(purchase.credits, 'credit')}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {purchase.completed_at && (
                                            <time
                                                dateTime={purchase.completed_at}
                                            >
                                                {dateFormat.format(
                                                    new Date(
                                                        purchase.completed_at,
                                                    ),
                                                )}
                                            </time>
                                        )}
                                        {purchase.invoice && (
                                            <>
                                                {purchase.completed_at && ', '}
                                                <a
                                                    href={purchaseInvoice.url(
                                                        purchase.id,
                                                    )}
                                                    className="underline underline-offset-2"
                                                >
                                                    download invoice
                                                </a>
                                            </>
                                        )}
                                    </span>
                                </span>
                                <span className="text-end">
                                    <span className="block font-medium tabular-nums">
                                        {purchase.amount}
                                    </span>
                                    <span
                                        className={cn(
                                            'block text-xs',
                                            purchase.status === 'completed'
                                                ? 'text-muted-foreground'
                                                : 'text-destructive-foreground',
                                        )}
                                    >
                                        {purchase.status_label}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

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
