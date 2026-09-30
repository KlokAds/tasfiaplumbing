<?php

namespace Tests\Feature;

use App\Support\ResponsiveImage;
use Tests\TestCase;

/** File names with spaces must never reach HTML, srcset or JSON-LD unencoded (bots would split them). */
class ImageUrlTest extends TestCase
{
    public function test_paths_with_spaces_are_encoded_once(): void
    {
        $this->assertSame('Admin/Blog/Untitled%20design%20-%202024.jpg', ResponsiveImage::encodePath('Admin/Blog/Untitled design - 2024.jpg'));
        $this->assertSame('Admin/Blog/Untitled%20design.jpg', ResponsiveImage::encodePath('Admin/Blog/Untitled%20design.jpg'));
        $this->assertSame('https://site.test/Service%20Image/S10.jpg', ResponsiveImage::publicUrl('/Service Image/S10.jpg', 'https://site.test/'));
        $this->assertSame('https://cdn.test/a b.jpg', ResponsiveImage::publicUrl('https://cdn.test/a b.jpg', 'https://site.test'));
    }

    public function test_srcset_entries_have_no_raw_spaces(): void
    {
        $srcset = ResponsiveImage::srcset('Admin/Blog/Details/Water heater replace.jpg', 640);

        $this->assertStringContainsString('/cache/img/320/Admin/Blog/Details/Water%20heater%20replace.jpg.webp 320w', $srcset);
        foreach (explode(', ', $srcset) as $entry) {
            $this->assertCount(2, explode(' ', $entry), $entry);
        }
    }
}
