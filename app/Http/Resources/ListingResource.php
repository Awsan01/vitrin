<?php

namespace App\Http\Resources;

use App\Enums\GenerationStatus;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full listing for the results page.
 *
 * @mixin Listing
 */
class ListingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->copies->pluck('status')->merge($this->photos->pluck('status'));

        return [
            'id' => $this->id,
            'name' => $this->name ?: 'Untitled product',
            'notes' => $this->notes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_processing' => $this->status->isProcessing(),
            'error' => $this->error,
            'can_retry' => $this->canRetry(),
            'created_at' => $this->created_at?->toIso8601String(),
            'source_url' => route('listings.source', $this->resource, false),
            'facts' => $this->product_facts,
            'progress' => [
                'done' => $items->filter(fn (GenerationStatus $s) => $s->isFinished())->count(),
                'total' => $items->count(),
            ],
            'copies' => ListingCopyResource::collection($this->copies)->resolve($request),
            'photos' => ListingPhotoResource::collection($this->photos)->resolve($request),
        ];
    }
}
