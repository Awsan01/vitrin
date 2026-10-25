<?php

namespace App\Jobs;

use App\Ai\Agents\ListingWriter;
use App\Enums\GenerationStatus;
use App\Models\ListingCopy;
use App\Services\ListingGenerator;
use App\Support\ListingCopyNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class WriteListingCopy implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 45];

    public int $timeout = 120;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public ListingCopy $copy) {}

    public function handle(): void
    {
        $copy = $this->copy;
        $listing = $copy->listing;

        if ($listing->product_facts === null) {
            throw new RuntimeException("Listing {$listing->id} has not been analyzed yet.");
        }

        $copy->update(['status' => GenerationStatus::Processing, 'error' => null]);

        $writer = new ListingWriter($copy->marketplace, $copy->language);

        $raw = $writer->prompt(
            $writer->promptFor($listing),
            provider: config('vitrin.ai.text.provider'),
            model: config('vitrin.ai.text.model'),
        )->toArray();

        $copy->update([
            ...ListingCopyNormalizer::normalize($raw, $copy->marketplace),
            'status' => GenerationStatus::Completed,
            'error' => null,
        ]);

        $listing->syncStatus();
    }

    public function failed(?Throwable $exception): void
    {
        app(ListingGenerator::class)->failItem(
            $this->copy,
            "The AI service didn't return a usable listing.",
        );
    }
}
