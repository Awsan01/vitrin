<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFormRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_ups_are_limited_per_ip_address(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('register.store'), [])->assertSessionHasErrors();
        }

        $this->post(route('register.store'), [])->assertTooManyRequests();
    }

    public function test_password_reset_emails_are_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => "someone{$i}@example.com"])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'someone@example.com'])->assertTooManyRequests();
    }

    public function test_password_confirmation_cannot_be_brute_forced(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)
                ->post(route('password.confirm.store'), ['password' => "wrong-{$i}"])
                ->assertSessionHasErrors('password');
        }

        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertTooManyRequests();
    }

    public function test_pages_and_other_forms_are_not_limited(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->get(route('register'))->assertOk();
        }
    }
}
