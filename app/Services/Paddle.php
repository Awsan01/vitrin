<?php

namespace App\Services;

use App\Data\CreditPack;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Number;
use NumberFormatter;
use Throwable;

/**
 * Paddle settings and the prices shown on the site. The checkout itself
 * runs in the browser (Paddle.js) and payments arrive by webhook, so this
 * is the only place the server talks to Paddle's API.
 */
class Paddle
{
    /**
     * Whether credit packs can be bought: the browser needs the client-side
     * token, and without the webhook secret no payment could be credited.
     */
    public function enabled(): bool
    {
        return $this->clientToken() !== null
            && (string) config('services.paddle.webhook_secret') !== ''
            && CreditPack::purchasable() !== [];
    }

    /**
     * @return 'sandbox'|'production'
     */
    public function environment(): string
    {
        return config('services.paddle.environment') === 'production' ? 'production' : 'sandbox';
    }

    public function clientToken(): ?string
    {
        return config('services.paddle.client_token') ?: null;
    }

    /**
     * What Paddle.js needs in the browser, or null when payments are off.
     *
     * @return array{environment: 'sandbox'|'production', token: string}|null
     */
    public function checkoutConfig(): ?array
    {
        $token = $this->clientToken();

        return $this->enabled() && $token !== null
            ? ['environment' => $this->environment(), 'token' => $token]
            : null;
    }

    /**
     * Every pack as shown on the site. "price" is null until Paddle's price
     * is known; "purchasable" is whether it can be bought right now.
     *
     * @return list<array{key: string, name: string, credits: int, price: string|null, popular: bool, purchasable: bool}>
     */
    public function packs(): array
    {
        $prices = $this->prices();
        $enabled = $this->enabled();

        return array_map(fn (CreditPack $pack) => [
            'key' => $pack->key,
            'name' => $pack->name,
            'credits' => $pack->credits,
            'price' => $pack->priceId !== null ? ($prices[$pack->priceId] ?? null) : null,
            'popular' => $pack->popular,
            'purchasable' => $enabled && $pack->priceId !== null,
        ], CreditPack::all());
    }

    /**
     * Display prices for the purchasable packs, keyed by Paddle price ID,
     * e.g. ['pri_01h...' => '$9.00']. Read from Paddle so the site can never
     * show a different price from the checkout. Cached for an hour; when
     * Paddle can't be reached the prices are left out (the checkout still
     * shows them) and it's tried again in five minutes.
     *
     * @return array<string, string>
     */
    public function prices(): array
    {
        $ids = array_map(fn (CreditPack $pack) => (string) $pack->priceId, CreditPack::purchasable());
        $key = (string) config('services.paddle.api_key');

        if ($ids === [] || $key === '') {
            return [];
        }

        $cacheKey = 'paddle:prices:'.$this->environment().':'.md5(implode(',', $ids));
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout(5)
                ->get($this->apiUrl().'/prices', ['id' => implode(',', $ids), 'per_page' => 200])
                ->throw();

            $prices = [];

            foreach ((array) $response->json('data') as $price) {
                $amount = data_get($price, 'unit_price.amount');
                $currency = data_get($price, 'unit_price.currency_code');

                if (is_string($price['id'] ?? null) && is_numeric($amount) && is_string($currency)) {
                    $prices[$price['id']] = self::formatMoney((int) $amount, $currency);
                }
            }

            Cache::put($cacheKey, $prices, now()->addHour());

            return $prices;
        } catch (Throwable $e) {
            report($e);
            Cache::put($cacheKey, [], now()->addMinutes(5));

            return [];
        }
    }

    /**
     * An amount in the currency's smallest unit, as Paddle sends it, for
     * display: (900, 'USD') becomes "$9.00", (900, 'JPY') "¥900".
     */
    public static function formatMoney(int $amount, string $currency): string
    {
        $digits = (new NumberFormatter("en@currency={$currency}", NumberFormatter::CURRENCY))
            ->getAttribute(NumberFormatter::FRACTION_DIGITS);

        return (string) Number::currency($amount / 10 ** (int) $digits, in: $currency, locale: 'en');
    }

    protected function apiUrl(): string
    {
        return $this->environment() === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';
    }
}
