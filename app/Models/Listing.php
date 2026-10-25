<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\ListingStatus;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $name
 * @property string|null $notes
 * @property string $source_path
 * @property ListingStatus $status
 * @property array<string, mixed>|null $product_facts
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, ListingCopy> $copies
 * @property-read Collection<int, ListingPhoto> $photos
 */
#[Fillable(['name', 'notes', 'source_path', 'status', 'product_facts', 'error'])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'product_facts' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ListingCopy, $this>
     */
    public function copies(): HasMany
    {
        return $this->hasMany(ListingCopy::class)->orderBy('id');
    }

    /**
     * @return HasMany<ListingPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ListingPhoto::class)->orderBy('id');
    }

    /**
     * The folder holding this listing's files on the Vitrin disk.
     */
    public function storageDirectory(): string
    {
        return "listings/{$this->id}";
    }

    public static function disk(): string
    {
        return config('vitrin.disk');
    }

    /**
     * A short product type to steer prompts, e.g. "women's linen shirt".
     */
    public function productType(): string
    {
        return $this->product_facts['product_type'] ?? $this->name ?? 'product';
    }

    /**
     * Whether the whole listing can run again: its photo couldn't be analyzed,
     * so nothing was generated. Single items that failed are retried one by one.
     */
    public function canRetry(): bool
    {
        return $this->status === ListingStatus::Failed && $this->product_facts === null;
    }

    /**
     * Recalculate the overall status once the analysis step has finished.
     */
    public function syncStatus(): void
    {
        $this->refresh();

        if ($this->status === ListingStatus::Failed || $this->product_facts === null) {
            return;
        }

        $items = $this->copies->pluck('status')->merge($this->photos->pluck('status'));

        $status = match (true) {
            $items->contains(fn (GenerationStatus $s) => ! $s->isFinished()) => ListingStatus::Generating,
            $items->every(fn (GenerationStatus $s) => $s === GenerationStatus::Failed) => ListingStatus::Failed,
            $items->contains(GenerationStatus::Failed) => ListingStatus::Partial,
            default => ListingStatus::Completed,
        };

        if ($status !== $this->status) {
            $this->update(['status' => $status]);
        }
    }

    /**
     * Remove the listing's files along with the record. The record goes
     * first, so a failed delete never leaves a listing without its files.
     */
    public function deleteWithFiles(): void
    {
        $this->delete();

        Storage::disk(self::disk())->deleteDirectory($this->storageDirectory());
    }
}
