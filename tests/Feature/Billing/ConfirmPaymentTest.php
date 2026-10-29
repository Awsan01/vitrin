<?php

namespace Tests\Feature\Billing;

use App\Data\CreditPack;
use App\Enums\PurchaseStatus;
use App\Models\CreditPurchase;
use App\Models\User;
use App\Services\CreditPurchases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Right after paying, the browser reports the transaction and the server
 * checks it with Paddle's API instead of waiting for the webhook.
 */
class ConfirmPaymentTest extends TestCase
{
    use InteractsWithPaddle, RefreshDatabase;

    protected const TRANSACTION = 'txn_01hv8kxg3hxyxs9t471ms9kfsz';

    protected User $user;

    protected CreditPurchase $purchase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePaddle();
        Http::preventStrayRequests();

        $this->user = User::factory()->withCredits(5)->create();
        $this->purchase = app(CreditPurchases::class)->start($this->user, CreditPack::find('growth'));
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    protected function fakeTransaction(array $transaction): void
    {
        Http::fake([
            'sandbox-api.paddle.com/transactions/'.self::TRANSACTION => Http::response(['data' => $transaction]),
        ]);
    }

    protected function confirm(?User $as = null, string $transactionId = self::TRANSACTION): TestResponse
    {
        return $this->actingAs($as ?? $this->user)
            ->from(route('credits.index'))
            ->post(route('credits.purchases.confirm', $this->purchase), ['transaction_id' => $transactionId]);
    }

    public function test_a_confirmed_payment_adds_the_credits_straight_away(): void
    {
        $this->fakeTransaction($this->paddleTransaction($this->purchase, ['id' => self::TRANSACTION]));

        $this->confirm()->assertRedirect(route('credits.index'));

        $this->assertSame(305, $this->user->fresh()->credits);
        $this->assertSame(PurchaseStatus::Completed, $this->purchase->fresh()->status);
        $this->assertSame(self::TRANSACTION, $this->purchase->fresh()->paddle_transaction_id);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test_api_key'));
    }

    public function test_the_webhook_arriving_afterwards_adds_nothing_more(): void
    {
        $this->fakeTransaction($this->paddleTransaction($this->purchase, ['id' => self::TRANSACTION]));

        $this->confirm();
        $this->postWebhook($this->transactionCompleted($this->purchase, ['id' => self::TRANSACTION]))->assertOk();

        $this->assertSame(305, $this->user->fresh()->credits);
    }

    public function test_a_purchase_the_webhook_already_completed_is_not_checked_again(): void
    {
        Http::fake();
        $this->postWebhook($this->transactionCompleted($this->purchase, ['id' => self::TRANSACTION]));

        $this->confirm()->assertRedirect(route('credits.index'));

        $this->assertSame(305, $this->user->fresh()->credits);
        Http::assertNothingSent();
    }

    public function test_a_payment_paddle_is_still_processing_adds_nothing_yet(): void
    {
        $this->fakeTransaction($this->paddleTransaction($this->purchase, ['id' => self::TRANSACTION, 'status' => 'paid']));

        $this->confirm()->assertRedirect(route('credits.index'));

        $this->assertSame(5, $this->user->fresh()->credits);
        $this->assertSame(PurchaseStatus::Pending, $this->purchase->fresh()->status);
    }

    public function test_a_transaction_for_another_purchase_adds_nothing(): void
    {
        $other = app(CreditPurchases::class)->start(User::factory()->create(), CreditPack::find('business'));
        $this->fakeTransaction($this->paddleTransaction($other, ['id' => self::TRANSACTION]));

        $this->confirm()->assertRedirect(route('credits.index'));

        $this->assertSame(5, $this->user->fresh()->credits);
        $this->assertSame(PurchaseStatus::Pending, $this->purchase->fresh()->status);
        $this->assertSame(PurchaseStatus::Pending, $other->fresh()->status);
    }

    public function test_only_the_buyer_can_confirm_a_purchase(): void
    {
        Http::fake();

        $this->confirm(as: User::factory()->create())->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_the_transaction_id_must_be_a_paddle_id(): void
    {
        Http::fake();

        foreach (['', 'txn_01test', '../prices', self::TRANSACTION.'/invoice', strtoupper(self::TRANSACTION)] as $id) {
            $this->confirm(transactionId: $id)->assertSessionHasErrors('transaction_id');
        }

        Http::assertNothingSent();
    }

    public function test_a_transaction_paddle_does_not_know_changes_nothing(): void
    {
        Exceptions::fake();
        Http::fake(['sandbox-api.paddle.com/*' => Http::response(['error' => ['code' => 'not_found']], 404)]);

        $this->confirm()->assertRedirect(route('credits.index'));

        $this->assertSame(5, $this->user->fresh()->credits);
        Exceptions::assertNothingReported();
    }

    public function test_paddle_being_unreachable_is_reported_and_changes_nothing(): void
    {
        Exceptions::fake();
        Http::fake(['sandbox-api.paddle.com/*' => Http::response('Server error', 500)]);

        $this->confirm()->assertRedirect(route('credits.index'));

        $this->assertSame(5, $this->user->fresh()->credits);
        $this->assertSame(PurchaseStatus::Pending, $this->purchase->fresh()->status);
        Exceptions::assertReported(RequestException::class);
    }
}
