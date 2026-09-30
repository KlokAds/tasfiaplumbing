<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\GoogleReviews;
use App\Support\SeoAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CacheController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/System/Cache', [
            'status' => [
                'environment' => app()->environment(),
                'config_cached' => app()->configurationIsCached(),
                'routes_cached' => app()->routesAreCached(),
                'views' => count(File::glob(config('view.compiled') . '/*.php') ?: []),
                'cache_store' => config('cache.default'),
            ],
        ]);
    }

    /**
     * "data"  – website data only: settings, SEO scores, Google reviews, page SEO.
     *           Safe any time; use when a change does not show on the site.
     * "all"   – also compiled views, config and routes (after an update or .env change).
     *           In production the optimized files are rebuilt straight away.
     */
    public function clear(Request $request)
    {
        $scope = $request->validate(['scope' => 'required|in:data,all'])['scope'];

        try {
            if ($scope === 'data') {
                Cache::flush();
                SeoAudit::flush();
                GoogleReviews::flush();
                $message = 'Website data cache cleared. Settings, SEO scores and Google reviews are rebuilt on the next visit.';
            } else {
                Artisan::call('optimize:clear');
                if (app()->isProduction()) {
                    Artisan::call('optimize');
                }
                $message = app()->isProduction()
                    ? 'All caches cleared and the site re-optimized.'
                    : 'All caches cleared (config, routes, views and data).';
            }
        } catch (\Throwable $e) {
            Log::error('Cache clear failed', ['scope' => $scope, 'error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Could not clear the cache: ' . $e->getMessage());
        }

        Log::info('Cache cleared from admin', ['scope' => $scope, 'user' => $request->user()->id]);

        return redirect()->back()->with('success', $message);
    }
}
