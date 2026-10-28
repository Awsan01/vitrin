import { Head, usePage } from '@inertiajs/react';

export default function Refunds() {
    const { name, business } = usePage().props;

    return (
        <>
            <Head title="Refund policy" />

            <h1>Refund policy</h1>
            <p className="text-sm text-muted-foreground">
                Last updated 28 October 2026
            </p>

            <h2>Failed generations</h2>
            <p>
                If a listing or photo can't be generated, the credits it used go
                back to your balance automatically. You don't need to ask.
            </p>

            <h2>Credit packs</h2>
            <ul>
                <li>
                    You can ask for a refund within 14 days of buying a credit
                    pack.
                </li>
                <li>
                    If you haven't used any credits from the pack, you get a
                    full refund. If you have used some, we refund the share that
                    is still unused.
                </li>
                <li>
                    When a purchase is refunded, its credits are removed from
                    your balance.
                </li>
            </ul>

            <h2>How to ask</h2>
            <p>
                {business.email ? (
                    <>
                        Email us at{' '}
                        <a href={`mailto:${business.email}`}>
                            {business.email}
                        </a>{' '}
                        from the address on your account,
                    </>
                ) : (
                    'Contact us from the address on your account,'
                )}{' '}
                or contact Paddle, our reseller, through the link in your
                receipt or at <a href="https://paddle.net">paddle.net</a>.
                Paddle sends the refund to your original payment method; your
                bank may take 5 to 10 business days to show it.
            </p>

            <h2>Chargebacks</h2>
            <p>
                Please contact us before disputing a payment with your bank; we
                can usually help faster. If a payment is disputed, the credits
                from that purchase are removed and the account may be suspended
                while the dispute is open.
            </p>

            <h2>Your legal rights</h2>
            <p>
                This policy doesn't limit any rights you have under consumer
                law, such as the 14-day right to cancel digital purchases in the
                European Union and the United Kingdom. {name} is sold by Paddle,
                so Paddle's buyer terms also apply.
            </p>
        </>
    );
}
