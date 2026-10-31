<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages the company's billing app runs (enquiries, people and roles, Google connect, the system):
 * they open only in a session signed in from the billing app's "Open" link (Admin\SsoController),
 * for a few hours. Signed in here directly, they do not exist.
 */
class FromBilling
{
    public const KEY = 'from_billing_until';

    public const HOURS = 3;

    public static function start(Request $request): void
    {
        $request->session()->put(self::KEY, now()->getTimestamp() + self::HOURS * 3600);
    }

    public static function active(Request $request): bool
    {
        return (int) $request->session()->get(self::KEY) > now()->getTimestamp();
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(self::active($request), 404);

        return $next($request);
    }
}
