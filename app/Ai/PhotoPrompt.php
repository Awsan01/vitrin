<?php

namespace App\Ai;

use App\Enums\PhotoStyle;
use App\Models\Listing;

/**
 * Builds the image-generation prompt for one photo style. The source photo is
 * attached as the reference; the shared rules keep the product itself intact.
 */
class PhotoPrompt
{
    public static function for(PhotoStyle $style, Listing $listing): string
    {
        $facts = $listing->product_facts ?? [];
        $productType = $listing->productType();

        $context = collect([
            'Product' => $productType,
            'Brand' => $facts['brand'] ?? null,
            'Colors' => implode(', ', $facts['colors'] ?? []),
            'Printed text to keep exactly' => implode(' | ', $facts['visible_text'] ?? []),
        ])->filter()->map(fn ($value, $label) => "{$label}: {$value}")->implode("\n");

        return <<<PROMPT
        {$style->prompt($productType)}

        Use the attached photo as the exact reference for the product. Keep the product identical: same shape, proportions, colors, materials, logos, printed text and details. Do not add, remove or change any part of the product.
        Do not add any text, captions, watermarks, badges, price tags or logos that are not on the product itself.
        The result must look like a real, high-end commercial product photograph, not an illustration.

        {$context}
        PROMPT;
    }
}
