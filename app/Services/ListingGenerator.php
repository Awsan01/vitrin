<?php

namespace App\Services;

use App\Data\NewListing;
use App\Enums\GenerationStatus;
use App\Enums\ListingStatus;
use App\Jobs\AnalyzeListing;
use App\Jobs\GenerateListingPhoto;
use App\Jobs\WriteListingCopy;
use App\Models\Listing;
use App\Models\ListingCopy;
use App\Models\ListingPhoto;
use App\Models\User;
use App\Support\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The listing workflow in one place: start a generation, run a failed one
 * again, regenerate single items, and fail items with an automatic refund.
 *
 * Flow: start() stores the photo, charges credits and queues AnalyzeListing.
 * That job extracts product facts, then queues one WriteListingCopy per
 * marketplace and language and one GenerateListingPhoto per photo style.
 *
 * Methods that charge for existing items re-check them under a row lock
 * first, so a double click can't charge twice.
 */
class ListingGenerator
{
    public function __construct(protected CreditLedger $credits) {}

    /**
     * Total credits for a generation.
     */
    public static function cost(int $copies, int $photos): int
    {
        return $copies * config('vitrin.credits.copy') + $photos * config('vitrin.credits.photo');
    }

    /**
     * "4 listings and 2 photos", for the credit history.
     */
    public static function describe(int $copies, int $photos): string
    {
        return collect([
            $copies > 0 ? $copies.' '.Str::plural('listing', $copies) : null,
            $photos > 0 ? $photos.' '.Str::plural('photo', $photos) : null,
        ])->filter()->implode(' and ');
    }

    public function start(User $user, NewListing $input): Listing
    {
        $image = ProductImage::normalize($input->photo->getRealPath(), config('vitrin.uploads.max_dimension'));
        $copyCost = config('vitrin.credits.copy');
        $photoCost = config('vitrin.credits.photo');

        $listing = DB::transaction(function () use ($user, $input, $image, $copyCost, $photoCost) {
            $listing = $user->listings()->make([
                'name' => $input->name,
                'notes' => $input->notes,
                'status' => ListingStatus::Queued,
                'source_path' => '',
            ]);
            $listing->id = (string) Str::ulid();
            $listing->source_path = "{$listing->storageDirectory()}/source.jpg";
            $listing->save();

            foreach ($input->marketplaces as $marketplace) {
                foreach ($input->languages as $language) {
                    $listing->copies()->create([
                        'marketplace' => $marketplace,
                        'language' => $language,
                        'cost' => $copyCost,
                        'status' => GenerationStatus::Pending,
                    ]);
                }
            }

            foreach ($input->photoStyles as $style) {
                $listing->photos()->create([
                    'style' => $style,
                    'cost' => $photoCost,
                    'status' => GenerationStatus::Pending,
                ]);
            }

            $photos = count($input->photoStyles);
            $this->credits->spend(
                $user,
                self::cost($input->copyCount(), $photos),
                self::describe($input->copyCount(), $photos),
                $listing,
            );

            Storage::disk(Listing::disk())->put($listing->source_path, $image);

            return $listing;
        });

        AnalyzeListing::dispatch($listing);

        return $listing;
    }

    /**
     * Run a listing again after its photo couldn't be analyzed, with the same
     * photo, marketplaces, languages and photo styles. Returns false if the
     * listing can't run again, for example because a retry already started.
     */
    public function retry(Listing $listing): bool
    {
        $copyCost = config('vitrin.credits.copy');
        $photoCost = config('vitrin.credits.photo');

        $started = DB::transaction(function () use ($listing, $copyCost, $photoCost) {
            $locked = Listing::query()->whereKey($listing->getKey())->lockForUpdate()->first();

            if ($locked === null || ! $locked->canRetry()) {
                return false;
            }

            $copies = ListingCopy::query()->where('listing_id', $locked->id);
            $photos = ListingPhoto::query()->where('listing_id', $locked->id);
            $copyCount = $copies->count();
            $photoCount = $photos->count();

            $this->credits->spend(
                $locked->user,
                self::cost($copyCount, $photoCount),
                'Tried again: '.self::describe($copyCount, $photoCount),
                $locked,
            );

            $copies->update(['status' => GenerationStatus::Pending, 'cost' => $copyCost, 'error' => null]);
            $photos->update(['status' => GenerationStatus::Pending, 'cost' => $photoCost, 'error' => null]);
            $locked->update(['status' => ListingStatus::Queued, 'error' => null]);

            return true;
        });

        if ($started) {
            AnalyzeListing::dispatch($listing);
        }

        return $started;
    }

