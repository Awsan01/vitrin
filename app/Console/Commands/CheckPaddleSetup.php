<?php

namespace App\Console\Commands;

use App\Data\CreditPack;
use App\Services\Paddle;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

/**
 * Checks everything payments depend on, so a wrong price ID, a missing API
 * permission or the sandbox secret on the live site shows up here instead
 * of as a customer who paid and got no credits. Read-only: it changes
 * nothing in Paddle, and never prints more than the start of a secret.
 */
class CheckPaddleSetup extends Command
{
    /**
     * @var string
     */
    protected $signature = 'paddle:check';

    /**
     * @var string
     */
    protected $description = 'Check the Paddle settings, prices, API key and webhook destination';

    /** The webhook events PaddleWebhookController handles. */
    protected const EVENTS = ['transaction.completed', 'adjustment.created', 'adjustment.updated'];

    protected int $failures = 0;

    public function handle(Paddle $paddle): int
    {
        $environment = $paddle->environment();

        $this->newLine();
        $this->line($environment === 'production'
            ? '  <options=bold>Paddle live account</> (real payments)'
            : '  <options=bold>Paddle sandbox</> (test cards, no real money)');
        $this->newLine();

        $this->checkEnvironment($environment);
        $this->checkSettings($paddle, $environment);

        if ($paddle->apiKey() !== '' && $this->checkApiKey($paddle)) {
            $this->checkPrices($paddle);
            $this->checkWebhookDestination($paddle);
        }

        $this->newLine();

        if ($this->failures > 0 || ! $paddle->enabled()) {
            $this->components->error('Payments are off, or will fail, until the problems above are fixed. Setup steps: README, "Payments".');

            return self::FAILURE;
        }

        $this->components->info('Payments are ready: the Credits page shows the Buy buttons.');

        return self::SUCCESS;
    }

    protected function checkEnvironment(string $environment): void
    {
        if ($environment === 'production' && ! app()->isProduction()) {
            $this->row('warn', 'PADDLE_ENVIRONMENT', "production, but APP_ENV isn't: payments made here are real");
        } elseif ($environment === 'sandbox' && app()->isProduction()) {
            $this->row('warn', 'PADDLE_ENVIRONMENT', "sandbox on a production site: customers can't pay with real cards");
        } else {
            $this->row('ok', 'PADDLE_ENVIRONMENT', $environment);
        }

        $url = (string) config('app.url');

        if (app()->isProduction() && ! str_starts_with($url, 'https://')) {
            $this->row('fail', 'APP_URL', "{$url} must start with https://, Paddle only works on secure sites");
        }
    }

    protected function checkSettings(Paddle $paddle, string $environment): void
    {
        $token = (string) $paddle->clientToken();

        if ($token === '') {
            $this->row('fail', 'PADDLE_CLIENT_TOKEN', 'missing (Developer tools > Authentication > Client-side tokens)');
        } elseif (($from = $this->environmentOf($token, 'test_', 'live_')) !== null && $from !== $environment) {
            $this->row('fail', 'PADDLE_CLIENT_TOKEN', $this->wrongAccount($from, $environment));
        } else {
            $this->row('ok', 'PADDLE_CLIENT_TOKEN', $this->mask($token));
        }

        $key = $paddle->apiKey();

        if ($key === '') {
            $this->row('fail', 'PADDLE_API_KEY', 'missing (Developer tools > Authentication > API keys)');
        } elseif (($from = $this->environmentOf($key, 'pdl_sdbx_', 'pdl_live_')) !== null && $from !== $environment) {
            $this->row('fail', 'PADDLE_API_KEY', $this->wrongAccount($from, $environment));
        } else {
            $this->row('ok', 'PADDLE_API_KEY', $this->mask($key));
        }

        $secret = (string) config('services.paddle.webhook_secret');

        if ($secret === '') {
            $this->row('fail', 'PADDLE_WEBHOOK_SECRET', 'missing (Developer tools > Notifications, the destination\'s secret key)');
        } elseif (! str_starts_with($secret, 'pdl_ntfset_')) {
            $this->row('warn', 'PADDLE_WEBHOOK_SECRET', "doesn't look like a notification secret, which starts with pdl_ntfset_");
        } else {
            $this->row('ok', 'PADDLE_WEBHOOK_SECRET', $this->mask($secret));
        }

        foreach (CreditPack::all() as $pack) {
            if ($pack->priceId === null) {
                $this->row('warn', $this->priceSetting($pack), "not set, so the {$pack->name} pack isn't offered");
            } elseif ($key === '') {
                $this->row('warn', $this->priceSetting($pack), "{$pack->priceId} (not checked without an API key)");
            }
        }

        if (CreditPack::purchasable() === []) {
            $this->row('fail', 'PADDLE_PRICE_*', 'no pack has a Paddle price, so nothing can be bought');
        }
    }

