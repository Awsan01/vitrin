<?php

namespace App\Enums;

enum Marketplace: string
{
    case Trendyol = 'trendyol';
    case Hepsiburada = 'hepsiburada';
    case Amazon = 'amazon';
    case Noon = 'noon';
    case Etsy = 'etsy';
    case Webstore = 'webstore';

    public function label(): string
    {
        return $this->rules()['label'];
    }

    /**
     * The marketplace's listing rules from config/vitrin.php.
     *
     * @return array{
     *     label: string,
     *     hint?: string,
     *     title_min?: int,
     *     title_max: int,
     *     title_target: string,
     *     highlight: array{label: string, max: int}|null,
     *     bullets: int,
     *     bullet_max: int,
     *     bullet_target: string,
     *     description_format: 'html'|'plain',
     *     keywords: array{label: string, count: int, max_length: int, max_bytes: int|null},
     *     rules: list<string>,
     * }
     */
    public function rules(): array
    {
        return config("vitrin.marketplaces.{$this->value}");
    }

    /**
     * Options for the frontend pickers.
     *
     * @return list<array{value: string, label: string, hint: string|null}>
     */
    public static function options(): array
    {
        return array_map(fn (self $m) => [
            'value' => $m->value,
            'label' => $m->label(),
            'hint' => $m->rules()['hint'] ?? null,
        ], self::cases());
    }
}
