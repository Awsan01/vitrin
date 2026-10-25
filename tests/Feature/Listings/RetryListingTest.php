<?php

namespace Tests\Feature\Listings;

use App\Enums\GenerationStatus;
use App\Enums\Language;
use App\Enums\ListingStatus;
use App\Enums\Marketplace;
use App\Enums\PhotoStyle;
use App\Jobs\AnalyzeListing;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RetryListingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // A listing whose photo couldn't be analyzed: everything failed and was refunded.
        $this->owner = User::factory()->withCredits(10)->create();
        $this->listing = Listing::factory()->for($this->owner)->create([
            'status' => ListingStatus::Failed,
            'error' => "The AI service couldn't analyze this photo. Your credits were refunded. Try again in a few minutes.",
        ]);

        $this->listing->copies()->create(['marketplace' => Marketplace::Trendyol, 'language' => Language::Turkish, 'cost' => 1, 'status' => GenerationStatus::Failed]);
        $this->listing->copies()->create(['marketplace' => Marketplace::Noon, 'language' => Language::Arabic, 'cost' => 1, 'status' => GenerationStatus::Failed]);
        $this->listing->photos()->create(['style' => PhotoStyle::Lifestyle, 'cost' => 2, 'status' => GenerationStatus::Failed]);
    }

    public function test_the_results_page_offers_to_try_again(): void
    {
        $this->actingAs($this->owner)
            ->get(route('listings.show', $this->listing))
            ->assertInertia(fn (Assert $page) => $page
                ->where('listing.status', 'failed')
                ->where('listing.can_retry', true)
            );
    }

    public function test_a_failed_listing_runs_again_with_the_same_choices(): void
    {
        $this->actingAs($this->owner)
            ->from(route('listings.show', $this->listing))
            ->post(route('listings.retry', $this->listing))
            ->assertRedirect(route('listings.show', $this->listing))
            ->assertInertiaFlash('toast.type', 'success');

        $this->listing->refresh();
        $this->assertSame(ListingStatus::Queued, $this->listing->status);
        $this->assertNull($this->listing->error);
        $this->assertSame(6, $this->owner->fresh()->credits);
        $this->assertDatabaseHas('credit_transactions', [
            'listing_id' => $this->listing->id,
            'amount' => -4,
            'description' => 'Tried again: 2 listings and 1 photo',
        ]);

        foreach ($this->listing->copies->concat($this->listing->photos) as $item) {
            $this->assertSame(GenerationStatus::Pending, $item->status);
        }

        Queue::assertPushed(AnalyzeListing::class, fn ($job) => $job->listing->is($this->listing));
    }

    public function test_trying_again_twice_only_charges_once(): void
    {
        $this->actingAs($this->owner)->post(route('listings.retry', $this->listing));
        $this->actingAs($this->owner)
            ->post(route('listings.retry', $this->listing))
            ->assertInertiaFlash('toast.type', 'warning');

        $this->assertSame(6, $this->owner->fresh()->credits);
        Queue::assertPushed(AnalyzeListing::class, 1);
    }

    public function test_trying_again_needs_enough_credits(): void
    {
        $this->owner->forceFill(['credits' => 3])->save();

        $this->actingAs($this->owner)
            ->post(route('listings.retry', $this->listing))
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertSame(3, $this->owner->fresh()->credits);
        $this->assertSame(ListingStatus::Failed, $this->listing->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_listings_that_were_analyzed_are_not_run_again_as_a_whole(): void
    {
        $listing = Listing::factory()->analyzed()->for($this->owner)->create(['status' => ListingStatus::Partial]);

        $this->actingAs($this->owner)
            ->post(route('listings.retry', $listing))
            ->assertInertiaFlash('toast.type', 'warning');

        $this->assertSame(10, $this->owner->fresh()->credits);
        Queue::assertNothingPushed();
    }

    public function test_other_users_cannot_try_again(): void
    {
        $this->actingAs(User::factory()->withCredits(10)->create())
            ->post(route('listings.retry', $this->listing))
            ->assertForbidden();

        Queue::assertNothingPushed();
    }
}
