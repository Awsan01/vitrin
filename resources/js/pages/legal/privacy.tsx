import { Head, usePage } from '@inertiajs/react';

export default function Privacy() {
    const { name, business } = usePage().props;

    return (
        <>
            <Head title="Privacy policy" />

            <h1>Privacy policy</h1>
            <p className="text-sm text-muted-foreground">
                Last updated 28 October 2026
            </p>

            <p>
                This policy explains what personal data {name} collects, why,
                and what you can do about it. The data controller is{' '}
                {business.legal_name}
                {business.address ? `, ${business.address}` : ''}
                {business.country ? `, ${business.country}` : ''}.
                {business.email && (
                    <>
                        {' '}
                        Questions and requests:{' '}
                        <a href={`mailto:${business.email}`}>
                            {business.email}
                        </a>
                        .
                    </>
                )}
            </p>

            <h2>What we collect</h2>
            <ul>
                <li>
                    <strong>Account details:</strong> your name, email address
                    and password (stored only as a secure hash), and your
                    two-factor authentication settings if you turn it on.
                </li>
                <li>
                    <strong>Your content:</strong> the product photos you
                    upload, the product names and notes you add, and the
                    listings and photos we generate for you. Uploaded photos are
                    re-saved without their metadata, such as GPS location.
                </li>
                <li>
                    <strong>Credits and purchases:</strong> your credit history,
                    and for each purchase the pack, amount, currency, date and
                    Paddle's transaction and customer IDs. Paddle collects your
                    payment details; we never see or store your card number.
                </li>
                <li>
                    <strong>Technical data:</strong> your IP address and browser
                    details, stored with your login session, and short-lived
                    server logs used to keep the service secure and fix errors.
                </li>
            </ul>

            <h2>Why we use it</h2>
            <ul>
                <li>
                    To provide the service you signed up for: analysing your
                    photos, writing listings, making photos, and keeping your
                    credit balance (performance of our contract with you).
                </li>
                <li>
                    To keep {name} secure and prevent abuse, such as fake
                    accounts created for free credits (our legitimate
                    interests).
                </li>
                <li>
                    To process payments and keep the records that tax and
                    accounting law require (legal obligation).
                </li>
                <li>
                    To send emails about your account, such as address
                    verification and password resets. We don't send marketing
                    emails without your consent.
                </li>
            </ul>

            <h2>Who we share it with</h2>
            <p>
                We don't sell your data. We share it only with providers that
                help us run {name}:
            </p>
            <ul>
                <li>
                    <strong>Google</strong> (Gemini API), which analyses your
                    photos and generates the listings and photos.
                </li>
                <li>
                    <strong>Paddle</strong>, our reseller and merchant of
                    record, which processes payments and handles invoices, taxes
                    and refunds. Paddle's own privacy policy covers the data you
                    give it at checkout.
                </li>
                <li>
                    Our <strong>hosting provider</strong>, which stores the app
                    and its data, and our <strong>email provider</strong>, which
                    delivers account emails.
                </li>
            </ul>
            <p>
                Some of these providers process data outside your country,
                including in the European Union, the United Kingdom and the
                United States. They do so under contracts that require them to
                protect it.
            </p>

            <h2>How long we keep it</h2>
            <ul>
                <li>
                    Your account, content and credit history are kept while your
                    account exists. Deleting your account in the settings
                    removes them, including all uploaded and generated photos.
                </li>
                <li>
                    Checkouts that were opened but never paid are deleted after
                    30 days. Server logs are kept for up to 14 days.
                </li>
                <li>
                    Paddle keeps payment records for as long as tax law
                    requires.
                </li>
            </ul>

            <h2>Cookies</h2>
            <p>
                {name} only uses cookies that it needs to work: a session cookie
                that keeps you logged in, a security token that protects forms
                (XSRF-TOKEN), and two preferences (appearance and sidebar
                state). We don't use advertising or tracking cookies. When you
                pay, Paddle's checkout may set its own cookies to process the
                payment and prevent fraud.
            </p>

            <h2>Your rights</h2>
            <p>
                You can ask us to access, correct, delete or export your data,
                or to restrict or object to how we use it. Much of this you can
                do yourself in your settings; for anything else, email us. We
                reply within 30 days.
            </p>
            <ul>
                <li>
                    In the European Union and the United Kingdom, you can also
                    complain to your data protection authority.
                </li>
                <li>
                    In Türkiye, you have the rights in Article 11 of the
                    Personal Data Protection Law (KVKK, No. 6698). Send requests
                    to us first; you can then complain to the Personal Data
                    Protection Authority (KVKK).
                </li>
            </ul>

            <h2>Security</h2>
            <p>
                Connections are encrypted (HTTPS), passwords are hashed, photos
                are stored privately and only shown to their owner, and you can
                turn on two-factor authentication.
            </p>

            <h2>Children</h2>
            <p>{name} is not meant for anyone under 18.</p>

            <h2>Changes</h2>
            <p>
                If we change this policy in a significant way, we will tell you
                by email or in the app.
            </p>
        </>
    );
}