    /**
     * Whether the API key works at all, and can read transactions (to
     * confirm payments and fetch invoices).
     */
    protected function checkApiKey(Paddle $paddle): bool
    {
        $response = $this->request(fn () => $paddle->api()->get('/transactions', ['per_page' => 1]));

        if ($response === null) {
            return false;
        }

        if ($response->status() === 401) {
            $this->row('fail', 'PADDLE_API_KEY', 'Paddle rejected it. Check it was copied whole, from this environment\'s account, and hasn\'t expired or been revoked');

            return false;
        }

        if ($response->status() === 403) {
            $this->row('fail', 'API access', 'the key lacks transaction.read, needed to confirm payments and download invoices');
        } elseif ($response->successful()) {
            $this->row('ok', 'API access', 'can read transactions, to confirm payments and fetch invoices');
        } else {
            $this->row('fail', 'Paddle API', "answered {$response->status()}");
        }

        return true;
    }

    protected function checkPrices(Paddle $paddle): void
    {
        $packs = CreditPack::purchasable();

        if ($packs === []) {
            return;
        }

        $ids = implode(',', array_map(fn (CreditPack $pack) => (string) $pack->priceId, $packs));
        $response = $this->request(fn () => $paddle->api()->get('/prices', ['id' => $ids, 'per_page' => 200]));

        if ($response === null) {
            return;
        }

        if (! $response->successful()) {
            $this->row('fail', 'API access', $response->status() === 403
                ? 'the key lacks price.read, needed to show prices on the site'
                : "Paddle answered {$response->status()} when reading prices");

            return;
        }

        $prices = collect((array) $response->json('data'))->filter(fn ($price) => is_array($price))->keyBy('id');

        foreach ($packs as $pack) {
            $setting = $this->priceSetting($pack);
            $price = $prices->get($pack->priceId);

            if (! is_array($price)) {
                $this->row('fail', $setting, "{$pack->priceId} isn't a price in this Paddle account");

                continue;
            }

            if (data_get($price, 'status') !== 'active') {
                $this->row('fail', $setting, "{$pack->priceId} is archived in Paddle");
            } elseif (data_get($price, 'billing_cycle') !== null) {
                $this->row('fail', $setting, "{$pack->priceId} is a subscription price; packs need one-time prices");
            } else {
                $amount = data_get($price, 'unit_price.amount');
                $currency = data_get($price, 'unit_price.currency_code');
                $shown = is_numeric($amount) && is_string($currency) ? Paddle::formatMoney((int) $amount, $currency) : 'price';

                $this->row('ok', $setting, "{$shown} for {$pack->credits} credits ({$pack->name})");
            }
        }
    }

