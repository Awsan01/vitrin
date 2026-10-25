<?php

namespace App\Jobs;

use App\Ai\PhotoPrompt;
use App\Enums\GenerationStatus;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Services\ListingGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Image as ImageFile;
use Laravel\Ai\Image;
use Throwable;

class GenerateListingPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [20, 60];

    public int $timeout = 180;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public ListingPhoto $photo) {}

    public function handle(): void
    {
        $photo = $this->photo;
        $listing = $photo->listing;

        $photo->update(['status' => GenerationStatus::Processing, 'error' => null]);

        $image = Image::of(PhotoPrompt::for($photo->style, $listing))
            ->attachments([ImageFile::fromStorage($listing->source_path, Listing::disk())])
            ->square()
            ->quality(config('vitrin.ai.image.quality'))
            ->timeout(170)
            ->generate(config('vitrin.ai.image.provider'), config('vitrin.ai.image.model'))
            ->firstImage();

        $extension = match ($image->mime()) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        $path = "{$listing->storageDirectory()}/photos/{$photo->style->value}-".Str::lower(Str::random(8)).".{$extension}";
        Storage::disk(Listing::disk())->put($path, $image->content());

        $photo->replaceFile($path);
        $listing->syncStatus();
    }

    public function failed(?Throwable $exception): void
    {
        app(ListingGenerator::class)->failItem(
            $this->photo,
            "The AI service didn't return a photo.",
        );
    }
}
