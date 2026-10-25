import { Head, router, usePoll } from '@inertiajs/react';
import { FileSpreadsheet, RefreshCw, Trash2, TriangleAlert } from 'lucide-react';
import { useEffect, useState } from 'react';
import { CopyPanel } from '@/components/listing/copy-panel';
import { FactsPanel } from '@/components/listing/facts-panel';
import { PhotoPanel } from '@/components/listing/photo-panel';
import { ListingStatusBadge } from '@/components/listing-status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { plural } from '@/lib/utils';
import { dashboard } from '@/routes';
import { destroy, exportMethod, retry } from '@/routes/listings';
import type { Costs, ListingDetail } from '@/types';

const dateFormat = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export default function ShowListing({
    listing,
    costs,
}: {
    listing: ListingDetail;
    costs: Costs;
}) {
    // Refresh the results every few seconds while anything is still generating.
    const { start, stop } = usePoll(
        3000,
        { only: ['listing', 'credits'] },
        { autoStart: listing.is_processing },
    );

    useEffect(() => {
        if (listing.is_processing) {
            start();
        } else {
            stop();
        }
    }, [listing.is_processing, start, stop]);

    const canRegenerate = listing.facts !== null;
    const hasFinishedCopies = listing.copies.some(
        (copy) => copy.status === 'completed',
    );

    return (
        <>
            <Head title={listing.name} />

            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0 space-y-1.5">
                        <h1 className="text-2xl font-semibold tracking-tight first-letter:uppercase">
                            {listing.name}
                        </h1>
                        <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                            <ListingStatusBadge
                                status={listing.status}
                                label={listing.status_label}
                            />
                            {listing.created_at && (
                                <time dateTime={listing.created_at}>
                                    {dateFormat.format(
                                        new Date(listing.created_at),
                                    )}
                                </time>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {hasFinishedCopies && (
                            <Button variant="outline" asChild>
                                <a href={exportMethod.url(listing.id)}>
                                    <FileSpreadsheet />
                                    Export CSV
                                </a>
                            </Button>
                        )}
                        <DeleteListing listingId={listing.id} />
                    </div>
                </header>

                {listing.is_processing && (
                    <Progress
                        done={listing.progress.done}
                        total={listing.progress.total}
                        label={listing.status_label}
                    />
                )}

                {listing.status === 'failed' && listing.error && (
                    <FailedNotice listing={listing} costs={costs} />
                )}

                <div className="grid gap-x-10 gap-y-10 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
                    {/* On phones the listings come first; photos follow. */}
                    <aside className="order-last space-y-10 lg:order-none">
                        <PhotoPanel listing={listing} photoCost={costs.photo} />
                        {listing.facts && <FactsPanel facts={listing.facts} />}
                    </aside>

                    <CopyPanel
                        listingId={listing.id}
                        copies={listing.copies}
                        copyCost={costs.copy}
                        canRegenerate={canRegenerate}
                    />
                </div>
            </div>
        </>
    );
}

function Progress({
    done,
    total,
    label,
}: {
    done: number;
    total: number;
    label: string;
}) {
    const percent = total > 0 ? Math.round((done / total) * 100) : 0;

    return (
        <div
            className="rounded-xl bg-mint/60 p-4"
            role="status"
            aria-live="polite"
        >
            <div className="flex items-center justify-between gap-4 text-sm">
                <span className="font-medium text-mint-foreground">
                    {label}
                </span>
                <span className="text-mint-foreground/80 tabular-nums">
                    {done} of {total} ready
                </span>
            </div>
            <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-background/70">
                <div
                    className="h-full rounded-full bg-primary transition-[width] duration-500"
                    style={{ width: `${Math.max(percent, 4)}%` }}
                />
            </div>
            <p className="mt-2 text-xs text-mint-foreground/80">
                You can leave this page. Results keep generating and are saved
                to your listings.
            </p>
        </div>
    );
}

function FailedNotice({
    listing,
    costs,
}: {
    listing: ListingDetail;
    costs: Costs;
}) {
    const [retrying, setRetrying] = useState(false);
    const retryCost =
        listing.copies.length * costs.copy +
        listing.photos.length * costs.photo;

    const retryListing = () =>
        router.post(
            retry.url(listing.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setRetrying(true),
                onFinish: () => setRetrying(false),
            },
        );

    return (
        <div className="flex gap-3 rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm">
            <TriangleAlert className="mt-0.5 size-4 shrink-0 text-destructive-foreground" />
            <div>
                <p className="font-medium text-destructive-foreground">
                    We couldn't create this listing
                </p>
                <p className="text-muted-foreground">{listing.error}</p>
                {listing.can_retry && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="mt-3"
                        disabled={retrying}
                        onClick={retryListing}
                    >
                        <RefreshCw />
                        Try again ({plural(retryCost, 'credit')})
                    </Button>
                )}
            </div>
        </div>
    );
}

function DeleteListing({ listingId }: { listingId: string }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="icon" aria-label="Delete listing">
                    <Trash2 />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete this listing?</DialogTitle>
                <DialogDescription>
                    The photos and all generated text will be removed. Credits
                    already spent are not refunded.
                </DialogDescription>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        onClick={() => router.delete(destroy.url(listingId))}
                    >
                        Delete listing
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

ShowListing.layout = {
    breadcrumbs: [
        {
            title: 'Listings',
            href: dashboard(),
        },
    ],
};
