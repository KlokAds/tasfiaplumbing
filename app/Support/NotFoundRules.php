<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Makes the "Broken URLs (404)" list useful:
 *  - scanner noise (bots probing for .env, WordPress, vendor files…) is never logged, so the list
 *    only shows links a real visitor or Google might follow;
 *  - every broken URL gets a suggested page to redirect to (old admin pages, renamed articles…).
 */
class NotFoundRules
{
    /** Paths only bots and attack scanners ask for. Answering 404 is right; listing them is not useful. */
    private const NOISE = [
        '#(^|/)\.(env|git|svn|hg|aws|ssh|htaccess|htpasswd|ds_store|vscode|idea)(/|$|\.)#i',
        '#(^|/)(wp-|wordpress|xmlrpc\.php|wlwmanifest)#i',
        '#(^|/)(vendor|node_modules|storage|bootstrap|config|database|tests?|phpmyadmin|pma|adminer|cgi-bin)(/|$)#i',
        '#(^|/)(composer\.(json|lock)|package(-lock)?\.json|artisan|cron\.php|server\.php|phpinfo\.php|info\.php|shell\.php|eval-stdin\.php|web\.config|\.user\.ini)$#i',
        '#\.(sql|bak|old|orig|swp|zip|tar|gz|rar|7z|log|ini|ya?ml|sh|env|pem|key|asp|aspx|jsp|cgi)$#i',
        '#^/(install|setup|debug|telescope|_ignition|actuator|owa|remote|solr|druid|jenkins|boaform|hnap1)(/|$)#i',
        '#^/\.well-known/(?!acme-challenge|security\.txt)#i',
        '#^/(ai-catalog|ads)\.(json|txt)$#i',
        // Guesses for sign-up pages, crypto gateways and app manifests this site does not have.
        '#^/(register|signup|sign-up|user/register|ipfs|manifest\.json|site\.webmanifest|apple-app-site-association)(/|$)#i',
    ];

    /** Old addresses from the previous website (and common guesses) that have a fixed new home. */
    private const ALIASES = [
        '/forget-password' => '/admin/forgot-password',
        '/forgot-password' => '/admin/forgot-password',
        '/password/reset' => '/admin/forgot-password',
        '/dashboard' => '/admin',
        '/login' => '/admin/login',
        '/admin/dashboard' => '/admin',
        '/home' => '/',
        '/index.php' => '/',
        '/index.html' => '/',
        '/contact-us' => '/contact',
        '/about-us' => '/about',
        '/service' => '/services',
        '/blog' => '/blogs',
        '/project' => '/projects',
        '/review' => '/reviews',
        '/location' => '/locations',
        '/privacy' => '/privacy-policy',
        '/terms' => '/terms-of-service',
    ];

    public static function isNoise(string $path): bool
    {
        $p = parse_url($path, PHP_URL_PATH) ?: $path;
        foreach (self::NOISE as $re) {
            if (preg_match($re, $p)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The page a broken URL most likely meant, or null.
     *
     * @param array<int, string> $pages public paths of the site (from the sitemap)
     */
    public static function suggest(string $path, array $pages): ?string
    {
        $p = rtrim(strtolower(parse_url($path, PHP_URL_PATH) ?: '/'), '/') ?: '/';

        if (isset(self::ALIASES[$p])) {
            return self::ALIASES[$p];
        }

        // A file link with "+" for spaces (e.g. /Admin/Logo/Tasfia+Plumbing.webp): the real file.
        $raw = parse_url($path, PHP_URL_PATH) ?: $path;
        if (str_contains($raw, '+')) {
            $file = str_replace('+', ' ', rawurldecode($raw));
            if (is_file(public_path(ltrim($file, '/')))) {
                return implode('/', array_map('rawurlencode', explode('/', $file)));
            }
        }

        // Pages: the closest address by words, preferring the same section (/blogs/…, /service/…).
        $words = self::words($p);
        if (!$words) {
            return null;
        }
        $section = explode('/', trim($p, '/'))[0] ?? '';
        $best = null;
        $bestScore = 0.0;
        foreach ($pages as $page) {
            $cand = self::words(strtolower($page));
            if (!$cand) {
                continue;
            }
            $score = count(array_intersect($words, $cand)) / count(array_unique([...$words, ...$cand]));
            if ($section !== '' && str_starts_with(trim($page, '/'), $section . '/')) {
                $score += 0.1;
            }
            if ($score > $bestScore) {
                [$best, $bestScore] = [$page, $score];
            }
        }

        return $bestScore >= 0.45 ? $best : null;
    }

    /** Meaningful words of a URL path ("tasfia", "singapore", "service"… say little about the page). */
    private static function words(string $path): array
    {
        $stop = ['tasfia', 'engineering', 'plumbing', 'handyman', 'door', 'repair', 'singapore', 'sg', 'service', 'services', 'blogs', 'blog', 'the', 'and', 'in', 'of', 'a', 'to', 'for', 'www', 'html', 'php'];
        $parts = preg_split('/[^a-z0-9]+/', Str::ascii($path), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_diff($parts, $stop)));
    }
}
