<?php

namespace Tests\Feature\Listings;

use App\Ai\Agents\ListingWriter;
use App\Ai\Agents\ProductAnalyst;
use App\Enums\GenerationStatus;
use App\Enums\ListingStatus;
use App\Models\CreditTransaction;
use App\Models\Listing;
use App\Models\User;
use Database\Factories\ListingFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Image;
use Tests\TestCase;

class GenerateListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_guests_are_redirected_from_the_create_page(): void
    {
        $this->get(route('listings.create'))->assertRedirect(route('login'));
    }

    public function test_unverified_users_cannot_generate(): void
    {
        $user = User::factory()->unverified()->withCredits(10)->create();

        $this->actingAs($user)->get(route('listings.create'))->assertRedirect(route('verification.notice'));
    }

    public function test_the_create_page_lists_marketplaces_languages_and_photo_styles(): void
    {
        $user = User::factory()->withCredits(10)->create();

        $this->actingAs($user)
            ->get(route('listings.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('listings/create')
                ->has('marketplaces', 6)
                ->has('languages', 3)
                ->has('photoStyles', 5)
                ->where('costs.copy', 1)
                ->where('costs.photo', 2)
                ->where('credits', 10)
            );
    }

    public function test_photo_marketplaces_and_languages_are_required(): void
    {
        $user = User::factory()->withCredits(10)->create();

        $this->actingAs($user)
            ->post(route('listings.store'), [])
            ->assertSessionHasErrors(['photo', 'marketplaces', 'languages']);

        $this->assertDatabaseCount('listings', 0);
    }

    public function test_tiny_photos_are_rejected(): void
    {
        $user = User::factory()->withCredits(10)->create();

        $this->actingAs($user)
            ->post(route('listings.store'), [
                'photo' => UploadedFile::fake()->image('tiny.jpg', 100, 100),
                'marketplaces' => ['amazon'],
                'languages' => ['en'],
            ])
            ->assertSessionHasErrors('photo');
    }

    public function test_it_refuses_when_the_user_cannot_afford_the_generation(): void
    {
        $user = User::factory()->withCredits(3)->create();

        $this->actingAs($user)
            ->post(route('listings.store'), [
                'photo' => UploadedFile::fake()->image('shirt.jpg', 800, 800),
                'marketplaces' => ['amazon', 'trendyol'],
                'languages' => ['en', 'tr'],
            ])
            ->assertSessionHasErrors('credits');

        $this->assertDatabaseCount('listings', 0);
        $this->assertSame(3, $user->fresh()->credits);
    }

    public function test_it_generates_listings_and_photos_from_one_photo(): void
    {
        $user = User::factory()->withCredits(20)->create();

        ProductAnalyst::fake([ListingFactory::sampleFacts()]);
        ListingWriter::fake(function (string $prompt) {
            $market = str_contains($prompt, 'Write the Amazon listing') ? 'Amazon' : 'Trendyol';
            $arabic = str_contains($prompt, 'in Arabic');

            return [
                'title' => $arabic ? 'قميص كتان رجالي بأكمام قصيرة' : "{$market} linen shirt for men",
                'highlight' => $market === 'Amazon' ? 'Breathable linen, chest pocket, relaxed fit' : null,
                'bullets' => ['Breathable: linen keeps you cool', 'Easy fit: relaxed cut', 'Practical: chest pocket'],
                'description' => "A relaxed linen shirt for warm days.\n\n- Short sleeves\n- Chest pocket",
                'keywords' => ['linen shirt', 'summer shirt', 'men shirt'],
                'category' => 'Clothing > Men > Shirts',
            ];
        });
        Image::fake();

        $response = $this->actingAs($user)->post(route('listings.store'), [
            'photo' => UploadedFile::fake()->image('shirt.png', 1200, 900),
            'notes' => '100% linen, sizes S to XL',
            'marketplaces' => ['amazon', 'trendyol'],
            'languages' => ['en', 'ar'],
            'photo_styles' => ['white_background', 'lifestyle'],
        ]);

        $listing = Listing::firstOrFail();
        $response->assertRedirect(route('listings.show', $listing));

        // 4 listings x 1 credit + 2 photos x 2 credits.
        $this->assertSame(12, $user->fresh()->credits);
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'listing_id' => $listing->id,
            'amount' => -8,
            'reason' => CreditTransaction::GENERATION,
        ]);

        $listing->refresh();
        $this->assertSame(ListingStatus::Completed, $listing->status);
        $this->assertSame("Men's short-sleeve linen shirt", $listing->name);
        $this->assertSame('linen', $listing->product_facts['materials'][0]);
        Storage::disk('local')->assertExists($listing->source_path);

        $this->assertCount(4, $listing->copies);
        $this->assertTrue($listing->copies->every(fn ($c) => $c->status === GenerationStatus::Completed));
        $arabicAmazon = $listing->copies->first(fn ($c) => $c->marketplace->value === 'amazon' && $c->language->value === 'ar');
        $this->assertSame('قميص كتان رجالي بأكمام قصيرة', $arabicAmazon->title);
        $this->assertSame('Breathable linen, chest pocket, relaxed fit', $arabicAmazon->highlight);
        $this->assertNull($listing->copies->first(fn ($c) => $c->marketplace->value === 'trendyol')->highlight);

        $this->assertCount(2, $listing->photos);
        foreach ($listing->photos as $photo) {
            $this->assertSame(GenerationStatus::Completed, $photo->status);
            Storage::disk('local')->assertExists($photo->path);
        }

        ProductAnalyst::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '100% linen, sizes S to XL')
            && count($prompt->attachments) === 1);
        ListingWriter::assertPromptedTimes(4);
        Image::assertGenerated(fn ($prompt) => str_contains($prompt->prompt, 'pure white (#FFFFFF)')
            && count($prompt->attachments) === 1);
    }

    public function test_a_photo_without_a_product_fails_and_refunds(): void
    {
        $user = User::factory()->withCredits(10)->create();

        ProductAnalyst::fake([[
            ...ListingFactory::sampleFacts(),
            'is_product' => false,
            'rejection_reason' => 'The photo shows a document, not a product.',
        ]]);
        ListingWriter::fake();
        Image::fake();

        $this->actingAs($user)->post(route('listings.store'), [
            'photo' => UploadedFile::fake()->image('scan.jpg', 800, 800),
            'marketplaces' => ['noon'],
            'languages' => ['ar'],
            'photo_styles' => ['detail'],
        ]);

        $listing = Listing::firstOrFail();
        $this->assertSame(ListingStatus::Failed, $listing->status);
        $this->assertSame('The photo shows a document, not a product. Your credits were refunded.', $listing->error);
        $this->assertSame(10, $user->fresh()->credits);
        $this->assertSame(GenerationStatus::Failed, $listing->copies->first()->status);

        ListingWriter::assertNeverPrompted();
        Image::assertNothingGenerated();
    }
}
