<?php

namespace App\Http\Middleware;

use App\Models\BlogDetail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fallback for hosting without a cron job: at most once a minute, the first web request
 * publishes any scheduled article that is due. With cron running this finds nothing to do.
 */
class PublishScheduledArticles
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (Cache::add('articles:publish-check', 1, 60) && BlogDetail::dueForPublishing()->exists()) {
                BlogDetail::publishDue();
            }
        } catch (\Throwable $e) {
            Log::warning('Scheduled publishing check failed', ['error' => $e->getMessage()]);
        }

        return $next($request);
    }
}
