<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = (string) Str::ulid();

        return [
            'id' => $id,
            'user_id' => User::factory(),
            'name' => 'Linen shirt',
            'notes' => null,
            'source_path' => "listings/{$id}/source.jpg",
            'status' => ListingStatus::Queued,
            'product_facts' => null,
        ];
    }

    /**
     * A listing whose photo has been analyzed.
     */
    public function analyzed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::Generating,
            'product_facts' => self::sampleFacts(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function sampleFacts(): array
    {
        return [
            'is_product' => true,
            'rejection_reason' => null,
            'product_type' => "men's short-sleeve linen shirt",
            'category_path' => 'Clothing > Men > Shirts',
            'brand' => null,
            'colors' => ['sand beige'],
            'materials' => ['linen'],
            'key_features' => ['short sleeves', 'button-down front', 'chest pocket', 'relaxed fit'],
            'size_or_dimensions' => null,
            'quantity' => null,
            'target_audience' => 'men',
            'visible_text' => [],
            'needs_confirmation' => ['exact linen percentage', 'available sizes'],
            'photo_tips' => [],
        ];
    }
}
