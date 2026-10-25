<?php

namespace Tests\Feature\Listings;

use App\Enums\GenerationStatus;
use App\Enums\Language;
use App\Enums\ListingStatus;
use App\Enums\Marketplace;
use App\Enums\PhotoStyle;
use App\Jobs\GenerateListingPhoto;
use App\Jobs\WriteListingCopy;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManageListingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->owner = User::factory()->withCredits(10)->create();
        $this->listing = Listing::factory()->analyzed()->for($this->owner)->create([
            'status' => ListingStatus::Completed,
        ]);

        Storage::disk('local')->put($this->listing->source_path, 'source-bytes');
        Storage::disk('local')->put("listings/{$this->listing->id}/photos/white.png", 'photo-bytes');

        $this->listing->copies()->create([
            'marketplace' => Marketplace::Hepsiburada,
            'language' => Language::Turkish,
            'cost' => 1,
            'status' => GenerationStatus::Completed,
            'title' => 'Erkek Keten Gömlek Kısa Kollu',
            'bullets' => ['Nefes alan keten kumaş'],
            'description' => "Yaz için rahat bir gömlek.\n\n- Kısa kollu\n- Göğüs cepli",
            'keywords' => ['keten gömlek', 'yazlık gömlek'],
            'category' => 'Giyim > Erkek > Gömlek',
            'warnings' => [],
        ]);

        $this->listing->photos()->create([
            'style' => PhotoStyle::WhiteBackground,
            'cost' => 2,
            'status' => GenerationStatus::Completed,
            'path' => "listings/{$this->listing->id}/photos/white.png",
        ]);
    }

    public function test_the_owner_sees_the_results_page(): void
    {
        $this->actingAs($this->owner)
            ->get(route('listings.show', $this->listing))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('listings/show')
                ->where('listing.id', $this->listing->id)
                ->where('listing.is_processing', false)
                ->where('listing.progress.done', 2)
                ->has('listing.copies', 1, fn (Assert $copy) => $copy
                    ->where('marketplace_label', 'Hepsiburada')
                    ->where('dir', 'ltr')
                    ->where('title', 'Erkek Keten Gömlek Kısa Kollu')
                    ->where('description_html', "<p>Yaz için rahat bir gömlek.</p>\n<ul><li>Kısa kollu</li><li>Göğüs cepli</li></ul>")
                    ->where('limits.title_max', 255)
                    ->etc()
                )
                ->has('listing.photos', 1, fn (Assert $photo) => $photo
                    ->where('style_label', 'White background')
                    ->whereType('url', 'string')
                    ->etc()
                )
            );
    }

    public function test_other_users_cannot_see_or_touch_a_listing(): void
    {
        $intruder = User::factory()->withCredits(10)->create();
        $photo = $this->listing->photos()->first();
        $copy = $this->listing->copies()->first();

        $this->actingAs($intruder)->get(route('listings.show', $this->listing))->assertForbidden();
        $this->actingAs($intruder)->get(route('listings.source', $this->listing))->assertForbidden();
        $this->actingAs($intruder)->get(route('listings.export', $this->listing))->assertForbidden();
        $this->actingAs($intruder)->get(route('listings.photos.show', [$this->listing, $photo]))->assertForbidden();
        $this->actingAs($intruder)->post(route('listings.copies.regenerate', [$this->listing, $copy]))->assertForbidden();
        $this->actingAs($intruder)->delete(route('listings.destroy', $this->listing))->assertForbidden();

        $this->assertModelExists($this->listing);
    }

    public function test_photos_are_scoped_to_their_listing(): void
    {
        $otherListing = Listing::factory()->analyzed()->for($this->owner)->create();
        $photo = $this->listing->photos()->first();

        $this->actingAs($this->owner)
            ->get(route('listings.photos.show', [$otherListing, $photo]))
            ->assertNotFound();
    }

    public function test_the_owner_can_view_and_download_files(): void
    {
        $photo = $this->listing->photos()->first();

        $this->actingAs($this->owner)->get(route('listings.source', $this->listing))->assertOk();

        $this->actingAs($this->owner)
            ->get(route('listings.photos.show', [$this->listing, $photo, 'download' => 1]))
            ->assertOk()
            ->assertDownload('linen-shirt-white-background.png');

        $this->actingAs($this->owner)
            ->get(route('listings.photos.download', $this->listing))
            ->assertOk()
            ->assertDownload('linen-shirt-photos.zip');
    }

    public function test_the_csv_export_opens_in_excel_with_turkish_text(): void
    {
        $response = $this->actingAs($this->owner)->get(route('listings.export', $this->listing));

        $response->assertOk()->assertDownload('linen-shirt-listings.csv');
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Erkek Keten Gömlek Kısa Kollu', $csv);
        $this->assertStringContainsString('keten gömlek, yazlık gömlek', $csv);
    }

    public function test_regenerating_a_copy_charges_a_credit_and_queues_a_job(): void
    {
        Queue::fake();
        $copy = $this->listing->copies()->first();

        $this->actingAs($this->owner)
            ->from(route('listings.show', $this->listing))
            ->post(route('listings.copies.regenerate', [$this->listing, $copy]))
            ->assertRedirect(route('listings.show', $this->listing));

        $this->assertSame(9, $this->owner->fresh()->credits);
        $this->assertSame(GenerationStatus::Pending, $copy->fresh()->status);
        $this->assertSame(ListingStatus::Generating, $this->listing->fresh()->status);
        Queue::assertPushed(WriteListingCopy::class, fn ($job) => $job->copy->is($copy));
    }

    public function test_regenerating_a_photo_needs_enough_credits(): void
    {
        Queue::fake();
        $this->owner->forceFill(['credits' => 1])->save();
        $photo = $this->listing->photos()->first();

        $this->actingAs($this->owner)
            ->post(route('listings.photos.regenerate', [$this->listing, $photo]))
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertSame(1, $this->owner->fresh()->credits);
        $this->assertSame(GenerationStatus::Completed, $photo->fresh()->status);
        Queue::assertNotPushed(GenerateListingPhoto::class);
    }

    public function test_regenerating_twice_only_charges_once(): void
    {
        Queue::fake();
        $copy = $this->listing->copies()->first();

        $this->actingAs($this->owner)->post(route('listings.copies.regenerate', [$this->listing, $copy]));
        $this->actingAs($this->owner)
            ->post(route('listings.copies.regenerate', [$this->listing, $copy]))
            ->assertInertiaFlash('toast.type', 'warning');

        $this->assertSame(9, $this->owner->fresh()->credits);
        Queue::assertPushed(WriteListingCopy::class, 1);
    }

    public function test_items_still_generating_cannot_be_regenerated(): void
    {
        Queue::fake();
        $copy = $this->listing->copies()->first();
        $copy->update(['status' => GenerationStatus::Processing]);

        $this->actingAs($this->owner)->post(route('listings.copies.regenerate', [$this->listing, $copy]));

        $this->assertSame(10, $this->owner->fresh()->credits);
        Queue::assertNothingPushed();
    }

    public function test_deleting_a_listing_removes_its_files(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('listings.destroy', $this->listing))
            ->assertRedirect(route('dashboard'));

        $this->assertModelMissing($this->listing);
        $this->assertDatabaseCount('listing_copies', 0);
        Storage::disk('local')->assertMissing($this->listing->source_path);
    }

    public function test_the_dashboard_only_shows_the_users_own_listings(): void
    {
        Listing::factory()->analyzed()->create(['name' => 'Someone else']);

        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('listings', 1, fn (Assert $card) => $card
                    ->where('name', 'Linen shirt')
                    ->where('marketplaces', ['Hepsiburada'])
                    ->where('languages', ['tr'])
                    ->etc()
                )
                ->where('pagination.total', 1)
            );
    }
}
