<?php

namespace App\Data;

use App\Enums\Language;
use App\Enums\Marketplace;
use App\Enums\PhotoStyle;
use Illuminate\Http\UploadedFile;

/**
 * A validated request to generate listings from one product photo.
 */
final readonly class NewListing
{
    /**
     * @param  list<Marketplace>  $marketplaces
     * @param  list<Language>  $languages
     * @param  list<PhotoStyle>  $photoStyles
     */
    public function __construct(
        public UploadedFile $photo,
        public ?string $name,
        public ?string $notes,
        public array $marketplaces,
        public array $languages,
        public array $photoStyles,
    ) {}

    public function copyCount(): int
    {
        return count($this->marketplaces) * count($this->languages);
    }
}
