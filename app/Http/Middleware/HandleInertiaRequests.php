<?php

namespace App\Http\Middleware;

use App\Models\ContactContent;
use App\Models\Footer;
use App\Models\ServiceDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $contact = null;
        $footer = null;

        try {
            $contact = ContactContent::first();
            $footer = Footer::first();
        } catch (\Throwable $e) {
            // Fallback gracefully
        }

        // Navigation data — graceful fallback until Category/Location models are added.
        // Cached lightly per request via static so multiple share() calls don't re-query.
        $nav = $this->navigationData();

        return [
            ...parent::share($request),
            'serviceCategories' => $nav['serviceCategories'],
            'topLocations' => $nav['topLocations'],
            'footerTopServices' => $nav['footerTopServices'],
            'hasPrices' => $nav['hasPrices'] ?? false,
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'roles' => method_exists($request->user(), 'getRoleNames') ? $request->user()->getRoleNames() : [],
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'admin' => fn () => ($request->user() && $request->is('admin', 'admin/*')) ? $this->adminData($request) : null,
            'meta' => fn () => $request->is('admin', 'admin/*') ? null : \App\Support\PageMeta::resolve($request),
            // Public pages only: business details come from Settings, so every page shows the same NAP.
            'company' => fn () => $request->is('admin', 'admin/*') ? null : $this->company($contact, $footer),
        ];
    }

    protected function adminData(Request $request): array
    {
        $count = function (string $table, ?callable $scope = null): int {
            try {
                $q = \DB::table($table);
                if ($scope) {
                    $scope($q);
                }
                return $q->count();
            } catch (\Throwable $e) {
                return 0;
            }
        };

        return [
            'counts' => [
                'services' => $count('service_details'),
                'categories' => $count('service_categories'),
                'locations' => $count('locations'),
                'prices' => $count('prices'),
                'articles' => $count('blog_details'),
                'faqs' => $count('faqs'),
                'projects' => $count('project_details'),
                'reviews' => $count('feed_back_contents'),
                'redirects' => $count('redirects'),
                'checklist' => $request->user()->can('settings.view')
                    ? \Illuminate\Support\Facades\Cache::remember('admin.checklist.must', 300, fn () => rescue(fn () => \App\Support\SiteChecklist::score()['must_open'], 0, false))
                    : 0,
                'open_404s' => $count('not_found_logs', fn ($q) => $q->where('is_resolved', false)),
                'unread' => $count('messages', fn ($q) => $q->where('is_read', 0)),
                'review' => $request->user()->can('articles.publish')
                    ? $count('blog_details', fn ($q) => $q->where('status', 'pending')) + $count('article_revisions', fn ($q) => $q->where('status', 'pending'))
                    : 0,
            ],
            'can' => collect(\App\Support\Permissions::all())
                ->mapWithKeys(fn ($p) => [$p => $request->user()->can($p)])
                ->all(),
            'brand' => \App\Models\SiteSetting::get('business.brand_name') ?: config('app.name'),
            'debug' => (bool) config('app.debug'),
            'role' => (function () use ($request) {
                $name = $request->user()->getRoleNames()->first();
                return $name ? (config("admin.roles.{$name}.label") ?? $name) : null;
            })(),
            'user' => [
                'image' => $request->user()->photo(),
                'job_title' => $request->user()->job_title,
            ],
            'notifications' => (function () use ($request) {
                try {
                    return [
                        'unread' => $request->user()->unreadNotifications()->count(),
                        'items' => $request->user()->notifications()->latest()->take(8)->get()
                            ->map(fn ($n) => [
                                'id' => $n->id,
                                'title' => $n->data['title'] ?? '',
                                'body' => $n->data['body'] ?? '',
                                'level' => $n->data['level'] ?? 'info',
                                'read' => (bool) $n->read_at,
                                'at' => $n->created_at?->toIso8601String(),
                            ]),
                    ];
                } catch (\Throwable $e) {
                    return ['unread' => 0, 'items' => []];
                }
            })(),
        ];
    }

    protected function company($contact, $footer): array
    {
        $brand = \App\Models\SiteSetting::get('business.brand_name') ?: config('app.name');
        [$phone, $tel] = self::formatPhone($contact?->phone);

        return [
            'name' => $brand,
            'legal_name' => \App\Models\SiteSetting::get('business.legal_name'),
            'phone' => $phone,
            'tel' => $tel,
            'email' => $contact?->email,
            'address' => $contact?->address,
            'map' => $contact?->map,
            'logo' => $footer?->main_logo ? '/' . ltrim($footer->main_logo, '/') : '/logo.png',
            'footer_logo' => $footer?->f_logo ? '/' . ltrim($footer->f_logo, '/') : null,
            'summary' => $footer?->f_short_desc,
            'copyright' => self::copyright($footer?->c_text, $brand),
            'whatsapp' => \App\Support\Socials::whatsappUrl($contact?->phone),
            'chat' => (bool) \App\Models\SiteSetting::get('tracking.tawk_enabled') && \App\Models\SiteSetting::get('tracking.tawk_id'),
            'hours' => \App\Models\SiteSetting::get('hours.mon_fri'),
            // Admin → Website text: every heading/short text used across pages.
            'texts' => collect(\App\Models\SiteSetting::values())->filter(fn ($v, $k) => str_starts_with($k, 'text.'))
                ->mapWithKeys(fn ($v, $k) => [substr($k, 5) => (string) $v])->all(),
            // Admin → Contact & social; order = footer icon order.
            'socials' => \App\Support\Socials::links(),
        ];
    }

    /**
     * Singapore numbers are shown as "+65 9373 0360" and dialled as "+6593730360",
     * however they were typed in admin ("93730360", "6593730360", "+65 9373-0360" …).
     *
     * @return array{0: ?string, 1: ?string} [display, tel]
     */
    public static function formatPhone(?string $raw): array
    {
        $digits = preg_replace('/\D/', '', (string) $raw);
        if ($digits === '') {
            return [null, null];
        }
        if (strlen($digits) === 8) {
            $digits = '65' . $digits;
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '65')) {
            $local = substr($digits, 2);

            return ['+65 ' . substr($local, 0, 4) . ' ' . substr($local, 4), '+' . $digits];
        }

        return [trim((string) $raw), '+' . $digits];
    }

    /**
     * "© 2016–2026 Tasfia Plumbing Service Singapore All rights reserved."
     * The end year is always the current year. The start year comes from Settings
     * (Year founded) or, failing that, the first year written in the old footer text.
     */
    public static function copyright(?string $legacy, string $brand): string
    {
        $owner = \App\Models\SiteSetting::get('business.legal_name') ?: $brand;
        $start = \App\Models\SiteSetting::get('business.founded_year');
        if (!preg_match('/^(19|20)\d{2}$/', (string) $start)) {
            $start = preg_match('/\b((?:19|20)\d{2})\b/', (string) $legacy, $m) ? $m[1] : null;
        }
        $now = date('Y');
        $years = $start && $start < $now ? "{$start}–{$now}" : $now;

        return '© ' . $years . ' ' . rtrim($owner, '.') . '. All rights reserved.';
    }

    /** Menus: service categories with their services, featured locations, footer links. Cached for 10 minutes. */
    protected function navigationData(): array
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }

        try {
            return $memo = \Illuminate\Support\Facades\Cache::remember('site.navigation', 600, function () {
                $services = ServiceDetail::where('is_active', true)->orderBy('order')->get(['id', 'name', 'slug', 'category_id']);
                $categories = \App\Models\ServiceCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']);

                $groups = $categories->map(fn ($c) => [
                    'name' => $c->name,
                    'href' => $c->publicPath(),
                    'services' => $services->where('category_id', $c->id)->take(8)->map(fn ($s) => ['name' => $s->name, 'href' => $s->publicPath()])->values()->all(),
                ])->filter(fn ($g) => count($g['services']))->values();

                $loose = $services->whereNull('category_id');
                if ($loose->isNotEmpty()) {
                    $groups->push([
                        'name' => $groups->isEmpty() ? 'Our services' : 'More services',
                        'href' => '/services',
                        'services' => $loose->take(30)->map(fn ($s) => ['name' => $s->name, 'href' => $s->publicPath()])->values()->all(),
                    ]);
                }

                $locations = \App\Models\Location::where('is_active', true)->orderByDesc('is_featured')->orderBy('sort_order')->take(12)->get(['name', 'slug'])
                    ->map(fn ($l) => ['name' => $l->name, 'href' => $l->publicPath()])->all();

                return [
                    'serviceCategories' => $groups->all(),
                    'topLocations' => $locations,
                    'footerTopServices' => $services->take(6)->map(fn ($s) => ['name' => $s->name, 'href' => $s->publicPath()])->values()->all(),
                    // Price list links are only shown once there are prices to show.
                    'hasPrices' => \App\Models\Price::where('is_active', true)->exists(),
                ];
            });
        } catch (\Throwable $e) {
            return $memo = ['serviceCategories' => [], 'topLocations' => [], 'footerTopServices' => [], 'hasPrices' => false];
        }
    }
}
