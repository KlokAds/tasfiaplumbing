<?php

namespace Tests\Feature;

use App\Support\EmailLogo;
use Tests\TestCase;

class EmailLogoTest extends TestCase
{
    public function test_a_webp_logo_with_spaces_becomes_a_small_png_with_a_clean_url(): void
    {
        $dir = public_path('test-logo dir');
        @mkdir($dir);
        $img = imagecreatetruecolor(300, 200);
        imagewebp($img, "{$dir}/My Logo.webp");

        $url = EmailLogo::url('/test-logo dir/My Logo.webp');

        $this->assertMatchesRegularExpression('#/cache/email/logo-[a-f0-9]{12}\.png$#', $url);
        $file = public_path(parse_url($url, PHP_URL_PATH));
        [$w, $h, $type] = getimagesize($file);
        $this->assertSame(IMAGETYPE_PNG, $type);
        $this->assertSame([96, 64], [$w, $h]);

        @unlink($file);
        @unlink("{$dir}/My Logo.webp");
        @rmdir($dir);
    }

    public function test_a_missing_logo_falls_back_to_the_png_icon(): void
    {
        $this->assertStringEndsWith('/apple-touch-icon.png', EmailLogo::url('/nope/missing.webp'));
    }
}
