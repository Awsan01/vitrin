<?php

namespace Tests\Feature\Billing;

use App\Data\CreditPack;
use App\Models\CreditPurchase;
use App\Models\User;
use App\Services\CreditPurchases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use InteractsWithPaddle, RefreshDatabase;

    protected User $user;

    protected CreditPurchase $purchase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePaddle();
        Http::preventStrayRequests();

        $this->user = User::factory()->create();
        $this->purchase = app(CreditPurchases::class)->start($this->user, CreditPack::find('growth'));
        $this->postWebhook($this->transactionCompleted($this->purchase));
    }

    public function test_the_buyer_downloads_the_invoice_paddle_issued(): void
    {
        Http::fake(['sandbox-api.paddle.com/transactions/txn_01test/invoice*' => Http::response([
            'data' => ['url' => 'https://invoices.paddle.test/inv_01.pdf?signature=abc'],
        ])]);

        $this->actingAs($this->user)
            ->get(route('credits.purchases.invoice', $this->purchase))
            ->assertRedirect('https://invoices.paddle.test/inv_01.pdf?signature=abc');

        Http::assertSent(fn (Request $request) => $request['disposition'] === 'attachment');
    }

    public function test_invoices_are_only_for_the_buyer(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->get(route('credits.purchases.invoice', $this->purchase))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_unpaid_purchases_have_no_invoice(): void
    {
        Http::fake();
        $unpaid = app(CreditPurchases::class)->start($this->user, CreditPack::find('starter'));

        $this->actingAs($this->user)
            ->get(route('credits.purchases.invoice', $unpaid))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_an_invoice_paddle_cannot_provide_shows_an_error(): void
    {
        Exceptions::fake();
        Http::fake(['sandbox-api.paddle.com/*' => Http::response('Server error', 500)]);

        $this->actingAs($this->user)
            ->from(route('credits.index'))
            ->get(route('credits.purchases.invoice', $this->purchase))
            ->assertRedirect(route('credits.index'))
            ->assertInertiaFlash('toast.type', 'error');

        Exceptions::assertReported(RequestException::class);
    }
}
