import { Head, Link, usePage } from '@inertiajs/react';
import { ImagePlus } from 'lucide-react';
import { ListingStatusBadge } from '@/components/listing-status-badge';
import { Button } from '@/components/ui/button';
import { plural } from '@/lib/utils';
import { dashboard } from '@/routes';
import { create, show } from '@/routes/listings';
import type { ListingCard, Pagination } from '@/types';

const dateFormat = new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

export default function Dashboard({
    listings,
    pagination,
}: {
    listings: ListingCard[];
    pagination: Pagination;
}) {
    const { credits } = usePage().props;

    return (
        <>
            <Head title="Listings" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Listings
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {pagination.total === 0
                                ? 'Your generated listings will appear here.'
                                : `${plural(pagination.total, 'product')} so far. You have ${plural(credits ?? 0, 'credit')} left.`}
                        </p>
                    </div>

                    {pagination.total > 0 && (
                        <Button asChild>
                            <Link href={create()}>
                                <ImagePlus />
                                New listing
                            </Link>
                        </Button>
                    )}
                </header>

                {listings.length === 0 ? (
                    <EmptyState />
                ) : (
                    <ul className="grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        {listings.map((listing) => (
                            <li key={listing.id}>
                                <ListingCardView listing={listing} />
                            </li>
                        ))}
                    </ul>
                )}

                {pagination.last_page > 1 && (
                    <nav
                        className="flex items-center justify-between border-t pt-4 text-sm"
                        aria-label="Pagination"
                    >
                        <span className="text-muted-foreground">
                            Page {pagination.current_page} of{' '}
                            {pagination.last_page}
                        </span>
                        <div className="flex gap-2">
                            {pagination.prev_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={pagination.prev_url}>
                                        Newer
                                    </Link>
                                </Button>
                            )}
                            {pagination.next_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={pagination.next_url}>
                                        Older
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}

function ListingCardView({ listing }: { listing: ListingCard }) {
    return (
        <Link
            href={show(listing.id)}
            className="group block rounded-xl focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            <div className="relative aspect-[4/3] overflow-hidden rounded-xl bg-sweep">
                <img
                    src={listing.source_url}
                    alt=""
                    loading="lazy"
                    className="size-full object-contain p-4 transition-transform duration-300 group-hover:scale-[1.03] motion-reduce:transition-none"
                />
                <ListingStatusBadge
                    status={listing.status}
                    label={listing.status_label}
                    className="absolute top-3 left-3"
                />
            </div>

            <div className="mt-3 space-y-1">
                <h2 className="line-clamp-2 leading-snug font-medium group-hover:underline group-hover:underline-offset-4">
                    {listing.name}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {listing.marketplaces.join(', ')}
                </p>
                <p className="flex items-center gap-3 text-xs text-muted-foreground">
                    <span className="flex gap-1">
                        {listing.languages.map((language) => (
                            <span
                                key={language}
                                className="rounded border px-1 py-px font-medium uppercase"
                            >
                                {language}
                            </span>
                        ))}
                    </span>
                    {listing.photos_count > 0 && (
                        <span>
                            {listing.photos_count}{' '}
                            {listing.photos_count === 1 ? 'photo' : 'photos'}
                        </span>
                    )}
                    {listing.created_at && (
                        <time dateTime={listing.created_at} className="ml-auto">
                            {dateFormat.format(new Date(listing.created_at))}
                        </time>
                    )}
                </p>
            </div>
        </Link>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed px-6 py-20 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-mint text-mint-foreground">
                <ImagePlus className="size-6" />
            </div>
            <h2 className="mt-5 text-lg font-semibold">
                Turn your first product photo into listings
            </h2>
            <p className="mt-2 max-w-md text-sm text-muted-foreground">
                Upload one photo, choose where you sell, and get titles, bullet
                points, descriptions and keywords in English, Turkish and
                Arabic, plus new photos of the product.
            </p>
            <Button asChild className="mt-6">
                <Link href={create()}>Upload a product photo</Link>
            </Button>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Listings',
            href: dashboard(),
        },
    ],
};