    /**
     * The notification destination that sends webhooks to this site: its
     * URL, events, and whether its secret is the one in .env.
     */
    protected function checkWebhookDestination(Paddle $paddle): void
    {
        $label = 'Webhook destination';
        $url = route('webhooks.paddle');
        $response = $this->request(fn () => $paddle->api()->get('/notification-settings'));

        if ($response === null) {
            return;
        }

        if ($response->status() === 403) {
            $this->row('warn', $label, 'not checked, the API key lacks notification_setting.read. In Paddle, check that a destination sends '.implode(', ', self::EVENTS)." to {$url}");

            return;
        }

        if (! $response->successful()) {
            $this->row('fail', $label, "Paddle answered {$response->status()}");

            return;
        }

        $urls = collect((array) $response->json('data'))
            ->filter(fn ($destination) => is_array($destination) && data_get($destination, 'type') === 'url');
        $destination = $urls->first(fn (array $destination) => rtrim((string) data_get($destination, 'destination'), '/') === rtrim($url, '/'));

        if (! is_array($destination)) {
            $found = $urls->pluck('destination')->implode(', ');
            $message = "none sends webhooks to {$url}".($found !== '' ? " (found: {$found})" : '');

            // Paddle can't reach a computer without a tunnel, and payments
            // are still confirmed through the API.
            app()->isLocal()
                ? $this->row('warn', $label, "{$message}. Fine for testing on this computer: payments are confirmed through the API, but refunds won't arrive")
                : $this->row('fail', $label, "{$message}. Payments made after the buyer closes the page, and every refund, will be missed");

            return;
        }

        $problems = [];

        if (data_get($destination, 'active') !== true) {
            $problems[] = 'it is turned off';
        }

        if (! in_array(data_get($destination, 'traffic_source'), ['platform', 'all'], true)) {
            $problems[] = 'it only receives simulated events';
        }

        $subscribed = collect((array) data_get($destination, 'subscribed_events'))->pluck('name')->all();
        $missing = array_diff(self::EVENTS, $subscribed);

        if ($missing !== []) {
            $problems[] = 'it doesn\'t send '.implode(', ', $missing);
        }

        $destinationSecret = data_get($destination, 'endpoint_secret_key');
        $secretChecked = is_string($destinationSecret) && $destinationSecret !== '';

        if ($secretChecked && ! hash_equals($destinationSecret, (string) config('services.paddle.webhook_secret'))) {
            $problems[] = 'its secret key isn\'t PADDLE_WEBHOOK_SECRET, so every webhook will be refused';
        }

        $problems === []
            ? $this->row('ok', $label, $url.($secretChecked ? ', events and secret match' : ', events match'))
            : $this->row('fail', $label, implode('; ', $problems));
    }

    /**
     * @param  Closure(): Response  $request
     */
    protected function request(Closure $request): ?Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            $this->row('fail', 'Paddle API', "couldn't connect: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Which environment a token or key belongs to, from its prefix. Null for
     * an older format without one.
     */
    protected function environmentOf(string $value, string $sandboxPrefix, string $livePrefix): ?string
    {
        return match (true) {
            str_starts_with($value, $sandboxPrefix) => 'sandbox',
            str_starts_with($value, $livePrefix) => 'production',
            default => null,
        };
    }

    protected function wrongAccount(string $from, string $environment): string
    {
        $account = $from === 'production' ? 'live' : 'sandbox';

        return "is from the {$account} account, but PADDLE_ENVIRONMENT is {$environment}";
    }

    protected function priceSetting(CreditPack $pack): string
    {
        return 'PADDLE_PRICE_'.strtoupper($pack->key);
    }

    /**
     * Enough of a secret to tell which one it is, never the whole thing.
     */
    protected function mask(string $value): string
    {
        return substr($value, 0, 10).'…';
    }

    /**
     * @param  'ok'|'warn'|'fail'  $status
     */
    protected function row(string $status, string $label, string $detail): void
    {
        if ($status === 'fail') {
            $this->failures++;
        }

        $tag = match ($status) {
            'ok' => '<fg=green;options=bold>  OK  </>',
            'warn' => '<fg=yellow;options=bold> WARN </>',
            'fail' => '<fg=red;options=bold> FAIL </>',
        };

        $this->line("  {$tag} <options=bold>{$label}</>: {$detail}");
    }
}
