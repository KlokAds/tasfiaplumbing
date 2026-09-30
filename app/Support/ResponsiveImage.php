<?php

namespace App\Support;

use App\Http\Controllers\ImageController;

/**
 * Server-side twin of resources/js/utils/img.js: resized WebP URLs and srcsets for
 * uploaded images, plus the <link rel="preload"> for the page's main (LCP) image so the
 * browser starts downloading it before JavaScript has rendered the page.
 */
class ResponsiveImage
{
    private const ATTR = 'preload_image';

    public static function local(?string $path): ?string
    {
        $p = ltrim((string) $path, '/');
        if ($p === '' || preg_match('#^https?:#i', $p) || !preg_match('/\.(jpe?g|png|webp|gif)$/i', $p)) {
            return null;
        }

        return preg_match('#^(Admin|uploads|images)/#', $p) ? $p : null;
    }

    public static function url(?string $path, int $width): ?string
    {
        $p = self::local($path);
        if (!$p) {
            return $path ? '/' . ltrim($path, '/') : null;
        }
        $w = collect(ImageController::WIDTHS)->first(fn ($x) => $x >= $width) ?? max(ImageController::WIDTHS);

        return "/cache/img/{$w}/{$p}.webp";
    }

    public static function srcset(?string $path, int $max = 1600): ?string
    {
        $p = self::local($path);
        if (!$p) {
            return null;
        }

        return collect(ImageController::WIDTHS)->filter(fn ($w) => $w >= 320 && $w <= $max)
            ->map(fn ($w) => "/cache/img/{$w}/{$p}.webp {$w}w")->join(', ');
    }

    /** Ask the layout to preload this image (call from the controller). */
    public static function preload(?string $path, string $sizes = '100vw', int $max = 1920): void
    {
        if (!$path) {
            return;
        }
        request()->attributes->set(self::ATTR, [
            'href' => self::url($path, 1280),
            'srcset' => self::srcset($path, $max),
            'sizes' => $sizes,
        ]);
    }

    public static function preloadTag(): string
    {
        $p = request()->attributes->get(self::ATTR);
        if (!$p || !$p['href']) {
            return '';
        }

        return '<link rel="preload" as="image" fetchpriority="high" href="' . e($p['href']) . '"'
            . ($p['srcset'] ? ' imagesrcset="' . e($p['srcset']) . '" imagesizes="' . e($p['sizes']) . '"' : '') . '>';
    }
}
