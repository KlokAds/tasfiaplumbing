<?php

namespace App\Http\Middleware;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before routing so old URLs from the previous site (e.g. /page.php?id=5)
 * are redirected even though no route matches them. Unmatched 404s are logged
 * for the Redirect manager.
 */
class HandleRedirects
{
    private const SKIP_PREFIXES = ['admin', 'api', 'build', 'storage', 'up'];
    private const SKIP_EXTENSIONS = ['js', 'css', 'map', 'ico', 'woff', 'woff2', 'ttf'];

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethodSafe() || $this->skip($request)) {
            return $next($request);
        }

        $path = '/' . trim($request->getPathInfo(), '/');
        $query = $request->getQueryString();

        try {
            $map = Redirect::activeMap();
            $hit = ($query ? ($map[$path . '?' . $query] ?? null) : null) ?? $map[$path] ?? null;
        } catch (\Throwable $e) {
            $hit = null;
        }

        if ($hit) {
            [$id, $to, $code] = $hit;
            try {
                Redirect::whereKey($id)->update(['hit_count' => DB::raw('hit_count + 1'), 'last_hit_at' => now()]);
            } catch (\Throwable $e) {
                // Counting hits must never break the redirect itself.
            }

            if ($code === 410 || !$to) {
                abort(410);
            }

            return redirect()->to($to, $code);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 404) {
            try {
                NotFoundLog::record($query ? $path . '?' . $query : $path, $request->headers->get('referer'));
            } catch (\Throwable $e) {
                // Logging is best-effort.
            }
        }

        return $response;
    }

    private function skip(Request $request): bool
    {
        $path = trim($request->getPathInfo(), '/');
        foreach (self::SKIP_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $ext !== '' && in_array($ext, self::SKIP_EXTENSIONS, true);
    }
}
