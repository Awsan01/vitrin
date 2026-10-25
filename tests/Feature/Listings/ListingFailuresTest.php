<?php

namespace Tests\Feature\Listings;

use App\Enums\GenerationStatus;
use App\Enums\Language;
use App\Enums\ListingStatus;
use App\Enums\Marketplace;
use App\Enums\PhotoStyle;
use App\Jobs\AnalyzeListing;
use App\Jobs\GenerateListingPhoto;
use App\Jobs\WriteListingCopy;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ListingFailuresTest extends TestCase
{
    use RefreshDatabase;

    protected function makeListing(): Listing
    {
        $user = User::factory()->withCredits(5)->create();
        $listing = Listing::factory()->analyzed()->for($user)->create();

        $listing->copies()->create(['marketplace' => Marketplace::Amazon, 'language' => Language::English, 'cost' => 1, 'status' => GenerationStatus::Completed, 'title' => 'Done']);
        $listing->copies()->create(['marketplace' => Marketplace::Noon, 'language' => Language::Arabic, 'cost' => 1, 'status' => GenerationStatus::Processing]);
        $listing->photos()->create(['style' => PhotoStyle::Lifestyle, 'cost' => 2, 'status' => GenerationStatus::Pending]);

        return $listing;
    }

    public function test_a_failed_copy_is_refunded_and_the_listing_finishes_as_partial(): void
    {
        $listing = $this->makeListing();
        $copy = $listing->copies()->where('marketplace', 'noon')->firstOrFail();
        $photo = $listing->photos()->firstOrFail();

        (new WriteListingCopy($copy))->failed(new RuntimeException('API down'));

        $this->assertSame(GenerationStatus::Failed, $copy->fresh()->status);
        $this->assertSame("The AI service didn't return a usable listing. 1 credit was refunded.", $copy->fresh()->error);
        $this->assertSame(6, $listing->user->fresh()->credits);
        $this->assertDatabaseHas('credit_transactions', ['listing_id' => $listing->id, 'amount' => 1, 'reason' => 'refund']);
        $this->assertSame(ListingStatus::Generating, $listing->fresh()->status);

        $photo->update(['status' => GenerationStatus::Completed, 'path' => 'x.png']);
        $listing->syncStatus();

        $this->assertSame(ListingStatus::Partial, $listing->fresh()->status);
    }

    public function test_failing_an_item_twice_only_refunds_once(): void
    {
        $listing = $this->makeListing();
        $photo = $listing->photos()->firstOrFail();

        (new GenerateListingPhoto($photo))->failed(new RuntimeException('timeout'));
        (new GenerateListingPhoto($photo))->failed(new RuntimeException('timeout'));

        $this->assertSame(7, $listing->user->fresh()->credits);
        $this->assertSame("The AI service didn't return a photo. 2 credits were refunded.", $photo->fresh()->error);
    }

    public function test_a_failed_analysis_fails_the_listing_and_refunds_unfinished_items(): void
    {
        $listing = $this->makeListing();

        (new AnalyzeListing($listing))->failed(new RuntimeException('API down'));

        $listing->refresh();
        $this->assertSame(ListingStatus::Failed, $listing->status);
        $this->assertNotNull($listing->error);
        // The listing carries the message; the items don't repeat it.
        $this->assertSame([null, null], [
            $listing->copies()->where('marketplace', 'noon')->value('error'),
            $listing->photos()->value('error'),
        ]);
        // The noon copy (1) and the photo (2) were unfinished; the Amazon copy was done.
        $this->assertSame(8, $listing->user->fresh()->credits);
        $this->assertSame('Done', $listing->copies()->where('marketplace', 'amazon')->value('title'));
    }
}
