<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\CreditLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_accounts_get_welcome_credits(): void
    {
        $this->skipUnlessFortifyHas('registration');

        $this->post(route('register.store'), [
            'name' => 'Awsan',
            'email' => 'seller@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'seller@example.com')->firstOrFail();

        $this->assertSame(15, $user->credits);
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'amount' => 15,
            'balance_after' => 15,
            'reason' => CreditTransaction::SIGNUP,
        ]);
    }

    public function test_spending_more_than_the_balance_throws(): void
    {
        $user = User::factory()->withCredits(2)->create();

        $this->expectException(InsufficientCreditsException::class);

        try {
            app(CreditLedger::class)->spend($user, 3, 'Too much');
        } finally {
            $this->assertSame(2, $user->fresh()->credits);
            $this->assertDatabaseCount('credit_transactions', 0);
        }
    }

    public function test_admins_can_grant_credits_from_the_command_line(): void
    {
        $user = User::factory()->withCredits(1)->create(['email' => 'shop@example.com']);

        $this->artisan('credits:grant', ['email' => 'shop@example.com', 'amount' => 50, '--note' => 'Paid by bank transfer'])
            ->expectsOutputToContain('New balance: 51')
            ->assertSuccessful();

        $this->assertSame(51, $user->fresh()->credits);
        $this->assertDatabaseHas('credit_transactions', ['user_id' => $user->id, 'amount' => 50, 'description' => 'Paid by bank transfer']);

        $this->artisan('credits:grant', ['email' => 'nobody@example.com', 'amount' => 5])->assertFailed();
    }

    public function test_the_credits_page_shows_the_balance_and_history(): void
    {
        $user = User::factory()->withCredits(0)->create();
        app(CreditLedger::class)->add($user, 20, CreditTransaction::GRANT, 'Top-up');
        app(CreditLedger::class)->spend($user, 3, '3 listings');

        $this->actingAs($user)
            ->get(route('credits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/credits')
                ->where('balance', 17)
                ->has('transactions', 2)
                ->where('transactions.0.amount', -3)
                ->where('transactions.0.balance_after', 17)
            );
    }
}
