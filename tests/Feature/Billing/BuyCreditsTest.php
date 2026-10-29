<?php

namespace Tests\Feature\Billing;

use App\Enums\PurchaseStatus;
use App\Models\CreditPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuyCreditsTest extends TestCase
{
    use InteractsWithPaddle, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePaddle();
        Http::preventStrayRequests();
    }

    protected function fakePaddlePrices(): void
    {
        Http::fake([
            'sandbox-api.paddle.com/prices*' => Http::response(['data' => [
                ['id' => 'pri_starter', 'unit_price' => ['amount' => '900', 'currency_code' => 'USD']],
                ['id' => 'pri_growth', 'unit_price' => ['amount' => '2400', 'currency_code' => 'USD']],
                ['id' => 'pri_business', 'unit_price' => ['amount' => '6900', 'currency_code' => 'USD']],
            ]]),
        ]);
    }

    public function test_buying_a_pack_records_a_pending_purchase_and_opens_the_checkout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('credits.index'))
            ->post(route('credits.purchases.store'), ['pack' => 'growth'])
            ->assertRedirect(route('credits.index'));

        $purchase = CreditPurchase::sole();
        $this->assertSame($user->id, $purchase->user_id);
        $this->assertSame(PurchaseStatus::Pending, $purchase->status);
        $this->assertSame(300, $purchase->credits);
        $this->assertSame('pri_growth', $purchase->price_id);

        $response
            ->assertInertiaFlash('checkout.purchase_id', $purchase->id)
            ->assertInertiaFlash('checkout.price_id', 'pri_growth')
            ->assertInertiaFlash('checkout.email', $user->email);
    }

    public function test_only_packs_with_a_paddle_price_can_be_bought(): void
    {
        config(['vitrin.packs.business.price_id' => null]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('credits.purchases.store'), ['pack' => 'gold'])->assertSessionHasErrors('pack');
        $this->actingAs($user)->post(route('credits.purchases.store'), ['pack' => 'business'])->assertSessionHasErrors('pack');

        $this->assertDatabaseCount('credit_purchases', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredSettings(): array
    {
        return [
            'client-side token' => ['services.paddle.client_token'],
            'API key' => ['services.paddle.api_key'],
            'webhook secret' => ['services.paddle.webhook_secret'],
        ];
    }

    #[DataProvider('requiredSettings')]
    public function test_nothing_can_be_bought_until_paddle_is_set_up(string $setting): void
    {
        config([$setting => null]);

        $this->actingAs(User::factory()->create())
            ->post(route('credits.purchases.store'), ['pack' => 'growth'])
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertDatabaseCount('credit_purchases', 0);
    }

    public function test_the_credits_page_names_missing_settings_only_in_debug_mode(): void
    {
        config(['services.paddle.api_key' => null, 'services.paddle.webhook_secret' => null, 'app.debug' => true]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('credits.index'))->assertInertia(fn (Assert $page) => $page
            ->where('paddle', null)
            ->where('packs.0.purchasable', false)
            ->where('missingSettings', ['PADDLE_API_KEY', 'PADDLE_WEBHOOK_SECRET']));

        config(['app.debug' => false]);

        $this->actingAs($user)->get(route('credits.index'))->assertInertia(fn (Assert $page) => $page
            ->where('missingSettings', null));
    }

    public function test_guests_and_unverified_users_cannot_buy(): void
    {
        $this->post(route('credits.purchases.store'), ['pack' => 'growth'])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('credits.purchases.store'), ['pack' => 'growth'])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('credit_purchases', 0);
    }

    public function test_opening_checkouts_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post(route('credits.purchases.store'), ['pack' => 'starter'])->assertRedirect();
        }

        $this->actingAs($user)->post(route('credits.purchases.store'), ['pack' => 'starter'])->assertTooManyRequests();
    }

    public function test_the_credits_page_offers_the_packs_and_lists_paid_purchases(): void
    {
        $this->fakePaddlePrices();
        $user = User::factory()->create();
        $user->creditPurchases()->create(['pack' => 'growth', 'price_id' => 'pri_growth', 'credits' => 300, 'status' => PurchaseStatus::Pending]);
        $user->creditPurchases()->create([
            'pack' => 'starter', 'price_id' => 'pri_starter', 'credits' => 100, 'status' => PurchaseStatus::Completed,
            'paddle_transaction_id' => 'txn_01test', 'amount' => 900, 'currency' => 'USD', 'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('credits.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/credits')
                ->where('paddle', ['environment' => 'sandbox', 'token' => 'test_client_token'])
                ->has('packs', 3)
                ->where('packs.1.name', 'Growth')
                ->where('packs.1.price', '$24.00')
                ->where('packs.1.purchasable', true)
                ->has('purchases', 1)
                ->where('purchases.0.pack', 'Starter')
                ->where('purchases.0.amount', '$9.00')
                ->where('purchases.0.status_label', 'Paid')
                ->where('purchases.0.invoice', true));
    }

    public function test_paddle_prices_are_fetched_once_and_cached(): void
    {
        $this->fakePaddlePrices();

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('packs.0.price', '$9.00')
            ->where('packs.2.price', '$69.00'));
        $this->get(route('home'))->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test_api_key')
            && $request['id'] === 'pri_starter,pri_growth,pri_business');
    }

    public function test_the_site_still_works_when_paddle_cannot_be_reached(): void
    {
        Http::fake(['sandbox-api.paddle.com/*' => Http::response('Server error', 500)]);

        $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('packs.0.credits', 100)
            ->where('packs.0.price', null));
    }

    public function test_abandoned_checkouts_are_pruned_after_a_month(): void
    {
        $user = User::factory()->create();
        $abandoned = $user->creditPurchases()->create(['pack' => 'growth', 'price_id' => 'pri_growth', 'credits' => 300, 'status' => PurchaseStatus::Pending]);
        $paid = $user->creditPurchases()->create(['pack' => 'growth', 'price_id' => 'pri_growth', 'credits' => 300, 'status' => PurchaseStatus::Completed]);

        $this->travel(32)->days();
        $this->artisan('model:prune', ['--model' => [CreditPurchase::class]])->assertSuccessful();

        $this->assertModelMissing($abandoned);
        $this->assertModelExists($paid);
    }

    public function test_the_policies_are_public_and_name_the_business(): void
    {
        config(['vitrin.business.legal_name' => 'Ayşe Yılmaz', 'vitrin.business.email' => 'help@vitrin.test']);

        foreach (['legal.terms' => 'legal/terms', 'legal.privacy' => 'legal/privacy', 'legal.refunds' => 'legal/refunds'] as $route => $component) {
            $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->where('business.legal_name', 'Ayşe Yılmaz')
                ->where('business.email', 'help@vitrin.test'));
        }
    }
}
