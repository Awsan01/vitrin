<?php

namespace Tests\Feature\Billing;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckPaddleSetupTest extends TestCase
{
    use InteractsWithPaddle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePaddle();
        Http::preventStrayRequests();
    }

    /**
     * Paddle answering like a correctly set up sandbox account, with the
     * given changes to its webhook destination.
     *
     * @param  array<string, mixed>  $destination
     */
    protected function fakePaddle(array $destination = [], int $transactions = 200, int $notificationSettings = 200): void
    {
        $price = fn (string $id, int $amount) => [
            'id' => $id,
            'status' => 'active',
            'billing_cycle' => null,
            'unit_price' => ['amount' => (string) $amount, 'currency_code' => 'USD'],
        ];

        Http::fake([
            'sandbox-api.paddle.com/transactions*' => Http::response(['data' => []], $transactions),
            'sandbox-api.paddle.com/prices*' => Http::response(['data' => [
                $price('pri_starter', 900),
                $price('pri_growth', 2400),
                $price('pri_business', 6900),
            ]]),
            'sandbox-api.paddle.com/notification-settings*' => Http::response(['data' => [[
                'id' => 'ntfset_01test',
                'type' => 'url',
                'destination' => route('webhooks.paddle'),
                'active' => true,
                'traffic_source' => 'platform',
                'endpoint_secret_key' => $this->webhookSecret,
                'subscribed_events' => [
                    ['name' => 'transaction.completed'],
                    ['name' => 'adjustment.created'],
                    ['name' => 'adjustment.updated'],
                ],
                ...$destination,
            ]]], $notificationSettings),
        ]);
    }

    public function test_a_complete_setup_passes(): void
    {
        $this->fakePaddle();

        $this->artisan('paddle:check')
            ->expectsOutputToContain('$24.00 for 300 credits (Growth)')
            ->expectsOutputToContain('events and secret match')
            ->expectsOutputToContain('Payments are ready')
            ->assertSuccessful();
    }

    public function test_missing_settings_are_named(): void
    {
        Http::fake();
        config([
            'services.paddle.client_token' => null,
            'services.paddle.api_key' => null,
            'services.paddle.webhook_secret' => null,
        ]);

        $this->artisan('paddle:check')
            ->expectsOutputToContain('PADDLE_CLIENT_TOKEN: missing')
            ->expectsOutputToContain('PADDLE_API_KEY: missing')
            ->expectsOutputToContain('PADDLE_WEBHOOK_SECRET: missing')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_a_webhook_secret_that_differs_from_paddles_fails(): void
    {
        $this->fakePaddle(['endpoint_secret_key' => 'pdl_ntfset_another_secret']);

        $this->artisan('paddle:check')
            ->expectsOutputToContain("its secret key isn't PADDLE_WEBHOOK_SECRET")
            ->assertFailed();
    }

    public function test_a_destination_missing_events_fails(): void
    {
        $this->fakePaddle(['subscribed_events' => [['name' => 'transaction.completed']], 'active' => false]);

        $this->artisan('paddle:check')
            ->expectsOutputToContain("it is turned off; it doesn't send adjustment.created, adjustment.updated")
            ->assertFailed();
    }

    public function test_a_site_paddle_does_not_send_webhooks_to_fails(): void
    {
        $this->fakePaddle(['destination' => 'https://old-domain.test/webhooks/paddle']);

        $this->artisan('paddle:check')
            ->expectsOutputToContain('none sends webhooks to '.route('webhooks.paddle').' (found: https://old-domain.test/webhooks/paddle)')
            ->assertFailed();
    }

    public function test_live_credentials_in_the_sandbox_fail(): void
    {
        $this->fakePaddle();
        config([
            'services.paddle.client_token' => 'live_0123456789abcdef',
            'services.paddle.api_key' => 'pdl_live_apikey_0123456789abcdef',
        ]);

        $this->artisan('paddle:check')
            ->expectsOutputToContain('PADDLE_CLIENT_TOKEN: is from the live account, but PADDLE_ENVIRONMENT is sandbox')
            ->expectsOutputToContain('PADDLE_API_KEY: is from the live account, but PADDLE_ENVIRONMENT is sandbox')
            ->assertFailed();
    }

    public function test_missing_api_permissions_are_explained(): void
    {
        $this->fakePaddle(transactions: 403, notificationSettings: 403);

        $this->artisan('paddle:check')
            ->expectsOutputToContain('the key lacks transaction.read')
            ->expectsOutputToContain('the API key lacks notification_setting.read')
            ->assertFailed();
    }

    public function test_prices_that_cannot_be_sold_as_packs_fail(): void
    {
        Http::fake([
            'sandbox-api.paddle.com/transactions*' => Http::response(['data' => []]),
            'sandbox-api.paddle.com/notification-settings*' => Http::response(['data' => []]),
            'sandbox-api.paddle.com/prices*' => Http::response(['data' => [
                ['id' => 'pri_starter', 'status' => 'archived', 'billing_cycle' => null],
                ['id' => 'pri_growth', 'status' => 'active', 'billing_cycle' => ['interval' => 'month', 'frequency' => 1]],
            ]]),
        ]);

        $this->artisan('paddle:check')
            ->expectsOutputToContain('pri_starter is archived in Paddle')
            ->expectsOutputToContain('pri_growth is a subscription price')
            ->expectsOutputToContain("pri_business isn't a price in this Paddle account")
            ->assertFailed();
    }
}
