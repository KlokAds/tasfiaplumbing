<?php

namespace App\Support;

/**
 * Email apps (Outlook, many phone mail apps) cannot show WebP and choke on spaces in image URLs.
 * Emails therefore use a small PNG copy of the site logo with a clean file name, made once per logo change.
 */
class EmailLogo
{
    public const SIZE = 96;

    private const FALLBACK = '/apple-touch-icon.png';

    public static function url(?string $logo): string
    {
        if (!$logo || str_starts_with($logo, 'http')) {
            return $logo ?: url(self::FALLBACK);
        }

        $source = public_path(ltrim(rawurldecode($logo), '/'));
        if (!is_file($source)) {
            return url(self::FALLBACK);
        }

        $name = 'logo-' . substr(md5($logo . '|' . filemtime($source) . '|' . filesize($source)), 0, 12) . '.png';
        $target = public_path('cache/email/' . $name);

        if (!is_file($target) && !self::make($source, $target)) {
            return url(self::FALLBACK);
        }

        return url('/cache/email/' . $name);
    }

    private static function make(string $source, string $target): bool
    {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }

        $img = @imagecreatefromstring((string) file_get_contents($source));
        if (!$img) {
            return false;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, self::SIZE / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        if (!is_dir(dirname($target))) {
            @mkdir(dirname($target), 0755, true);
        }
        $tmp = $target . '.' . uniqid() . '.tmp';
        $ok = imagepng($out, $tmp, 9) && @rename($tmp, $target);
        @unlink($tmp);

        return $ok;
    }
}
