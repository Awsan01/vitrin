<?php

/*
|--------------------------------------------------------------------------
| Vitrin settings
|--------------------------------------------------------------------------
|
| Everything product-specific lives here: which AI provider writes the copy
| and draws the photos, what each generation costs in credits, and the rules
| each marketplace enforces on titles, bullets and keywords.
|
*/

return [

    /*
    | AI providers. Any provider supported by the Laravel AI SDK works
    | (gemini, openai, anthropic, ...). Leave a model empty to use the
    | provider's default. Gemini covers both text and images with one key.
    */
    'ai' => [
        'text' => [
            'provider' => env('VITRIN_TEXT_PROVIDER', 'gemini'),
            'model' => env('VITRIN_TEXT_MODEL') ?: null,
        ],
        'image' => [
            'provider' => env('VITRIN_IMAGE_PROVIDER', 'gemini'),
            'model' => env('VITRIN_IMAGE_MODEL') ?: null,
            // low (~1K), medium (~2K) or high (~4K) on Gemini.
            'quality' => env('VITRIN_IMAGE_QUALITY', 'medium'),
        ],
    ],

    /*
    | Credits. New accounts get a starter balance; each generated listing
    | (one marketplace in one language) and each photo costs credits.
    | Failed generations are refunded automatically.
    */
    'credits' => [
        'signup' => (int) env('VITRIN_SIGNUP_CREDITS', 15),
        'copy' => (int) env('VITRIN_COPY_COST', 1),
        'photo' => (int) env('VITRIN_PHOTO_COST', 2),
    ],

    /*
    | Where uploads and generated photos are stored. Files are served
    | through authorized routes, so a private disk is fine (and keeps
    | unreleased products private).
    */
    'disk' => env('VITRIN_DISK', 'local'),

    'uploads' => [
        'max_kb' => 10240,
        'min_dimension' => 300,
        // Uploads are resized so the long edge is at most this many pixels.
        'max_dimension' => 2048,
        // Decoding takes about 4 bytes per pixel, so this caps the memory one
        // upload can use (50 MP is about 200 MB) and stops "image bombs":
        // tiny files that claim enormous dimensions.
        'max_megapixels' => 50,
    ],

    /*
    | Marketplace rules. These are injected into the writing prompt and then
    | enforced on the result (titles are trimmed at a word boundary, extra
    | tags are dropped) with a warning shown to the seller.
    |
    | Sources (checked October 2026):
    | - Amazon: 75-character title limit from July 27, 2026 (all categories
    |   except media) and the 125-character "Item highlights" field.
    | - noon: partner help center, title requirements and rejection reasons.
    | - Hepsiburada: 255-character title, brand + product + model + feature +
    |   quantity order, no promotional phrases.
    | - Trendyol: no hard title limit; the first 30 characters must describe
    |   the product; no emoji, links, shipping or contact details.
    | Re-check these when a marketplace changes its policy.
    */
    'marketplaces' => [
        'trendyol' => [
            'label' => 'Trendyol',
            'title_max' => 100,
            'title_target' => 'about 60–100 characters; the first 30 characters must clearly say what the product is',
            'highlight' => null,
            'bullets' => 5,
            'bullet_max' => 200,
            'bullet_target' => 'about 80–150 characters',
            'description_format' => 'html',
            'keywords' => ['label' => 'Search keywords', 'count' => 10, 'max_length' => 40, 'max_bytes' => null],
            'rules' => [
                'Title order: brand and model (if known), product type, then two or three key attributes such as connection, size, material or color. Example: "Marka X1 Kablosuz Ergonomik Mouse 2.4 GHz ve Bluetooth Siyah".',
                'The description should clearly cover measurements, pattern, fabric or material and care instructions, ideally as bullet points. Label the specifications list "Ürün Özellikleri:" when writing in Turkish.',
                'Never include emoji, links, shipping company, delivery time, contact information or campaign promises.',
            ],
        ],
        'hepsiburada' => [
            'label' => 'Hepsiburada',
            'title_max' => 255,
            'title_target' => 'about 80–150 characters',
            'highlight' => null,
            'bullets' => 5,
            'bullet_max' => 200,
            'bullet_target' => 'about 80–150 characters',
            'description_format' => 'html',
            'keywords' => ['label' => 'Search keywords', 'count' => 10, 'max_length' => 40, 'max_bytes' => null],
            'rules' => [
                'Title order: brand + main product name + model + feature + quantity.',
                'No excessive capital letters, emoji or symbols in the title.',
                'Never use promotional phrases such as "en uygun fiyat", "kampanya" or "ücretsiz kargo" (or their equivalents in other languages).',
                'The description should end with a short technical specifications list, labelled "Teknik Özellikler:" when writing in Turkish.',
                'No misleading claims such as medical benefits or promises that cannot be guaranteed.',
            ],
        ],
        'amazon' => [
            'label' => 'Amazon',
            'title_max' => 75,
            'title_target' => '75 characters or fewer including spaces (hard limit)',
            'highlight' => ['label' => 'Item highlights', 'max' => 125],
            'bullets' => 5,
            'bullet_max' => 250,
            'bullet_target' => 'about 150–200 characters',
            'description_format' => 'plain',
            'keywords' => ['label' => 'Backend search terms', 'count' => 25, 'max_length' => 40, 'max_bytes' => 250],
            'rules' => [
                'Amazon titles must be 75 characters or fewer including spaces (policy from July 27, 2026, all categories except media). Put the most important words first.',
                'Title order: brand (if known), product type, the single most important attribute, quantity if it is a pack.',
                'Do not repeat the same word more than twice in the title and do not use special characters such as ! $ ? _ { } ^ ~ #.',
                'Item highlights: one line of up to 125 characters with the most useful attributes that did not fit in the title.',
                'Each bullet starts with a short benefit phrase followed by a colon.',
                'Backend search terms: single words or short phrases, no punctuation, no brand names, no words already used in the title; together they must fit in 250 bytes.',
            ],
        ],
        'noon' => [
            'label' => 'noon',
            'title_min' => 20,
            'title_max' => 160,
            'title_target' => 'about 40–120 characters; never more than 160',
            'highlight' => null,
            'bullets' => 5,
            'bullet_max' => 250,
            'bullet_target' => 'about 150–200 characters',
            'description_format' => 'plain',
            'keywords' => ['label' => 'Search keywords', 'count' => 10, 'max_length' => 40, 'max_bytes' => null],
            'rules' => [
                'Do not put the brand name in the title; noon shows the brand separately.',
                'Do not put the color in the title; noon appends it automatically.',
                'Include the product type or sub-type and the top features, keeping the title as short as possible.',
                'In English, capitalize the first letter of each word except prepositions, conjunctions and articles.',
                'No special characters such as @ ^ * # &, no ALL CAPS except acronyms (LED, USB), no prices, promotions or keyword stuffing.',
            ],
        ],
        'etsy' => [
            'label' => 'Etsy',
            'title_max' => 140,
            'title_target' => 'up to 140 characters; lead with what the item is',
            'highlight' => null,
            'bullets' => 5,
            'bullet_max' => 200,
            'bullet_target' => 'about 80–150 characters',
            'description_format' => 'plain',
            'keywords' => ['label' => 'Tags', 'count' => 13, 'max_length' => 20, 'max_bytes' => null],
            'rules' => [
                'The title reads naturally: what the item is first, then its most important descriptive details. No keyword lists.',
                'Exactly 13 tags, each 20 characters or fewer. Multi-word phrases are fine; do not repeat tags.',
                'The description opens with a warm one-sentence hook, then covers materials, size and care.',
            ],
        ],
        'webstore' => [
            'label' => 'Online store',
            'hint' => 'Shopify, Salla, WooCommerce',
            'title_max' => 70,
            'title_target' => 'up to 70 characters so it is not cut off in search results',
            'highlight' => ['label' => 'SEO meta description', 'max' => 160],
            'bullets' => 5,
            'bullet_max' => 200,
            'bullet_target' => 'about 80–150 characters',
            'description_format' => 'html',
            'keywords' => ['label' => 'SEO keywords', 'count' => 10, 'max_length' => 40, 'max_bytes' => null],
            'rules' => [
                'The title doubles as the SEO title: product type and the main search phrase first.',
                'The SEO meta description is one persuasive sentence of up to 160 characters that includes the main search phrase.',
                'The description is scannable: a short intro paragraph, then key features as a list.',
            ],
        ],
    ],

];
