<?php

namespace Tests\Feature;

use App\Support\ContentHtml;
use Tests\TestCase;

class ContentImageSizeTest extends TestCase
{
    public function test_a_resized_photo_keeps_its_width_and_alt_text(): void
    {
        $html = ContentHtml::render('<p>Intro text for the photo.</p><img src="/uploads/a.webp" alt="Pipe under a sink" width="340" height="255"><p>More text.</p>', 'Title');

        $this->assertStringContainsString('width="340"', $html);
        $this->assertStringContainsString('style="width:340px"', $html);
        $this->assertStringContainsString('alt="Pipe under a sink"', $html);
        $this->assertStringNotContainsString('height=', $html);
    }

    public function test_bad_widths_and_pasted_styles_are_dropped(): void
    {
        $html = ContentHtml::render('<p>x</p><img src="/a.webp" alt="a" width="100%;background:url(x)" style="position:fixed"><p>y</p>', 'T');

        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringNotContainsString('width=', $html);
    }

    public function test_gallery_tiles_ignore_editor_widths(): void
    {
        $html = ContentHtml::render('<p><img src="/a.webp" alt="a" width="200"></p><p><img src="/b.webp" alt="b" width="300"></p>', 'T');

        $this->assertStringContainsString('content-gallery', $html);
        $this->assertStringNotContainsString('style=', $html);
    }
}
