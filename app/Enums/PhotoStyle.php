<?php

namespace App\Enums;

enum PhotoStyle: string
{
    case WhiteBackground = 'white_background';
    case Lifestyle = 'lifestyle';
    case InUse = 'in_use';
    case Detail = 'detail';
    case Angle = 'angle';

    public function label(): string
    {
        return match ($this) {
            self::WhiteBackground => 'White background',
            self::Lifestyle => 'Lifestyle scene',
            self::InUse => 'In use',
            self::Detail => 'Close-up detail',
            self::Angle => 'New angle',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::WhiteBackground => 'Clean studio shot on pure white, the main image most marketplaces require.',
            self::Lifestyle => 'Your product in a natural setting where it is used.',
            self::InUse => 'Someone using the product, shown naturally.',
            self::Detail => 'Macro shot of texture, material and finish.',
            self::Angle => 'A three-quarter view. AI imagines unseen sides, so check it before publishing.',
        };
    }

    /**
     * Instructions for this style, appended to the shared product-fidelity rules.
     */
    public function prompt(string $productType): string
    {
        return match ($this) {
            self::WhiteBackground => 'Create a professional e-commerce main image: the product alone, centered, on a pure white (#FFFFFF) seamless background with a soft natural shadow beneath it. Even studio lighting, sharp focus, the product fills about 85% of the frame. No props.',
            self::Lifestyle => "Create a realistic lifestyle photo of the product in a tasteful, natural setting where a {$productType} is typically used. Soft natural light and a shallow depth of field. The product is the clear hero; props are minimal and never cover it.",
            self::InUse => "Show the product being used naturally by a person, as a {$productType} would be used in real life. Prefer hands or a cropped figure over a full face. The product stays clearly visible and identical to the reference.",
            self::Detail => 'Create a close-up macro photo that highlights the texture, material and craftsmanship visible in the reference photo. Studio lighting and a neutral background.',
            self::Angle => 'Show the same product from a three-quarter camera angle on a clean light-gray studio background. Keep every visible detail consistent with the reference; where the reference does not show a side, keep that side simple and plausible.',
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'description' => $s->description(),
        ], self::cases());
    }
}
