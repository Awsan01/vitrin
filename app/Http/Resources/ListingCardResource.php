<?php

namespace App\Http\Resources;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact listing for the dashboard grid.
 *
 * @mixin Listing
 */
class ListingCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?: 'Untitled product',
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_processing' => $this->status->isProcessing(),
            'created_at' => $this->created_at?->toIso8601String(),
            'source_url' => route('listings.source', $this->resource, false),
            'marketplaces' => $this->copies->map(fn ($c) => $c->marketplace->label())->unique()->values()->all(),
            'languages' => $this->copies->map(fn ($c) => $c->language->value)->unique()->values()->all(),
            'copies_count' => $this->copies->count(),
            'photos_count' => $this->photos->count(),
        ];
    }
}
