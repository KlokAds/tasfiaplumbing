<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The content hub (the company's billing app) sends drafts here with a secret token. Only the token's
 * hash is stored on this site (setting content_api.token_hash); without one the API does not exist.
 */
class ContentApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $hash = (string) SiteSetting::stored('content_api.token_hash');
        $token = (string) $request->bearerToken();

        if ($hash === '' || $token === '' || !hash_equals($hash, hash('sha256', $token))) {
            abort(404);
        }

        return $next($request);
    }
}
