import { Head, Link, usePage } from '@inertiajs/react';
import { privacy, refunds } from '@/routes/legal';

export default function Terms() {
    const { name, business } = usePage().props;
    const operator = business.legal_name;

    return (
        <>
            <Head title="Terms of service" />

            <h1>Terms of service</h1>
            <p className="text-sm text-muted-foreground">
                Last updated 28 October 2026
            </p>

            <h2>1. Who we are</h2>
            <p>
                {name} is operated by {operator}
                {business.address ? `, ${business.address}` : ''}
                {business.country ? `, ${business.country}` : ''} ("we", "us").
                These terms are an agreement between you and us about your use
                of {name}.
                {business.email && (
                    <>
                        {' '}
                        You can reach us at{' '}
                        <a href={`mailto:${business.email}`}>
                            {business.email}
                        </a>
                        .
                    </>
                )}
            </p>

            <h2>2. The service</h2>
            <p>
                {name} writes marketplace listings (titles, bullet points,
                descriptions, keywords and categories) and creates product
                photos from a photo of your product, using artificial
                intelligence models.
            </p>

            <h2>3. Your account</h2>
            <ul>
                <li>You must be at least 18 years old to use {name}.</li>
                <li>
                    Give accurate details, keep your password safe, and tell us
                    if you think someone else has used your account. You are
                    responsible for what happens in your account.
                </li>
                <li>One person, one account. Accounts can't be shared.</li>
            </ul>

            <h2>4. Credits and payments</h2>
            <ul>
                <li>
                    Generations are paid for with credits. The app shows the
                    cost before you generate anything. New accounts may get free
                    credits.
                </li>
                <li>
                    Credit packs are one-time purchases at the price shown at
                    checkout, including any tax that applies in your country.
                    There are no subscriptions or automatic renewals.
                </li>
                <li>
                    Credits don't expire. They have no cash value and can't be
                    transferred, sold or exchanged for money, except for refunds
                    under our <Link href={refunds()}>refund policy</Link>.
                </li>
                <li>
                    If a generation fails, its credits are returned to your
                    balance automatically.
                </li>
                <li>
                    Our order process is conducted by our online reseller
                    Paddle.com. Paddle.com is the merchant of record for all our
                    orders. Paddle provides all customer service inquiries and
                    handles returns. Paddle's buyer terms apply to your payment.
                </li>
                <li>
                    We may change prices and credit costs in the future. Changes
                    never affect credits you have already bought.
                </li>
            </ul>

            <h2>5. Your content</h2>
            <ul>
                <li>
                    You keep ownership of the photos and information you upload,
                    and of the listings and photos made for you, as far as the
                    law allows.
                </li>
                <li>
                    You give us permission to store and process your content
                    only to provide the service, which includes sending it to
                    the AI providers described in our{' '}
                    <Link href={privacy()}>privacy policy</Link>.
                </li>
                <li>
                    You confirm that you have the right to use the photos you
                    upload, and that your products and listings follow the law
                    and the rules of the marketplaces where you sell.
                </li>
            </ul>

            <h2>6. AI-generated results</h2>
            <p>
                Listings and photos are made automatically and can contain
                mistakes, such as a wrong detail, size or material, or a photo
                that doesn't match your product exactly. Check everything before
                you publish it. You are responsible for what you publish.
                Marketplaces change their rules, and we can't guarantee that a
                marketplace will accept a listing or that it will lead to sales.
            </p>

            <h2>7. Acceptable use</h2>
            <p>You agree not to:</p>
            <ul>
                <li>
                    use {name} for products or content that are illegal,
                    counterfeit, or infringe someone else's rights;
                </li>
                <li>
                    upload photos of people without their permission, or content
                    that is hateful, sexual or violent;
                </li>
                <li>
                    try to break, overload or get around the limits of the
                    service, or access it by automated means other than the
                    features we provide;
                </li>
                <li>
                    create several accounts to get more free credits, or resell
                    access to {name}.
                </li>
            </ul>
            <p>
                We may suspend or close accounts that break these rules. If we
                do, we will tell you why, unless the law prevents it.
            </p>

            <h2>8. Availability and changes</h2>
            <p>
                We work to keep {name} running and secure, but we can't promise
                it will always be available or free of errors. We may change or
                stop features. If we decide to close the service, we will tell
                you by email at least 30 days before.
            </p>

            <h2>9. Closing your account</h2>
            <p>
                You can delete your account at any time in your settings. This
                deletes your listings and photos. Unused credits are lost when
                you delete your account, so ask for any refund you are entitled
                to first.
            </p>

            <h2>10. Liability</h2>
            <p>
                {name} is provided "as is". As far as the law allows, we are not
                liable for indirect or consequential losses, such as lost sales,
                lost profits, or penalties from a marketplace, and our total
                liability to you is limited to what you paid us in the 12 months
                before the claim. Nothing in these terms limits liability that
                can't be limited by law, or your rights as a consumer.
            </p>

            <h2>11. Changes to these terms</h2>
            <p>
                We may update these terms. If a change is significant, we will
                tell you by email or in the app before it takes effect. If you
                keep using {name} after that, the new terms apply.
            </p>

            <h2>12. Law</h2>
            <p>
                These terms are governed by the laws of{' '}
                {business.country ?? 'the country where we are based'}. If you
                are a consumer, you also keep the protection of the mandatory
                laws of the country where you live.
            </p>
        </>
    );
}
