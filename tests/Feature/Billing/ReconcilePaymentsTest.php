<?php

namespace Tests\Feature\Billing;

use App\Data\CreditPack;
use App\Enums\PurchaseStatus;
use App\Models\User;
use App\Services\CreditPurchases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * paddle:reconcile, the scheduled backup for payments whose webhook never
 * arrived and whose buyer closed the page before it could confirm them.
 */
class ReconcilePaymentsTest extends TestCase
{
    use InteractsWithPaddle, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePaddle();
        Http::preventStrayRequests();
    }

    public function test_paid_checkouts_still_waiting_get_their_credits(): void
    {
        Log::spy();
        $purchases = app(CreditPurchases::class);
        $user = User::factory()->withCredits(5)->create();
        $growth = $purchases->start($user, CreditPack::find('growth'));
        $starter = $purchases->start($user, CreditPack::find('starter'));
        $abandoned = $purchases->start($user, CreditPack::find('business'));

        // Two pages: Paddle returns at most 30 transactions at a time.
        Http::fake(['sandbox-api.paddle.com/transactions*' => fn (Request $request) => Http::response(
            str_contains($request->url(), 'after=')
                ? ['data' => [$this->paddleTransaction($starter, ['id' => 'txn_02'])], 'meta' => ['pagination' => ['has_more' => false]]]
                : ['data' => [
                    $this->paddleTransaction($growth, ['id' => 'txn_01']),
                    ['id' => 'txn_sold_elsewhere', 'status' => 'completed', 'custom_data' => null],
                ], 'meta' => ['pagination' => ['has_more' => true]]],
        )]);

        $this->artisan('paddle:reconcile')
            ->expectsOutputToContain('Added the credits for 2 paid purchases.')
            ->assertSuccessful();

        $this->assertSame(405, $user->fresh()->credits);
        $this->assertSame(PurchaseStatus::Completed, $growth->fresh()->status);
        $this->assertSame(PurchaseStatus::Completed, $starter->fresh()->status);
        $this->assertSame(PurchaseStatus::Pending, $abandoned->fresh()->status);

        $since = $growth->created_at->subMinutes(10)->toIso8601ZuluString();
        Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), "status=completed&created_at[GTE]={$since}"));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'after=txn_sold_elsewhere'));
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'paddle:check'))
            ->once();

        // Running again changes nothing.
        $this->artisan('paddle:reconcile')
            ->expectsOutputToContain('No paid checkouts are waiting for credits.')
            ->assertSuccessful();

        $this->assertSame(405, $user->fresh()->credits);
    }

    public function test_paddle_is_not_asked_without_recent_unpaid_checkouts(): void
    {
        Http::fake();
        app(CreditPurchases::class)->start(User::factory()->create(), CreditPack::find('growth'));

        $this->travel(3)->days();

        $this->artisan('paddle:reconcile')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_nothing_happens_while_payments_are_off(): void
    {
        Http::fake();
        config(['services.paddle.api_key' => null]);
        app(CreditPurchases::class)->start(User::factory()->create(), CreditPack::find('growth'));

        $this->artisan('paddle:reconcile')
            ->expectsOutputToContain('Payments are off')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_it_is_scheduled(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('paddle:reconcile')
            ->assertSuccessful();
    }
}
