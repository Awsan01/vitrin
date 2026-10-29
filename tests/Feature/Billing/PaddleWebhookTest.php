<?php

namespace Tests\Feature\Billing;

use App\Data\CreditPack;
use App\Enums\PurchaseStatus;
use App\Models\CreditPurchase;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\CreditLedger;
use App\Services\CreditPurchases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class PaddleWebhookTest extends TestCase
{
    use InteractsWithPaddle, RefreshDatabase;

    protected User $user;

    protected CreditPurchase $purchase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePaddle();
        $this->user = User::factory()->withCredits(5)->create();
        $this->purchase = app(CreditPurchases::class)->start($this->user, CreditPack::find('growth'));
    }

    public function test_a_completed_payment_adds_the_packs_credits_once(): void
    {
        // Paddle retries until it gets a 2xx, so the same event can arrive twice.
        $this->postWebhook($this->transactionCompleted($this->purchase))->assertOk()->assertCookieMissing('XSRF-TOKEN');
        $this->postWebhook($this->transactionCompleted($this->purchase))->assertOk();

        $this->assertSame(305, $this->user->fresh()->credits);

        $purchase = $this->purchase->fresh();
        $this->assertSame(PurchaseStatus::Completed, $purchase->status);
        $this->assertSame('txn_01test', $purchase->paddle_transaction_id);
        $this->assertSame(2400, $purchase->amount);
        $this->assertSame('USD', $purchase->currency);

        $this->assertSame(1, CreditTransaction::where('reason', CreditTransaction::PURCHASE)->count());
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $this->user->id,
            'amount' => 300,
            'reference' => 'paddle:txn_01test',
            'description' => 'Bought the Growth pack',
        ]);
    }

    public function test_unsigned_or_forged_webhooks_are_rejected(): void
    {
        $payload = $this->transactionCompleted($this->purchase);
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $forged = 'ts='.time().';h1='.hash_hmac('sha256', time().':'.$body, 'not-the-secret');

        $this->postWebhook($payload, signature: '')->assertForbidden();
        $this->postWebhook($payload, signature: $forged)->assertForbidden();

        $this->assertSame(5, $this->user->fresh()->credits);
        $this->assertSame(PurchaseStatus::Pending, $this->purchase->fresh()->status);
    }

    public function test_refused_signatures_are_logged_so_a_wrong_secret_gets_noticed(): void
    {
        Log::spy();

        $this->postWebhook($this->transactionCompleted($this->purchase), signature: 'ts=1;h1=forged')->assertForbidden();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'invalid signature'))
            ->once();
    }

    public function test_webhooks_are_refused_until_the_secret_is_set(): void
    {
        config(['services.paddle.webhook_secret' => null]);

        $this->postWebhook($this->transactionCompleted($this->purchase))->assertServiceUnavailable();

        $this->assertSame(5, $this->user->fresh()->credits);
    }

    public function test_a_payment_for_another_price_adds_nothing(): void
    {
        $this->postWebhook($this->transactionCompleted($this->purchase, [
            'items' => [['price' => ['id' => 'pri_starter'], 'quantity' => 1]],
        ]))->assertOk();

        $this->assertSame(5, $this->user->fresh()->credits);
        $this->assertSame(PurchaseStatus::Pending, $this->purchase->fresh()->status);
    }

    public function test_payments_made_outside_the_app_are_ignored(): void
    {
        $this->postWebhook($this->transactionCompleted($this->purchase, ['custom_data' => null]))->assertOk();
        $this->postWebhook($this->transactionCompleted($this->purchase, ['custom_data' => ['purchase_id' => 'unknown']]))->assertOk();

        $this->assertSame(5, $this->user->fresh()->credits);
    }

    public function test_an_approved_refund_takes_the_credits_back(): void
    {
        $this->postWebhook($this->transactionCompleted($this->purchase));
        $this->postWebhook($this->adjustment())->assertOk();

        $this->assertSame(5, $this->user->fresh()->credits);

        $purchase = $this->purchase->fresh();
        $this->assertSame(PurchaseStatus::Refunded, $purchase->status);
        $this->assertSame(300, $purchase->credits_reversed);
        $this->assertDatabaseHas('credit_transactions', [
            'reason' => CreditTransaction::PURCHASE_REVERSAL,
            'amount' => -300,
            'reference' => 'paddle:adj_01test',
            'description' => 'Growth pack refunded',
        ]);
    }

    public function test_a_partial_refund_takes_back_the_same_share(): void
    {
        $this->postWebhook($this->transactionCompleted($this->purchase));
        $this->postWebhook($this->adjustment(['type' => 'partial', 'totals' => ['total' => '1200']]));

        $this->assertSame(155, $this->user->fresh()->credits);
        $this->assertSame(PurchaseStatus::PartiallyRefunded, $this->purchase->fresh()->status);
    }

    public function test_refunds_waiting_for_approval_change_nothing(): void
    {
        $this->postWebhook($this->transactionCompleted($this->purchase));

        $this->postWebhook($this->adjustment(['status' => 'pending_approval'], 'adjustment.created'))->assertOk();
        $this->assertSame(305, $this->user->fresh()->credits);

        $this->postWebhook($this->adjustment(['status' => 'rejected']))->assertOk();
        $this->assertSame(305, $this->user->fresh()->credits);
        $this->assertSame(PurchaseStatus::Completed, $this->purchase->fresh()->status);
    }

    public function test_the_same_adjustment_is_only_applied_once(): void
    {
        $this->postWebhook($this->transactionCompleted($this->purchase));

        $this->postWebhook($this->adjustment(['type' => 'partial', 'totals' => ['total' => '1200']], 'adjustment.created'));
        $this->postWebhook($this->adjustment(['type' => 'partial', 'totals' => ['total' => '1200']]));

        $this->assertSame(155, $this->user->fresh()->credits);
    }

    public function test_a_chargeback_never_takes_the_balance_below_zero(): void
    {
        Log::spy();

        $this->postWebhook($this->transactionCompleted($this->purchase));
        app(CreditLedger::class)->spend($this->user, 250, 'Generations');

        $this->postWebhook($this->adjustment(['action' => 'chargeback', 'status' => 'approved']));

        $this->assertSame(0, $this->user->fresh()->credits);
        $this->assertSame(PurchaseStatus::Refunded, $this->purchase->fresh()->status);
        $this->assertDatabaseHas('credit_transactions', ['amount' => -55, 'description' => 'Growth pack charged back']);
        Log::shouldHaveReceived('warning')
            ->with('Credits from a refunded purchase were already spent', Mockery::on(fn ($context) => $context['removed'] === 55))
            ->once();
    }

    public function test_events_vitrin_does_not_use_still_get_a_200(): void
    {
        $this->postWebhook(['event_type' => 'subscription.created', 'data' => ['id' => 'sub_01']])->assertOk();
        $this->postWebhook($this->adjustment(['action' => 'credit']))->assertOk();

        $this->assertSame(5, $this->user->fresh()->credits);
    }
}
