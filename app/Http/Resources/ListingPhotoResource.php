<?php

namespace App\Http\Resources;

use App\Enums\GenerationStatus;
use App\Models\ListingPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ListingPhoto
 */
class ListingPhotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ready = $this->status === GenerationStatus::Completed && $this->path;
        // The file changes on regenerate while the URL stays the same, so
        // the version parameter keeps browsers from showing a cached image.
        $params = ['listing' => $this->listing_id, 'photo' => $this->id, 'v' => $this->updated_at?->getTimestamp()];

        return [
            'id' => $this->id,
            'style' => $this->style->value,
            'style_label' => $this->style->label(),
            'status' => $this->status->value,
            'url' => $ready ? route('listings.photos.show', $params, false) : null,
            'download_url' => $ready ? route('listings.photos.show', [...$params, 'download' => 1], false) : null,
            'error' => $this->error,
        ];
    }
}
