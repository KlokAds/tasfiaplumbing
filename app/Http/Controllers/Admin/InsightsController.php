<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoogleReview;
use App\Models\IndexStatus;
use App\Models\SiteSetting;
use App\Support\Google\Analytics;
use App\Support\Google\BusinessProfile;
use App\Support\Google\GoogleApi;
use App\Support\Google\GoogleException;
use App\Support\Google\SearchConsole;
use App\Support\Sitemap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class InsightsController extends Controller
{
    /** Search Console + Analytics reports. */
    public function reports(Request $request)
    {
        $fresh = $request->boolean('refresh');
        $load = function (callable $fn) {
            try {
                return ['ok' => true, 'data' => $fn()];
            } catch (GoogleException $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            } catch (\Throwable $e) {
                Log::warning('Insights report failed', ['error' => $e->getMessage()]);

                return ['ok' => false, 'error' => 'Could not load the report: ' . Str::limit($e->getMessage(), 160)];
            }
        };

        return Inertia::render('Admin/Insights/Reports', [
            'connected' => GoogleApi::connected(),
            'gsc' => GoogleApi::connected() && SearchConsole::property() ? $load(fn () => SearchConsole::report($fresh)) : null,
            'ga4' => GoogleApi::connected() && Analytics::property() ? $load(fn () => Analytics::report($fresh)) : null,
            'gscProperty' => SearchConsole::property(),
            'ga4Property' => SiteSetting::get('google.ga4_property'),
            'site' => url('/'),
        ]);
    }

    /** Index status of every sitemap URL. */
    public function indexing(Request $request)
    {
        $urls = Sitemap::urls()->map(fn ($u) => ['url' => url($u['loc'])] + $u);
        $statuses = IndexStatus::whereIn('url', $urls->pluck('url'))->get()->keyBy('url');

        $rows = $urls->map(function ($u) use ($statuses) {
            $s = $statuses->get($u['url']);

            return [
                'url' => $u['url'],
                'path' => $u['loc'],
                'type' => $u['type'],
                'title' => $u['title'],
                'state' => !$s ? 'unchecked' : ($s->error ? 'error' : ($s->isIndexed() ? 'indexed' : 'not_indexed')),
                'coverage' => $s?->coverage,
                'error' => $s?->error,
                'last_crawl' => $s?->last_crawl?->toIso8601String(),
                'checked_at' => $s?->checked_at?->toIso8601String(),
                'google_canonical' => $s?->google_canonical && rtrim($s->google_canonical, '/') !== rtrim($u['url'], '/') ? $s->google_canonical : null,
            ];
        })->values();

        return Inertia::render('Admin/Insights/Indexing', [
            'rows' => $rows,
            'summary' => $rows->countBy('state'),
            'ready' => GoogleApi::connected() && SearchConsole::property(),
        ]);
    }

    public function inspect(Request $request)
    {
        $url = $request->validate(['url' => 'required|url|max:500'])['url'];
        if (!str_starts_with($url, url('/'))) {
            return back()->with('error', 'Only pages of this website can be checked.');
        }
        try {
            $s = SearchConsole::inspect($url);
        } catch (GoogleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($s->error ? 'error' : 'success', $s->error ?: ($s->isIndexed() ? 'Indexed: ' : 'Not indexed: ') . ($s->coverage ?: 'no details'));
    }

    public function inspectBatch()
    {
        try {
            $n = SearchConsole::inspectBatch(40);
        } catch (GoogleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Checked {$n} pages with Google. The rest are checked automatically every day.");
    }

    /** Google connection settings. */
    public function google()
    {
        $connected = GoogleApi::connected();
        $lists = ['sites' => [], 'properties' => [], 'locations' => []];
        $errors = [];
        if ($connected) {
            foreach (['sites' => fn () => SearchConsole::sites(), 'properties' => fn () => Analytics::properties(), 'locations' => fn () => BusinessProfile::locations()] as $key => $fn) {
                try {
                    $lists[$key] = cache()->remember("google.list.{$key}", now()->addMinutes(10), $fn);
                } catch (\Throwable $e) {
                    $errors[$key] = $e->getMessage();
                }
            }
        }

        return Inertia::render('Admin/Insights/Google', [
            'client' => ['id' => GoogleApi::clientId(), 'has_secret' => (bool) GoogleApi::clientSecret()],
            'redirectUri' => GoogleApi::redirectUri(),
            'origin' => url('/'),
            'connected' => $connected,
            'email' => SiteSetting::get('google.connected_email'),
            'lastError' => SiteSetting::get('google.last_error'),
            'selected' => [
                'gsc' => SiteSetting::get('google.gsc_property'),
                'ga4' => SiteSetting::get('google.ga4_property'),
                'gbp' => SiteSetting::get('google.gbp_location'),
            ],
            'lists' => $lists,
            'listErrors' => $errors,
            'reviews' => [
                'count' => rescue(fn () => GoogleReview::count(), 0, false),
                'rating' => SiteSetting::get('google.gbp_rating'),
                'total' => SiteSetting::get('google.gbp_total'),
                'synced_at' => SiteSetting::get('google.gbp_synced_at'),
            ],
        ]);
    }

    public function saveClient(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:200', 'regex:/\.apps\.googleusercontent\.com$/'],
            'client_secret' => 'nullable|string|max:200',
        ], ['client_id.regex' => 'The Client ID ends with .apps.googleusercontent.com']);

        $values = ['google.client_id' => trim($data['client_id'])];
        if (filled($data['client_secret'] ?? null)) {
            $values['google.client_secret'] = Crypt::encryptString(trim($data['client_secret']));
        }
        SiteSetting::putMany($values);

        return back()->with('success', 'Saved. Now click "Connect Google".');
    }

    public function connect(Request $request)
    {
        if (!GoogleApi::hasClient()) {
            return back()->with('error', 'Add the Client ID and Client secret first.');
        }
        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return Inertia::location(GoogleApi::authUrl($state));
    }

    public function callback(Request $request)
    {
        $expected = $request->session()->pull('google_oauth_state');
        if (!$expected || !hash_equals($expected, (string) $request->query('state'))) {
            return redirect('/admin/insights/google')->with('error', 'The Google sign-in expired. Please try again.');
        }
        if ($request->query('error')) {
            return redirect('/admin/insights/google')->with('error', $request->query('error') === 'access_denied'
                ? 'Google access was not granted. Tick every permission on the Google screen.'
                : 'Google said: ' . $request->query('error'));
        }

        try {
            GoogleApi::exchange((string) $request->query('code'));
        } catch (\Throwable $e) {
            return redirect('/admin/insights/google')->with('error', $e->getMessage());
        }
        cache()->forget('google.list.sites');
        cache()->forget('google.list.properties');
        cache()->forget('google.list.locations');

        return redirect('/admin/insights/google')->with('success', 'Google connected. Now choose your Search Console property, Analytics property and business location.');
    }

    public function disconnect()
    {
        GoogleApi::disconnect();

        return back()->with('success', 'Google disconnected. Reviews already synced stay on the website.');
    }

    public function saveProperties(Request $request)
    {
        $data = $request->validate([
            'gsc' => 'nullable|string|max:300',
            'ga4' => ['nullable', 'string', 'regex:/^properties\/\d+$/'],
            'gbp' => ['nullable', 'string', 'regex:/^accounts\/[^\/]+\/locations\/[^\/]+$/'],
        ]);

        $values = ['google.gsc_property' => $data['gsc'] ?? '', 'google.ga4_property' => $data['ga4'] ?? '', 'google.gbp_location' => $data['gbp'] ?? ''];
        $location = collect(cache('google.list.locations', []))->firstWhere('name', $data['gbp'] ?? null);
        if ($location) {
            $values += [
                'google.gbp_location_name' => $location['title'],
                'google.gbp_place_id' => $location['place_id'] ?? '',
                'google.gbp_maps_uri' => $location['maps_uri'] ?? '',
                'google.gbp_review_uri' => $location['review_uri'] ?? '',
            ];
        }
        SiteSetting::putMany($values);
        GoogleApi::flushReports();

        $message = 'Saved.';
        if (!empty($data['gbp'])) {
            try {
                $n = BusinessProfile::sync();
                $message .= " {$n} Google reviews imported.";
            } catch (GoogleException $e) {
                $message .= ' Reviews could not be imported yet: ' . $e->getMessage();
            }
        }

        return back()->with('success', $message);
    }

    public function syncReviews()
    {
        try {
            $n = BusinessProfile::sync();
        } catch (GoogleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$n} Google reviews synced.");
    }
}
