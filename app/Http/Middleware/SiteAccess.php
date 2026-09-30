<?php

namespace App\Http\Middleware;

use App\Support\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance mode, temporary debug mode and country access, all switched from admin.
 * The admin area, logins and signed-in team members are never blocked.
 */
class SiteAccess
{
    private const ALWAYS_OPEN = ['admin', 'admin/*', 'login', 'up', 'robots.txt', 'cache/img/*', 'mail/*', 'setup'];

    public function handle(Request $request, Closure $next): Response
    {
        $signedIn = $request->user() !== null;

        // Debug output only for the signed-in team, and only until the timer runs out.
        if ($signedIn && SystemSettings::debugUntil()) {
            config(['app.debug' => true]);
        }

        if ($signedIn || $request->is(...self::ALWAYS_OPEN)) {
            return $next($request);
        }

        if (SystemSettings::maintenanceOn()) {
            // 503 + Retry-After tells Google "come back later" instead of dropping pages.
            return response()->view('errors.site-status', ['mode' => 'maintenance'] + SystemSettings::pageDetails(), 503, ['Retry-After' => '3600']);
        }

        if (SystemSettings::countryBlocked($request)) {
            return response()->view('errors.site-status', ['mode' => 'blocked'] + SystemSettings::pageDetails(), 403);
        }

        return $next($request);
    }
}
