<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_are_sent_with_browser_protections(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'self'; object-src 'none'")
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_https_is_enforced_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_the_appearance_cookie_only_accepts_known_values(): void
    {
        $this->withUnencryptedCookie('appearance', "dark';alert(document.cookie);'")
            ->get(route('home'))
            ->assertOk()
            ->assertSee("const appearance = 'system';", false)
            ->assertDontSee('alert(document.cookie)', false);

        $this->withUnencryptedCookie('appearance', 'dark')
            ->get(route('home'))
            ->assertSee("const appearance = 'dark';", false);
    }
}
