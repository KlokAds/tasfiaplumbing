<?php

namespace App\Http\Controllers;

/**
 * Responsive WebP copies of uploaded images: /cache/img/{width}/{path}.webp
 *
 * The first request builds the file inside public/, so every later request is served
 * directly by the web server as a static file (no PHP). Only a fixed set of widths and
 * only images inside the upload folders can be requested.
 */
class ImageController extends Controller
{
    public const WIDTHS = [96, 160, 320, 480, 640, 800, 1024, 1280, 1600, 1920];
    private const ROOTS = ['Admin/', 'uploads/', 'images/'];

    public function show(int $width, string $path)
    {
        abort_unless(in_array($width, self::WIDTHS, true), 404);
        $path = ltrim(str_replace('\\', '/', $path), '/');
        abort_if(str_contains($path, '..') || !str_ends_with(strtolower($path), '.webp'), 404);

        $sourceRel = substr($path, 0, -5); // strip ".webp"
        abort_unless(collect(self::ROOTS)->contains(fn ($r) => str_starts_with($sourceRel, $r)), 404);
        abort_unless(preg_match('/\.(jpe?g|png|webp|gif)$/i', $sourceRel), 404);

        $source = public_path($sourceRel);
        abort_unless(is_file($source) && str_starts_with(realpath($source), realpath(public_path())), 404);

        $target = public_path("cache/img/{$width}/{$path}");
        if (!is_file($target)) {
            if (!$this->build($source, $target, $width)) {
                return response()->file($source, ['Cache-Control' => 'public, max-age=86400']);
            }
        }

        return response()->file($target, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /** Remove every resized copy of an image (after it is deleted or moved), so an old copy is never served again. */
    public static function purge(string $path): void
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return;
        }
        foreach (self::WIDTHS as $w) {
            $file = public_path("cache/img/{$w}/{$path}.webp");
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function build(string $source, string $target, int $width): bool
    {
        $info = @getimagesize($source);
        if (!$info || !function_exists('imagewebp')) {
            return false;
        }
        [$w, $h] = $info;
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            IMAGETYPE_GIF => @imagecreatefromgif($source),
            default => false,
        };
        if (!$img) {
            return false;
        }

        // Respect phone photo orientation.
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = @exif_read_data($source)['Orientation'] ?? 1;
            $img = match ((int) $o) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => $img,
            };
            [$w, $h] = [imagesx($img), imagesy($img)];
        }

        $newW = min($width, $w);
        $newH = (int) round($h * $newW / $w);
        $out = imagecreatetruecolor($newW, $newH);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);

        // Two first requests for images in a new folder can arrive together: a folder that
        // appeared meanwhile is fine, and the file is written under a temporary name first,
        // so nobody is ever served a half-written image.
        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            imagedestroy($img);
            imagedestroy($out);

            return false;
        }
        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $ok = imagewebp($out, $tmp, 78);
        imagedestroy($img);
        imagedestroy($out);
        if (!$ok) {
            @unlink($tmp);

            return false;
        }
        if (!@rename($tmp, $target)) {
            // Another request finished the same file first (Windows will not replace it).
            @unlink($tmp);

            return is_file($target);
        }

        return true;
    }
}
