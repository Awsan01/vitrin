<?php

namespace App\Jobs;

use App\Ai\Agents\ProductAnalyst;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Services\ListingGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Image as ImageFile;
use Throwable;

class AnalyzeListing implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60];

    public int $timeout = 150;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Listing $listing) {}

    public function handle(ListingGenerator $generator): void
    {
        $listing = $this->listing;
        $listing->update(['status' => ListingStatus::Analyzing, 'error' => null]);

        $facts = (new ProductAnalyst)->prompt(
            ProductAnalyst::promptFor($listing),
            attachments: [ImageFile::fromStorage($listing->source_path, Listing::disk())],
            provider: config('vitrin.ai.text.provider'),
            model: config('vitrin.ai.text.model'),
        )->toArray();

        if (($facts['is_product'] ?? true) === false) {
            $reason = trim((string) ($facts['rejection_reason'] ?? '')) ?: 'This photo does not seem to show a product.';
            $generator->failListing($listing, "{$reason} Your credits were refunded.");

            return;
        }

        $listing->update([
            'product_facts' => $facts,
            'name' => $listing->name ?: Str::limit(Str::ucfirst($facts['product_type'] ?? 'Product'), 120, ''),
            'status' => ListingStatus::Generating,
        ]);

        foreach ($listing->copies as $copy) {
            WriteListingCopy::dispatch($copy);
        }

        foreach ($listing->photos as $photo) {
            GenerateListingPhoto::dispatch($photo);
        }

        $listing->syncStatus();
    }

    public function failed(?Throwable $exception): void
    {
        app(ListingGenerator::class)->failListing(
            $this->listing,
            "The AI service couldn't analyze this photo. Your credits were refunded. Try again in a few minutes.",
        );
    }
}
