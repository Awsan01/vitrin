<?php

namespace App\Http\Resources;

use App\Enums\GenerationStatus;
use App\Enums\Marketplace;
use App\Models\ListingCopy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ListingCopy
 */
class ListingCopyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rules = $this->marketplace->rules();
        $completed = $this->status === GenerationStatus::Completed;

        return [
            'id' => $this->id,
            'marketplace' => $this->marketplace->value,
            'marketplace_label' => $this->marketplace->label(),
            'language' => $this->language->value,
            'language_label' => $this->language->label(),
            'dir' => $this->language->direction(),
            'status' => $this->status->value,
            'title' => $this->title,
            'highlight' => $this->highlight,
            'bullets' => $this->bullets ?? [],
            'description' => $this->description,
            'description_html' => $completed && $rules['description_format'] === 'html'
                ? $this->descriptionHtml()
                : null,
            'keywords' => $this->keywords ?? [],
            'category' => $this->category,
            'warnings' => $this->warnings ?? [],
            'error' => $this->error,
            'limits' => [
                'title_max' => $rules['title_max'],
                'highlight_label' => $rules['highlight']['label'] ?? null,
                'highlight_max' => $rules['highlight']['max'] ?? null,
                'keywords_label' => $rules['keywords']['label'],
                'keywords_separator' => $this->marketplace === Marketplace::Amazon ? ' ' : ', ',
            ],
        ];
    }
}
