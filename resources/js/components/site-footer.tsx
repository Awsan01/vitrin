import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import { privacy, refunds, terms } from '@/routes/legal';

/**
 * The public footer. Paddle's website review requires the terms, privacy
 * policy and refund policy to be reachable from every public page.
 */
export function SiteFooter() {
    const { name, business } = usePage().props;

    return (
        <footer className="mx-auto max-w-6xl px-5 py-8 text-sm text-muted-foreground md:px-8">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <span className="flex items-center gap-2">
                    <AppLogoIcon className="size-4 fill-current" />
                    {name}
                </span>
                <nav
                    aria-label="Policies"
                    className="flex flex-wrap gap-x-5 gap-y-2"
                >
                    <Link
                        href={`${home().url}#pricing`}
                        className="hover:text-foreground"
                    >
                        Pricing
                    </Link>
                    <Link href={terms()} className="hover:text-foreground">
                        Terms
                    </Link>
                    <Link href={privacy()} className="hover:text-foreground">
                        Privacy
                    </Link>
                    <Link href={refunds()} className="hover:text-foreground">
                        Refunds
                    </Link>
                    {business.email && (
                        <a
                            href={`mailto:${business.email}`}
                            className="hover:text-foreground"
                        >
                            Contact
                        </a>
                    )}
                </nav>
            </div>
            <p className="mt-4 text-xs">
                © {new Date().getFullYear()} {business.legal_name}. Payments are
                processed by Paddle.com, our merchant of record.
            </p>
        </footer>
    );
}
