<?php

namespace App\Support;

/**
 * The country of a visitor's IP address, for Country access. The CDN in front of this site does not say
 * which country a visitor is in, so the address is looked up in DB-IP's free "IP to Country Lite" list
 * (CC BY 4.0, https://db-ip.com). The company's billing app on the same server fetches that list each month
 * (storage/app/geoip in ~/apps/billing); this site reads it from there, or from its own storage/app/geoip.
 * The files hold sorted fixed-size records (first address, last address, country), searched by halving.
 */
class GeoIp
{
    private const SIZES = [4 => 10, 16 => 34]; // bytes per record: 2 addresses + 2 letters

    public static function dir(): ?string
    {
        foreach ([config('services.geoip_dir'), dirname(base_path()) . '/billing/storage/app/geoip', storage_path('app/geoip')] as $dir) {
            if ($dir && is_file("{$dir}/ipv4.bin")) {
                return $dir;
            }
        }

        return null;
    }

    /** Two-letter country code, or null when the list is not here or the address is private. */
    public static function country(?string $ip): ?string
    {
        $bin = $ip ? @inet_pton($ip) : false;
        $dir = self::dir();
        if ($bin === false || !$dir || !isset(self::SIZES[strlen($bin)])) {
            return null;
        }
        $len = strlen($bin);
        $file = $dir . ($len === 4 ? '/ipv4.bin' : '/ipv6.bin');
        $f = @fopen($file, 'rb');
        if (!$f) {
            return null;
        }
        $size = self::SIZES[$len];
        $lo = 0;
        $hi = intdiv((int) filesize($file), $size) - 1;
        $found = null;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            fseek($f, $mid * $size);
            $rec = fread($f, $size);
            if (strcmp(substr($rec, 0, $len), $bin) <= 0) {
                $found = $rec;
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }
        fclose($f);
        if ($found === null || strcmp($bin, substr($found, $len, $len)) > 0) {
            return null;
        }
        $code = substr($found, 2 * $len, 2);

        return $code === 'ZZ' ? null : $code;
    }
}
