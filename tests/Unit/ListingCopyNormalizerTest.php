<?php

namespace Tests\Unit;

use App\Enums\Marketplace;
use App\Support\ListingCopyNormalizer;
use Tests\TestCase;

class ListingCopyNormalizerTest extends TestCase
{
    protected function raw(array $overrides = []): array
    {
        return [
            'title' => 'Linen shirt',
            'highlight' => null,
            'bullets' => ['One', 'Two', 'Three', 'Four', 'Five'],
            'description' => 'Text',
            'keywords' => ['linen'],
            'category' => 'Clothing',
            ...$overrides,
        ];
    }

    public function test_amazon_titles_are_cut_to_75_characters_at_a_word_boundary(): void
    {
        $title = 'Premium Men\'s Short Sleeve Linen Button-Down Shirt with Chest Pocket for Summer Beach Holidays';

        $result = ListingCopyNormalizer::normalize($this->raw(['title' => $title]), Marketplace::Amazon);

        $this->assertLessThanOrEqual(75, mb_strlen($result['title']));
        $this->assertStringEndsNotWith(' ', $result['title']);
        $this->assertSame("Premium Men's Short Sleeve Linen Button-Down Shirt with Chest Pocket for", $result['title']);
        $this->assertStringContainsString('Title shortened', $result['warnings'][0]);
    }

    public function test_arabic_length_is_counted_in_characters_not_bytes(): void
    {
        $title = 'قميص كتان رجالي بأكمام قصيرة وجيب على الصدر';

        $result = ListingCopyNormalizer::normalize($this->raw(['title' => $title]), Marketplace::Amazon);

        $this->assertSame($title, $result['title']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_etsy_tags_longer_than_20_characters_are_dropped(): void
    {
        $result = ListingCopyNormalizer::normalize($this->raw([
            'keywords' => ['linen shirt', 'a very long tag that will not fit', 'Linen Shirt', '#summer'],
        ]), Marketplace::Etsy);

        $this->assertSame(['linen shirt', 'summer'], $result['keywords']);
        $this->assertStringContainsString('longer than 20 characters', implode(' ', $result['warnings']));
    }

    public function test_amazon_search_terms_fit_in_250_bytes(): void
    {
        $keywords = array_map(fn ($i) => "keyword{$i} phrase", range(1, 25));

        $result = ListingCopyNormalizer::normalize($this->raw(['keywords' => $keywords]), Marketplace::Amazon);

        $this->assertLessThanOrEqual(250, strlen(implode(' ', $result['keywords'])));
        $this->assertStringContainsString('250 bytes', implode(' ', $result['warnings']));
    }

    public function test_emoji_markdown_and_list_markers_are_cleaned(): void
    {
        $result = ListingCopyNormalizer::normalize($this->raw([
            'title' => '🔥 **Linen Shirt** 🔥',
            'bullets' => ['- Breathable fabric ✅', '2. Relaxed fit', '- Breathable fabric'],
            'description' => "## About\n\n• Soft\n• Cool\n\n\n\nEnd",
        ]), Marketplace::Trendyol);

        $this->assertSame('Linen Shirt', $result['title']);
        $this->assertSame(['Breathable fabric', 'Relaxed fit'], $result['bullets']);
        $this->assertSame("About\n\n- Soft\n- Cool\n\nEnd", $result['description']);
        $this->assertStringContainsString('Only 2 of 5 bullet points', implode(' ', $result['warnings']));
    }

    public function test_numbers_at_the_start_of_a_bullet_are_kept_unless_they_are_list_markers(): void
    {
        $result = ListingCopyNormalizer::normalize($this->raw([
            'bullets' => ['2.4G wireless and two Bluetooth channels', '3) DPI button', '1600 DPI sensor'],
        ]), Marketplace::Trendyol);

        $this->assertSame(['2.4G wireless and two Bluetooth channels', 'DPI button', '1600 DPI sensor'], $result['bullets']);
    }

    public function test_highlights_are_dropped_on_marketplaces_without_them(): void
    {
        $result = ListingCopyNormalizer::normalize($this->raw(['highlight' => 'Something']), Marketplace::Noon);

        $this->assertNull($result['highlight']);
    }

    public function test_short_noon_titles_get_a_warning(): void
    {
        $result = ListingCopyNormalizer::normalize($this->raw(['title' => 'Shirt']), Marketplace::Noon);

        $this->assertStringContainsString('shorter than 20 characters', implode(' ', $result['warnings']));
    }
}
