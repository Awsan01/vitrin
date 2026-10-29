<?php

namespace App\Services;

use App\Data\CreditPack;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Number;
use NumberFormatter;
use Throwable;

/**
 * Paddle settings and every call the server makes to Paddle's API: the
 * prices shown on the site, checking payments, invoices and the setup
 * check. The checkout itself runs in the browser (Paddle.js) and payments
 * arrive by webhook.
 */
class Paddle
{
    /**
     * Whether credit packs can be bought. Every setting is needed: the
     * browser opens the checkout with the client-side token, the server
     * shows prices and checks payments with the API key, and without the
     * webhook secret refunds (and payments made after the page was closed)
     * would never arrive.
     */
    public function enabled(): bool
    {
        return $this->missingSettings() === [];
    }

    /**
     * The .env settings payments still need, e.g. ['PADDLE_API_KEY'].
     *
     * @return list<string>
     */
    public function missingSettings(): array
    {
        return array_keys(array_filter([
            'PADDLE_CLIENT_TOKEN' => $this->clientToken() === null,
            'PADDLE_API_KEY' => $this->apiKey() === '',
            'PADDLE_WEBHOOK_SECRET' => (string) config('services.paddle.webhook_secret') === '',
            'PADDLE_PRICE_*' => CreditPack::purchasable() === [],
        ]));
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

    public function apiKey(): string
    {
        return (string) config('services.paddle.api_key');
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

        if ($ids === [] || $this->apiKey() === '') {
            return [];
        }

        $cacheKey = 'paddle:prices:'.$this->environment().':'.md5(implode(',', $ids));
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = $this->api()
                ->timeout(5)
                ->get('/prices', ['id' => implode(',', $ids), 'per_page' => 200])
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
     * A transaction as Paddle has it, or null when Paddle doesn't know the
     * ID (for example a sandbox ID sent to the live account).
     *
     * @return array<string, mixed>|null
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function transaction(string $id): ?array
    {
        $response = $this->api()->get('/transactions/'.rawurlencode($id));

        if ($response->notFound()) {
            return null;
        }

        return (array) $response->throw()->json('data');
    }

    /**
     * Completed transactions created at or after the given time, fetched
     * page by page (Paddle returns at most 30 at a time).
     *
     * @return Generator<int, array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function completedTransactions(CarbonInterface $since): Generator
    {
        $query = [
            'status' => 'completed',
            'created_at[GTE]' => $since->toIso8601ZuluString(),
            'per_page' => 30,
        ];

        // Capped, so a misbehaving response can't keep this looping.
        for ($page = 0; $page < 100; $page++) {
            $response = $this->api()->get('/transactions', $query)->throw();
            $last = null;

            foreach ((array) $response->json('data') as $transaction) {
                if (is_array($transaction)) {
                    $last = $transaction['id'] ?? null;

                    yield $transaction;
                }
            }

            if ($response->json('meta.pagination.has_more') !== true || ! is_string($last) || $last === ($query['after'] ?? null)) {
                return;
            }

            $query['after'] = $last;
        }
    }

    /**
     * A temporary link (valid for about an hour) to the PDF invoice Paddle
     * issued for a completed transaction.
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function invoiceUrl(string $transactionId): ?string
    {
        $url = $this->api()
            ->get('/transactions/'.rawurlencode($transactionId).'/invoice', ['disposition' => 'attachment'])
            ->throw()
            ->json('data.url');

        return is_string($url) && str_starts_with($url, 'https://') ? $url : null;
    }

    /**
     * An authenticated client for Paddle's API in the configured environment.
     */
    public function api(): PendingRequest
    {
        return Http::baseUrl($this->apiUrl())
            ->withToken($this->apiKey())
            ->acceptJson()
            ->timeout(10);
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
