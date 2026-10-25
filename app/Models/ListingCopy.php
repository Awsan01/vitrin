<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\Language;
use App\Enums\Marketplace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One generated listing: a marketplace in a language.
 *
 * @property int $id
 * @property string $listing_id
 * @property Marketplace $marketplace
 * @property Language $language
 * @property int $cost
 * @property GenerationStatus $status
 * @property string|null $title
 * @property string|null $highlight
 * @property list<string>|null $bullets
 * @property string|null $description
 * @property list<string>|null $keywords
 * @property string|null $category
 * @property list<string>|null $warnings
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Listing $listing
 */
#[Fillable([
    'marketplace', 'language', 'cost', 'status', 'title', 'highlight', 'bullets',
    'description', 'keywords', 'category', 'warnings', 'error',
])]
class ListingCopy extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'marketplace' => Marketplace::class,
            'language' => Language::class,
            'status' => GenerationStatus::class,
            'cost' => 'integer',
            'bullets' => 'array',
            'keywords' => 'array',
            'warnings' => 'array',
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
     * The description as simple, safe HTML for marketplaces that accept it.
     *
     * The model writes plain text (paragraphs, "- " list lines); converting it
     * here means no AI-written markup ever reaches a page.
     */
    public function descriptionHtml(): string
    {
        $blocks = preg_split("/\n\s*\n/", trim((string) $this->description)) ?: [];
        $html = [];

        foreach ($blocks as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($l) => $l !== ''));

            if ($lines === []) {
                continue;
            }

            $isList = collect($lines)->every(fn (string $l): bool => preg_match('/^[-•*]\s+/u', $l) === 1);

            if ($isList) {
                $items = array_map(fn ($l) => '<li>'.e(preg_replace('/^[-•*]\s+/u', '', $l)).'</li>', $lines);
                $html[] = '<ul>'.implode('', $items).'</ul>';
            } else {
                $html[] = '<p>'.implode('<br>', array_map('e', $lines)).'</p>';
            }
        }

        return implode("\n", $html);
    }
}
