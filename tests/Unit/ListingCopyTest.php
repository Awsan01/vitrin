<?php

namespace Tests\Unit;

use App\Models\ListingCopy;
use Tests\TestCase;

class ListingCopyTest extends TestCase
{
    public function test_the_html_description_is_built_from_escaped_text(): void
    {
        $copy = new ListingCopy([
            'description' => "Soft <script>alert(1)</script> linen.\nSecond line.\n\n- <b>Breathable</b>\n- Relaxed & cool",
        ]);

        $this->assertSame(
            "<p>Soft &lt;script&gt;alert(1)&lt;/script&gt; linen.<br>Second line.</p>\n"
            .'<ul><li>&lt;b&gt;Breathable&lt;/b&gt;</li><li>Relaxed &amp; cool</li></ul>',
            $copy->descriptionHtml(),
        );
    }
}
