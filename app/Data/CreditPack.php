<?php

namespace App\Data;

/**
 * A credit pack from config/vitrin.php. The price is set in Paddle; a pack
 * without a Paddle price ID isn't offered.
 */
final readonly class CreditPack
{
    public function __construct(
        public string $key,
        public string $name,
        public int $credits,
        public ?string $priceId,
        public bool $popular = false,
    ) {}

    /**
     * Every configured pack, in config order.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        $packs = [];

        foreach ((array) config('vitrin.packs') as $key => $pack) {
            $packs[] = new self(
                key: (string) $key,
                name: (string) $pack['name'],
                credits: (int) $pack['credits'],
                priceId: ($pack['price_id'] ?? null) ?: null,
                popular: (bool) ($pack['popular'] ?? false),
            );
        }

        return $packs;
    }

    /**
     * The packs that can be bought: those with a Paddle price.
     *
     * @return list<self>
     */
    public static function purchasable(): array
    {
        return array_values(array_filter(self::all(), fn (self $pack) => $pack->priceId !== null));
    }

    public static function find(string $key): ?self
    {
        foreach (self::all() as $pack) {
            if ($pack->key === $key) {
                return $pack;
            }
        }

        return null;
    }
}
