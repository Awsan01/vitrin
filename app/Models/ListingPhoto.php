<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\PhotoStyle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One AI photo generated from the listing's source photo.
 *
 * @property int $id
 * @property string $listing_id
 * @property PhotoStyle $style
 * @property int $cost
 * @property GenerationStatus $status
 * @property string|null $path
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Listing $listing
 */
#[Fillable(['style', 'cost', 'status', 'path', 'error'])]
class ListingPhoto extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'style' => PhotoStyle::class,
            'status' => GenerationStatus::class,
            'cost' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * Replace the stored image, removing the previous file.
     */
    public function replaceFile(string $path): void
    {
        $previous = $this->path;

        $this->update([
            'path' => $path,
            'status' => GenerationStatus::Completed,
            'error' => null,
        ]);

        if ($previous && $previous !== $path) {
            Storage::disk(Listing::disk())->delete($previous);
        }
    }

    /**
     * A friendly download name such as "linen-shirt-white-background.png".
     */
    public function downloadName(): string
    {
        $extension = pathinfo((string) $this->path, PATHINFO_EXTENSION) ?: 'png';
        $base = str($this->listing->name ?: 'product')->slug()->limit(60, '');

        return "{$base}-".str_replace('_', '-', $this->style->value).".{$extension}";
    }
}
