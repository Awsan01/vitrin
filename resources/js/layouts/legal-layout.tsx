import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { SiteFooter } from '@/components/site-footer';
import { home } from '@/routes';

/**
 * Public layout for the terms, privacy policy and refund policy.
 */
export default function LegalLayout({ children }: { children: ReactNode }) {
    const { name } = usePage().props;

    return (
        <div className="min-h-screen bg-background text-foreground">
            <header className="mx-auto flex max-w-6xl items-center px-5 py-5 md:px-8">
                <Link
                    href={home()}
                    className="flex items-center gap-2.5 rounded-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <AppLogoIcon className="size-7 fill-primary" />
                    <span className="text-lg font-semibold tracking-tight">
                        {name}
                    </span>
                </Link>
            </header>
            <main className="mx-auto max-w-3xl px-5 pt-6 pb-16 md:px-8">
                <article className="text-[0.95rem] leading-relaxed [&_a]:underline [&_a]:underline-offset-2 [&_h1]:text-3xl [&_h1]:font-semibold [&_h1]:tracking-tight [&_h2]:mt-10 [&_h2]:text-lg [&_h2]:font-semibold [&_li]:mt-1.5 [&_p]:mt-3 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:ps-5">
                    {children}
                </article>
            </main>
            <SiteFooter />
        </div>
    );
}
