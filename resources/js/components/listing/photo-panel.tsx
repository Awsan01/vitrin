import { router } from '@inertiajs/react';
import { Download, RefreshCw, TriangleAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { plural } from '@/lib/utils';
import {
    download as downloadPhotos,
    regenerate,
} from '@/routes/listings/photos';
import type { ListingDetail, ListingPhoto } from '@/types';

export function PhotoPanel({
    listing,
    photoCost,
}: {
    listing: ListingDetail;
    photoCost: number;
}) {
    const hasPhotos = listing.photos.length > 0;
    const anyReady = listing.photos.some(
        (photo) => photo.status === 'completed',
    );

    return (
        <section aria-labelledby="photos-heading" className="space-y-4">
            <div className="flex items-center justify-between gap-3">
                <h2 id="photos-heading" className="font-semibold">
                    Photos
                </h2>
                {anyReady && (
                    <Button variant="ghost" size="sm" asChild>
                        <a href={downloadPhotos.url(listing.id)}>
                            <Download />
                            Download all
                        </a>
                    </Button>
                )}
            </div>

            <figure className="overflow-hidden rounded-xl border bg-sweep">
                <img
                    src={listing.source_url}
                    alt={`Original photo of ${listing.name}`}
                    className="aspect-square w-full object-contain p-4"
                />
                <figcaption className="border-t bg-background px-3 py-2 text-xs text-muted-foreground">
                    Your photo
                </figcaption>
            </figure>

            {hasPhotos && (
                <ul className="grid grid-cols-2 gap-3">
                    {listing.photos.map((photo) => (
                        <li key={photo.id}>
                            <PhotoTile
                                photo={photo}
                                listingId={listing.id}
                                photoCost={photoCost}
                                canRegenerate={listing.facts !== null}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function PhotoTile({
    photo,
    listingId,
    photoCost,
    canRegenerate,
}: {
    photo: ListingPhoto;
    listingId: string;
    photoCost: number;
    canRegenerate: boolean;
}) {
    const busy = photo.status === 'pending' || photo.status === 'processing';

    const regenerateLabel = `Make the ${photo.style_label} photo again (${plural(photoCost, 'credit')})`;
    const regenerateButton = canRegenerate && !busy && (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-7"
            title={regenerateLabel}
            aria-label={regenerateLabel}
            onClick={() =>
                router.post(
                    regenerate.url({ listing: listingId, photo: photo.id }),
                    {},
                    { preserveScroll: true },
                )
            }
        >
            <RefreshCw />
        </Button>
    );

    return (
        <figure className="group overflow-hidden rounded-xl border">
            <div className="relative aspect-square bg-sweep">
                {photo.status === 'completed' && photo.url ? (
                    <a href={photo.url} target="_blank" rel="noreferrer">
                        <img
                            src={photo.url}
                            alt={photo.style_label}
                            loading="lazy"
                            className="size-full object-cover"
                        />
                    </a>
                ) : busy ? (
                    <div className="flex size-full flex-col items-center justify-center gap-2 text-xs text-muted-foreground motion-safe:animate-pulse">
                        <Spinner />
                        Creating photo
                    </div>
                ) : photo.error ? (
                    <div className="flex size-full flex-col items-center justify-center gap-2 p-3 text-center text-xs text-destructive-foreground">
                        <TriangleAlert className="size-4" />
                        {photo.error}
                    </div>
                ) : (
                    // Failed along with the whole listing; the notice at the
                    // top of the page explains why.
                    <div className="flex size-full items-center justify-center p-3 text-center text-xs text-muted-foreground">
                        Not created
                    </div>
                )}
            </div>

            <figcaption className="flex items-center justify-between gap-1 border-t px-2 py-1.5">
                <span className="line-clamp-2 text-xs leading-tight font-medium">
                    {photo.style_label}
                </span>
                <span className="flex shrink-0 items-center gap-0.5">
                    {photo.download_url && (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            asChild
                        >
                            <a
                                href={photo.download_url}
                                aria-label={`Download ${photo.style_label}`}
                            >
                                <Download />
                            </a>
                        </Button>
                    )}
                    {regenerateButton}
                </span>
            </figcaption>
        </figure>
    );
}
