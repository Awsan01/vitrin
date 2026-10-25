import { router } from '@inertiajs/react';
import { RefreshCw, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { CharCount } from '@/components/char-count';
import { CopyButton } from '@/components/copy-button';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { cn, plural } from '@/lib/utils';
import { regenerate } from '@/routes/listings/copies';
import type { ListingCopy } from '@/types';

type MarketGroup = {
    value: string;
    label: string;
    copies: ListingCopy[];
};

function groupByMarketplace(copies: ListingCopy[]): MarketGroup[] {
    const groups = new Map<string, MarketGroup>();

    for (const copy of copies) {
        const group = groups.get(copy.marketplace) ?? {
            value: copy.marketplace,
            label: copy.marketplace_label,
            copies: [],
        };
        group.copies.push(copy);
        groups.set(copy.marketplace, group);
    }

    return [...groups.values()];
}

export function CopyPanel({
    listingId,
    copies,
    copyCost,
    canRegenerate,
}: {
    listingId: string;
    copies: ListingCopy[];
    copyCost: number;
    canRegenerate: boolean;
}) {
    const markets = groupByMarketplace(copies);
    const [marketValue, setMarketValue] = useState(markets[0]?.value ?? '');
    const [languageValue, setLanguageValue] = useState(
        copies[0]?.language ?? '',
    );

    const market = markets.find((m) => m.value === marketValue) ?? markets[0];

    if (!market) {
        return null;
    }

    const copy =
        market.copies.find((c) => c.language === languageValue) ??
        market.copies[0];

    return (
        <section aria-labelledby="copy-heading" className="min-w-0">
            <h2 id="copy-heading" className="sr-only">
                Listings
            </h2>

            <div
                role="tablist"
                aria-label="Marketplace"
                className="flex gap-1 overflow-x-auto border-b"
            >
                {markets.map((m) => {
                    const selected = m.value === market.value;
                    const pending = m.copies.some(
                        (c) =>
                            c.status === 'pending' || c.status === 'processing',
                    );

                    return (
                        <button
                            key={m.value}
                            type="button"
                            role="tab"
                            aria-selected={selected}
                            onClick={() => setMarketValue(m.value)}
                            className={cn(
                                '-mb-px flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                selected
                                    ? 'border-primary text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {m.label}
                            {pending && <Spinner className="size-3" />}
                        </button>
                    );
                })}
            </div>

            {market.copies.length > 1 && (
                <div
                    role="radiogroup"
                    aria-label="Language"
                    className="mt-4 inline-flex rounded-lg bg-muted p-1"
                >
                    {market.copies.map((c) => {
                        const selected = c.id === copy.id;

                        return (
                            <button
                                key={c.id}
                                type="button"
                                role="radio"
                                aria-checked={selected}
                                onClick={() => setLanguageValue(c.language)}
                                className={cn(
                                    'rounded-md px-3 py-1 text-sm transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                    selected
                                        ? 'bg-background font-medium shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <span dir={c.dir} lang={c.language}>
                                    {c.language_label}
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}

            <div className="mt-6">
                <CopyView
                    key={copy.id}
                    copy={copy}
                    listingId={listingId}
                    copyCost={copyCost}
                    canRegenerate={canRegenerate}
                />
            </div>
        </section>
    );
}

function CopyView({
    copy,
    listingId,
    copyCost,
    canRegenerate,
}: {
    copy: ListingCopy;
    listingId: string;
    copyCost: number;
    canRegenerate: boolean;
}) {
    const regenerateCopy = () =>
        router.post(
            regenerate.url({ listing: listingId, copy: copy.id }),
            {},
            { preserveScroll: true },
        );

    if (copy.status === 'pending' || copy.status === 'processing') {
        return (
            <div className="space-y-6" aria-busy="true">
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Spinner />
                    Writing the {copy.marketplace_label} listing in{' '}
                    {copy.language_label}
                </p>
                <Skeleton className="h-7 w-4/5" />
                <div className="space-y-2">
                    <Skeleton className="h-4 w-full" />
                    <Skeleton className="h-4 w-11/12" />
                    <Skeleton className="h-4 w-3/4" />
                </div>
                <Skeleton className="h-24 w-full" />
            </div>
        );
    }

    if (copy.status === 'failed') {
        const tryAgain = canRegenerate && (
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="mt-4"
                onClick={regenerateCopy}
            >
                <RefreshCw />
                Try again ({plural(copyCost, 'credit')})
            </Button>
        );

        // Without an error of its own, the copy failed along with the whole
        // listing, and the notice at the top of the page explains why.
        if (copy.error === null) {
            return (
                <div className="rounded-xl border border-dashed p-5">
                    <p className="text-sm text-muted-foreground">
                        This listing wasn't written.
                    </p>
                    {tryAgain}
                </div>
            );
        }

        return (
            <div className="rounded-xl border border-destructive/30 bg-destructive/5 p-5">
                <p className="flex items-center gap-2 font-medium text-destructive-foreground">
                    <TriangleAlert className="size-4" />
                    This listing wasn't written
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {copy.error}
                </p>
                {tryAgain}
            </div>
        );
    }

    const { limits } = copy;
    const textDir = { dir: copy.dir, lang: copy.language };
    const keywordsText = copy.keywords.join(limits.keywords_separator);

    return (
        <article className="space-y-1">
            {copy.warnings.length > 0 && (
                <div className="mb-5 rounded-xl bg-saffron-wash p-4 text-sm">
                    <p className="flex items-center gap-2 font-medium text-saffron">
                        <TriangleAlert className="size-4" />
                        Adjusted to fit {copy.marketplace_label}'s rules
                    </p>
                    <ul className="mt-2 list-disc space-y-0.5 ps-5 text-foreground/80">
                        {copy.warnings.map((warning) => (
                            <li key={warning}>{warning}</li>
                        ))}
                    </ul>
                </div>
            )}

            <Field
                label="Title"
                meta={
                    <CharCount text={copy.title ?? ''} max={limits.title_max} />
                }
                copyText={copy.title ?? ''}
            >
                <p {...textDir} className="text-lg leading-snug font-medium">
                    {copy.title}
                </p>
            </Field>

            {limits.highlight_label && copy.highlight && (
                <Field
                    label={limits.highlight_label}
                    meta={
                        limits.highlight_max ? (
                            <CharCount
                                text={copy.highlight}
                                max={limits.highlight_max}
                            />
                        ) : null
                    }
                    copyText={copy.highlight}
                >
                    <p {...textDir}>{copy.highlight}</p>
                </Field>
            )}

            {copy.bullets.length > 0 && (
                <Field label="Bullet points" copyText={copy.bullets.join('\n')}>
                    <ul
                        {...textDir}
                        className="list-disc space-y-1.5 ps-5 marker:text-primary"
                    >
                        {copy.bullets.map((bullet) => (
                            <li key={bullet}>{bullet}</li>
                        ))}
                    </ul>
                </Field>
            )}

            {copy.description && (
                <Field
                    label="Description"
                    copyText={copy.description}
                    extraAction={
                        copy.description_html ? (
                            <CopyButton
                                text={copy.description_html}
                                label="Copy HTML"
                            />
                        ) : null
                    }
                >
                    <FormattedText
                        text={copy.description}
                        className="me-auto max-w-prose space-y-3 leading-relaxed text-foreground/90"
                        {...textDir}
                    />
                </Field>
            )}

            {copy.keywords.length > 0 && (
                <Field
                    label={limits.keywords_label}
                    meta={
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {copy.keywords.length}
                        </span>
                    }
                    copyText={keywordsText}
                >
                    <ul {...textDir} className="flex flex-wrap gap-1.5">
                        {copy.keywords.map((keyword) => (
                            <li
                                key={keyword}
                                className="rounded-md bg-muted px-2 py-0.5 text-sm"
                            >
                                {keyword}
                            </li>
                        ))}
                    </ul>
                </Field>
            )}

            {copy.category && (
                <Field label="Category" copyText={copy.category}>
                    <p {...textDir} className="text-sm">
                        {copy.category}
                    </p>
                </Field>
            )}

            {canRegenerate && (
                <div className="flex flex-wrap items-center justify-between gap-3 pt-5">
                    <p className="text-sm text-muted-foreground">
                        Not quite right? Get a fresh version.
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={regenerateCopy}
                    >
                        <RefreshCw />
                        Rewrite ({plural(copyCost, 'credit')})
                    </Button>
                </div>
            )}
        </article>
    );
}

/**
 * Shows plain-text copy the way it will read: blank lines become paragraphs
 * and lines starting with "- " become a bulleted list.
 */
function FormattedText({
    text,
    className,
    dir,
    lang,
}: {
    text: string;
    className?: string;
    dir: string;
    lang: string;
}) {
    const blocks = text
        .split(/\n\s*\n/)
        .map((block) =>
            block
                .split('\n')
                .map((line) => line.trim())
                .filter(Boolean),
        )
        .filter((lines) => lines.length > 0);

    return (
        <div dir={dir} lang={lang} className={className}>
            {blocks.map((lines, index) =>
                lines.every((line) => /^[-•*]\s+/.test(line)) ? (
                    <ul
                        key={index}
                        className="list-disc space-y-1 ps-5 marker:text-primary"
                    >
                        {lines.map((line) => (
                            <li key={line}>{line.replace(/^[-•*]\s+/, '')}</li>
                        ))}
                    </ul>
                ) : (
                    <p key={index}>
                        {lines.map((line, i) => (
                            <span key={i}>
                                {i > 0 && <br />}
                                {line}
                            </span>
                        ))}
                    </p>
                ),
            )}
        </div>
    );
}

function Field({
    label,
    meta,
    copyText,
    extraAction,
    children,
}: {
    label: string;
    meta?: ReactNode;
    copyText: string;
    extraAction?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="border-b py-4 first:pt-0">
            <div className="mb-2 flex items-center justify-between gap-3">
                <h3 className="flex items-center gap-2 text-sm text-muted-foreground">
                    {label}
                    {meta}
                </h3>
                <div className="flex items-center">
                    {extraAction}
                    <CopyButton text={copyText} />
                </div>
            </div>
            {children}
        </section>
    );
}
