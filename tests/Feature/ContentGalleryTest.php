<?php

namespace Tests\Feature;

use App\Support\ContentHtml;
use Tests\TestCase;

/** Photos in an article: photos in a row become a gallery, a photo set beside the text stays beside it. */
class ContentGalleryTest extends TestCase
{
    public function test_photos_in_a_row_become_a_gallery_with_every_photo(): void
    {
        $html = ContentHtml::render('<p>Intro</p><img src="/a.jpg" alt="A"><img src="/b.jpg" alt="B"><p><img src="/c.jpg" alt="C"></p><h2>Next</h2><p>Text</p>');

        $this->assertStringContainsString('class="content-gallery" data-count="3"', $html);
        foreach (['/a.jpg', '/b.jpg', '/c.jpg'] as $src) {
            $this->assertMatchesRegularExpression('#<figure><img[^>]*src="' . preg_quote($src, '#') . '"#', $html);
        }
    }

    public function test_a_photo_set_beside_the_text_stays_out_of_the_gallery(): void
    {
        $html = ContentHtml::render('<p>Intro</p><img src="/a.jpg" alt="A" data-align="right" width="240"><img src="/b.jpg" alt="B"><p>Text beside it.</p>');

        $this->assertStringNotContainsString('content-gallery', $html);
        $this->assertMatchesRegularExpression('#<img[^>]*src="/a.jpg"[^>]*data-align="right"#', $html);
        $this->assertStringContainsString('/b.jpg', $html);
    }
}
