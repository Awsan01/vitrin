<?php

namespace Tests\Feature\Billing;

use App\Models\CreditPurchase;
use Illuminate\Testing\TestResponse;

/**
 * Paddle settings, signed webhooks and payloads shaped like Paddle's
 * (https://developer.paddle.com/webhooks/overview) for the billing tests.
 */
trait InteractsWithPaddle
{
    protected string $webhookSecret = 'pdl_ntfset_test_secret';

    protected function configurePaddle(): void
    {
        config([
            'services.paddle.environment' => 'sandbox',
            'services.paddle.client_token' => 'test_client_token',
            'services.paddle.api_key' => 'test_api_key',
            'services.paddle.webhook_secret' => $this->webhookSecret,
            'services.paddle.webhook_tolerance' => 300,
            'vitrin.packs.starter.price_id' => 'pri_starter',
            'vitrin.packs.growth.price_id' => 'pri_growth',
            'vitrin.packs.business.price_id' => 'pri_business',
        ]);
    }

    /**
     * Send a webhook the way Paddle does: a raw JSON body with a
     * Paddle-Signature header. Pass a signature to send a forged one.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function postWebhook(array $payload, ?string $signature = null): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature ??= "ts={$timestamp};h1=".hash_hmac('sha256', "{$timestamp}:{$body}", $this->webhookSecret);

        return $this->call('POST', '/webhooks/paddle', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_PADDLE_SIGNATURE' => $signature,
        ], content: $body);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function transactionCompleted(CreditPurchase $purchase, array $data = []): array
    {
        return [
            'event_id' => 'evt_01test',
            'event_type' => 'transaction.completed',
            'occurred_at' => now()->toIso8601String(),
            'notification_id' => 'ntf_01test',
            'data' => $this->paddleTransaction($purchase, $data),
        ];
    }

    /**
     * A completed transaction for the purchase, the way Paddle's API returns
     * it and its webhooks carry it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function paddleTransaction(CreditPurchase $purchase, array $data = []): array
    {
        return [
            'id' => 'txn_01test',
            'status' => 'completed',
            'customer_id' => 'ctm_01test',
            'currency_code' => 'USD',
            'custom_data' => ['purchase_id' => $purchase->id],
            'items' => [['price' => ['id' => $purchase->price_id], 'quantity' => 1]],
            'details' => ['totals' => ['total' => '2400', 'grand_total' => '2400']],
            ...$data,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function adjustment(array $data = [], string $event = 'adjustment.updated'): array
    {
        return [
            'event_id' => 'evt_02test',
            'event_type' => $event,
            'occurred_at' => now()->toIso8601String(),
            'notification_id' => 'ntf_02test',
            'data' => [
                'id' => 'adj_01test',
                'action' => 'refund',
                'status' => 'approved',
                'type' => 'full',
                'transaction_id' => 'txn_01test',
                'currency_code' => 'USD',
                'totals' => ['total' => '2400'],
                ...$data,
            ],
        ];
    }
}