    /**
     * Write one listing again. Returns false while it is still being written.
     */
    public function regenerateCopy(ListingCopy $copy): bool
    {
        $cost = config('vitrin.credits.copy');

        $started = DB::transaction(function () use ($copy, $cost) {
            $locked = ListingCopy::query()->whereKey($copy->getKey())->lockForUpdate()->first();

            if ($locked === null || ! $locked->status->isFinished()) {
                return false;
            }

            $this->credits->spend(
                $copy->listing->user,
                $cost,
                "Rewrote the {$copy->marketplace->label()} listing ({$copy->language->englishName()})",
                $copy->listing,
            );

            $locked->update(['status' => GenerationStatus::Pending, 'cost' => $cost, 'error' => null]);
            $copy->listing->update(['status' => ListingStatus::Generating]);

            return true;
        });

        if ($started) {
            WriteListingCopy::dispatch($copy);
        }

        return $started;
    }

    /**
     * Make one photo again. Returns false while it is still being made.
     */
    public function regeneratePhoto(ListingPhoto $photo): bool
    {
        $cost = config('vitrin.credits.photo');

        $started = DB::transaction(function () use ($photo, $cost) {
            $locked = ListingPhoto::query()->whereKey($photo->getKey())->lockForUpdate()->first();

            if ($locked === null || ! $locked->status->isFinished()) {
                return false;
            }

            $this->credits->spend(
                $photo->listing->user,
                $cost,
                "Made the {$photo->style->label()} photo again",
                $photo->listing,
            );

            $locked->update(['status' => GenerationStatus::Pending, 'cost' => $cost, 'error' => null]);
            $photo->listing->update(['status' => ListingStatus::Generating]);

            return true;
        });

        if ($started) {
            GenerateListingPhoto::dispatch($photo);
        }

        return $started;
    }

    /**
     * Mark one item failed and give its credits back. The message says what
     * went wrong; a note about the refund is added to it.
     */
    public function failItem(ListingCopy|ListingPhoto $item, string $message): void
    {
        $item->refresh();

        if ($item->status->isFinished()) {
            return;
        }

        DB::transaction(function () use ($item, $message) {
            $refunded = match (true) {
                $item->cost === 1 => ' 1 credit was refunded.',
                $item->cost > 1 => " {$item->cost} credits were refunded.",
                default => '',
            };

            $item->update(['status' => GenerationStatus::Failed, 'error' => $message.$refunded]);

            $label = $item instanceof ListingCopy
                ? "{$item->marketplace->label()} listing ({$item->language->englishName()}) failed"
                : "{$item->style->label()} photo failed";

            $this->credits->refund($item->listing->user, $item->cost, $label, $item->listing);
        });

        $item->listing->syncStatus();
    }

    /**
     * Fail the whole listing (e.g. the photo couldn't be analyzed) and refund
     * every item that hadn't finished. The message is shown once, on the
     * listing, so the items themselves carry no error of their own.
     */
    public function failListing(Listing $listing, string $message): void
    {
        DB::transaction(function () use ($listing, $message) {
            $listing->refresh();

            $unfinished = $listing->copies->concat($listing->photos)
                ->reject(fn (ListingCopy|ListingPhoto $item) => $item->status->isFinished());

            foreach ($unfinished as $item) {
                $item->update(['status' => GenerationStatus::Failed, 'error' => null]);
            }

            $listing->update(['status' => ListingStatus::Failed, 'error' => $message]);

            $this->credits->refund(
                $listing->user,
                $unfinished->sum('cost'),
                'Listing could not be generated',
                $listing,
            );
        });
    }
}
