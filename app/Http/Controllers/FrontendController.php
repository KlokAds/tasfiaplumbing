<?php

namespace App\Http\Controllers;

use App\Models\AboutContent;
use App\Models\BlogDetail;
use App\Models\BreadCumb;
use App\Models\ContactContent;
use App\Models\HomeCounter;
use App\Models\HomeHero;
use App\Models\HomeSkill;
use App\Models\HomeStatic;
use App\Models\Location;
use App\Models\Message;
use App\Models\Partner;
use App\Models\Price;
use App\Models\ProjectDetail;
use App\Models\ServiceCategory;
use App\Models\ServiceDetail;
use App\Models\SiteSetting;
use App\Support\ContentHtml;
use App\Support\PageSchema;
use App\Support\ResponsiveImage;
use App\Support\ReviewFeed;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class FrontendController extends Controller
{
    private const CARD = ['id', 'name', 'slug', 'image', 'short_summary', 'desc', 'category_id', 'response_time', 'warranty'];

    public function index()
    {
        $hero = HomeHero::orderBy('id')->first();
        ResponsiveImage::preload($hero?->img);
        $services = $this->liveServices()->with(['prices' => fn ($q) => $q->where('is_active', true)])->take(6)->get(self::CARD);

        return Inertia::render('Frontend/Home', [
            'hero' => [
                'title' => $hero?->title,
                'subtitle' => $hero?->subtitle ?: $hero?->short_desc,
                'btn_name' => $hero?->btn_name,
                'btn_link' => $hero?->btn_link,
                'image' => $hero?->img ? '/' . ltrim($hero->img, '/') : null,
                'eyebrow' => SiteSetting::get('home.eyebrow'),
                'badges' => array_values(array_filter(preg_split('/\R/', (string) SiteSetting::get('home.badges', '')))),
                'show_quote_form' => (bool) SiteSetting::get('home.show_quote_form', '1'),
            ],
            'homeStatic' => HomeStatic::first(),
            'services' => $this->withFromPrice($services),
            'allServicesCount' => $this->liveServices()->count(),
            // Every live service for the quote form's "What do you need?" list (Admin → Services).
            'serviceOptions' => $this->liveServices()->orderBy('name')->get(['id', 'name']),
            'categories' => ServiceCategory::where('is_active', true)->withCount(['services' => fn ($q) => $q->where('is_active', true)])->orderBy('sort_order')->get(['id', 'name', 'slug', 'intro']),
            'projects' => ProjectDetail::visible()->with('service:id,name,slug')->orderByDesc('completed_on')->latest()->take(6)->get(),
            'counters' => HomeCounter::all(['id', 'c_count', 'c_title', 'c_subtitle']),
            'reviews' => ReviewFeed::build(3),
            'latestBlogs' => $this->homeGuides(),
            'partners' => Partner::all(['id', 'image']),
        ]);
    }

    /**
     * Four recent guides for the homepage, preferring plumbing topics so the
     * homepage stays on-brand; topped up with the latest articles if needed.
     */
    private function homeGuides(): Collection
    {
        $cols = ['id', 'name', 'slug', 'image', 'excerpt', 'published_at'];
        $topics = ['tap', 'toilet', 'pipe', 'leak', 'sink', 'basin', 'water heater', 'shower', 'drain', 'bidet', 'faucet', 'flush', 'plumb'];
        $guides = BlogDetail::published()
            ->where(fn ($q) => collect($topics)->each(fn ($t) => $q->orWhere('name', 'like', "%{$t}%")))
            ->latest('published_at')->take(4)->get($cols);

        if ($guides->count() < 4) {
            $guides = $guides->concat(BlogDetail::published()->whereNotIn('id', $guides->pluck('id'))
                ->latest('published_at')->take(4 - $guides->count())->get($cols));
        }

        return $guides->values();
    }

    public function about()
    {
        PageSchema::set(PageSchema::breadcrumbs([['About us', '/about']]));

        $about = AboutContent::first();
        if ($about) {
            $about->short_desc = ContentHtml::render($about->short_desc, $about->title ?? '');
        }

        // Real job photos only: the old About images are SVG illustrations.
        $isPhoto = fn ($p) => filled($p) && !preg_match('/\.svg$/i', $p);
        $photos = collect([$about?->img_one, $about?->img_two])->filter($isPhoto)
            ->merge(ServiceDetail::where('is_active', true)->orderBy('order')->pluck('image')->filter($isPhoto))
            ->map(fn ($p) => '/' . ltrim($p, '/'))->unique()->take(3)->values();

        // Banner: the photo set in Admin → About page → Page banner; a job photo when it is empty or an old illustration.
        $banner = $isPhoto($about?->a_bread_img) ? '/' . ltrim($about->a_bread_img, '/') : ($photos[0] ?? null);

        return Inertia::render('Frontend/About', [
            'aboutContent' => $about,
            'photos' => $photos,
            'banner' => $banner,
            'facts' => [
                'founded' => SiteSetting::get('business.founded_year'),
                'services' => $this->liveServices()->count(),
                'locations' => Location::where('is_active', true)->count(),
            ],
            'reviews' => ReviewFeed::build(3),
            'counters' => HomeCounter::all(['id', 'c_count', 'c_title', 'c_subtitle']),
            'skills' => HomeSkill::all(),
            'partners' => Partner::all(['id', 'image']),
            'team' => \App\Models\User::visible()->where('is_active', true)->whereNotNull('job_title')->whereNotNull('bio')->get(['id', 'name', 'job_title', 'bio', 'image']),
        ]);
    }

    public function services()
    {
        $categories = ServiceCategory::where('is_active', true)->orderBy('sort_order')
            ->with(['services' => fn ($q) => $q->where('is_active', true)->with(['prices' => fn ($p) => $p->where('is_active', true)])->select(self::CARD)])
            ->get(['id', 'name', 'slug', 'intro', 'image']);
        $uncategorised = $this->liveServices()->whereNull('category_id')->with(['prices' => fn ($p) => $p->where('is_active', true)])->get(self::CARD);

        $categories->each(fn ($c) => $c->setRelation('services', $this->withFromPrice($c->services)));
        PageSchema::set(
            PageSchema::breadcrumbs([['Services', '/services']]),
            PageSchema::itemList('Services', $this->liveServices()->get(['id', 'name', 'slug'])),
        );

        return Inertia::render('Frontend/Services/Index', [
            'categories' => $categories->filter(fn ($c) => $c->services->isNotEmpty())->values(),
            'uncategorised' => $this->withFromPrice($uncategorised),
            'breadcrumb' => BreadCumb::first(),
        ]);
    }

    public function serviceCategory(string $category)
    {
        $cat = ServiceCategory::where('slug', $category)->where('is_active', true)->firstOrFail();
        $services = $this->liveServices()->where('category_id', $cat->id)->with(['prices' => fn ($p) => $p->where('is_active', true)])->get(self::CARD);

        PageSchema::set(
            PageSchema::breadcrumbs([['Services', '/services'], [$cat->name, $cat->publicPath()]]),
            PageSchema::category($cat, $services),
        );

        $cat->description = ContentHtml::render($cat->description, $cat->name);

        return Inertia::render('Frontend/Services/Category', [
            'category' => $cat,
            'services' => $this->withFromPrice($services),
            'articles' => BlogDetail::published()->whereIn('primary_service_id', $services->pluck('id'))->latest('published_at')->take(3)->get(['id', 'name', 'slug', 'image', 'excerpt', 'published_at']),
        ]);
    }

    public function serviceDetail($slug)
    {
        $service = ServiceDetail::where('is_active', true)->where('slug', $slug)
            ->with(['category:id,name,slug', 'prices' => fn ($q) => $q->where('is_active', true), 'faqs' => fn ($q) => $q->where('is_active', true)])
            ->firstOrFail();

        $related = $this->liveServices()->where('id', '!=', $service->id)
            ->when($service->category_id, fn ($q) => $q->orderByRaw('category_id = ? desc', [$service->category_id]))
            ->take(4)->get(self::CARD);
        $related = $this->withFromPrice($related);

        PageSchema::set(
            PageSchema::breadcrumbs(array_values(array_filter([
                ['Services', '/services'],
                $service->category ? [$service->category->name, $service->category->publicPath()] : null,
                [$service->name, $service->publicPath()],
            ]))),
            PageSchema::service($service),
            PageSchema::faq($service->faqs),
        );

        $service->desc = ContentHtml::render($service->desc, $service->name);
        ResponsiveImage::preload($service->bef_img && $service->aft_img ? $service->aft_img : $service->image, '(min-width: 1024px) 760px, 100vw', 1600);

        return Inertia::render('Frontend/Services/Show', [
            'service' => $service,
            'relatedServices' => $related,
            'projects' => ProjectDetail::visible()->where('service_id', $service->id)->orderByDesc('completed_on')->latest()->take(6)->get(),
            'articles' => BlogDetail::published()->where('primary_service_id', $service->id)->latest('published_at')->take(4)->get(['id', 'name', 'slug', 'image', 'excerpt', 'published_at']),
            'locations' => $service->locations()->where('is_active', true)->orderBy('sort_order')->get(['locations.id', 'name', 'slug']),
            'reviews' => ReviewFeed::build(3),
        ]);
    }

    public function locations()
    {
        $locations = Location::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'region', 'intro', 'image', 'property_types']);
        PageSchema::set(PageSchema::breadcrumbs([['Areas we serve', '/locations']]), PageSchema::itemList('Areas we serve', $locations));

        return Inertia::render('Frontend/Locations/Index', ['locations' => $locations]);
    }

    public function locationDetail($slug)
    {
        $location = Location::where('slug', $slug)->where('is_active', true)
            ->with(['faqs' => fn ($q) => $q->where('is_active', true)])->firstOrFail();
        $services = $location->services()->where('is_active', true)->with(['prices' => fn ($p) => $p->where('is_active', true)])->get(array_map(fn ($c) => "service_details.$c", self::CARD));

        PageSchema::set(
            PageSchema::breadcrumbs([['Areas we serve', '/locations'], [$location->name, $location->publicPath()]]),
            PageSchema::location($location),
            PageSchema::faq($location->faqs),
        );

        $location->description = ContentHtml::render($location->description, $location->name);

        return Inertia::render('Frontend/Locations/Show', [
            'location' => $location,
            'services' => $this->withFromPrice($services),
            'projects' => ProjectDetail::visible()->where('location_id', $location->id)->latest()->take(6)->get(),
            'nearby' => Location::where('is_active', true)->where('id', '!=', $location->id)
                ->when($location->region, fn ($q) => $q->orderByRaw('region = ? desc', [$location->region]))
                ->take(6)->get(['id', 'name', 'slug']),
        ]);
    }

    public function pricing()
    {
        $services = $this->liveServices()
            ->with(['category:id,name,slug', 'prices' => fn ($q) => $q->where('is_active', true)])
            ->whereHas('prices', fn ($q) => $q->where('is_active', true))
            ->get(['id', 'name', 'slug', 'category_id']);
        $lastReviewed = Price::where('is_active', true)->max('last_reviewed_at');

        PageSchema::set(PageSchema::breadcrumbs([['Price list', '/pricing']]));

        return Inertia::render('Frontend/Pricing', [
            'groups' => $services->groupBy(fn ($s) => $s->category?->name ?? 'Other services')
                ->map(fn ($list, $name) => ['name' => $name, 'services' => $list->values()])->values(),
            'lastReviewed' => $lastReviewed,
        ]);
    }

    public function projects(Request $request)
    {
        $query = ProjectDetail::visible()->with(['service:id,name,slug', 'location:id,name,slug'])->orderByDesc('completed_on')->latest();
        if ($request->filled('service')) {
            $query->whereHas('service', fn ($q) => $q->where('slug', $request->input('service')));
        }
        if ($request->filled('q')) {
            $term = '%' . trim((string) $request->input('q')) . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('area', 'like', $term)->orWhere('summary', 'like', $term));
        }
        PageSchema::set(PageSchema::breadcrumbs([['Projects', '/projects']]));

        return Inertia::render('Frontend/Projects/Index', [
            'projects' => $query->paginate(12)->withQueryString(),
            'services' => $this->liveServices()->whereIn('id', ProjectDetail::visible()->whereNotNull('service_id')->distinct()->pluck('service_id'))->get(['id', 'name', 'slug']),
            'filters' => $request->only(['service', 'q']),
            'breadcrumb' => BreadCumb::first(),
        ]);
    }

    public function blogs(Request $request)
    {
        $query = BlogDetail::published()->with('primaryService:id,name,slug')->latest('published_at');

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            // Grouped, so the search never escapes the "published only" filter.
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('excerpt', 'like', $term));
        }
        if ($request->filled('service')) {
            $query->whereHas('primaryService', fn ($q) => $q->where('slug', $request->input('service')));
        }
        PageSchema::set(PageSchema::breadcrumbs([['Articles', '/blogs']]));

        return Inertia::render('Frontend/Blogs/Index', [
            'blogs' => $query->paginate(12, ['id', 'name', 'slug', 'image', 'excerpt', 'published_at', 'primary_service_id'])->withQueryString(),
            'filters' => $request->only(['search', 'service']),
            'services' => $this->liveServices()->whereHas('articles', fn ($q) => $q->published())->get(['id', 'name', 'slug']),
            'breadcrumb' => BreadCumb::first(),
        ]);
    }

    public function blogDetail($slug)
    {
        $blog = BlogDetail::published()->where('slug', $slug)
            ->with(['author:id,name,job_title,bio,image,social_url', 'primaryService:id,name,slug,short_summary,image', 'faqs' => fn ($q) => $q->where('is_active', true)])
            ->firstOrFail();

        $related = BlogDetail::published()->where('id', '!=', $blog->id)
            ->when($blog->primary_service_id, fn ($q) => $q->orderByRaw('primary_service_id = ? desc', [$blog->primary_service_id]))
            ->latest('published_at')->take(3)->get(['id', 'name', 'slug', 'image', 'excerpt', 'published_at']);

        PageSchema::set(
            PageSchema::breadcrumbs([['Articles', '/blogs'], [$blog->name, $blog->publicPath()]]),
            PageSchema::article($blog),
            PageSchema::faq($blog->faqs),
        );

        $blog->makeHidden(['review_note', 'reviewed_by', 'submitted_at', 'status']);
        $blog->desc = ContentHtml::render($blog->desc, $blog->name);
        ResponsiveImage::preload($blog->image, '(min-width: 1024px) 760px, 100vw', 1600);

        return Inertia::render('Frontend/Blogs/Show', [
            'blog' => $blog,
            // A generic "Admin" byline hurts trust; show the team name until a real author is set.
            'author' => $blog->author && !in_array(strtolower(trim($blog->author->name)), ['admin', 'administrator', 'super admin'], true)
                ? ['image' => $blog->author->photo()] + $blog->author->only(['name', 'job_title', 'bio', 'social_url'])
                : [
                    'name' => (!$blog->auth_name || in_array(strtolower(trim($blog->auth_name)), ['admin', 'administrator'], true))
                        ? (SiteSetting::get('business.brand_name') ?: config('app.name')) . ' team'
                        : $blog->auth_name,
                    'job_title' => 'Plumbing specialists, Singapore',
                ],
            'relatedBlogs' => $related,
            'prices' => $blog->primaryService ? $blog->primaryService->prices()->where('is_active', true)->where('is_featured', true)->take(4)->get() : [],
        ]);
    }

    public function reviews()
    {
        PageSchema::set(PageSchema::breadcrumbs([['Reviews', '/reviews']]));

        return Inertia::render('Frontend/Reviews', [
            'reviews' => ReviewFeed::build(),
        ]);
    }

    public function contact()
    {
        PageSchema::set(PageSchema::breadcrumbs([['Contact', '/contact']]));

        return Inertia::render('Frontend/Contact', [
            'contact' => ContactContent::first(),
            'services' => $this->liveServices()->get(['id', 'name']),
            'hours' => [
                'Monday – Friday' => SiteSetting::get('hours.mon_fri'),
                'Saturday' => SiteSetting::get('hours.saturday'),
                'Sunday' => SiteSetting::get('hours.sunday'),
            ],
            'hoursNote' => SiteSetting::get('hours.note'),
        ]);
    }

    public function storeMessage(Request $request)
    {
        // Bots fill the hidden field; pretend success and store nothing.
        if (!empty($request->input('_hp'))) {
            return redirect()->back()->with('success', 'Thank you! Your message has been sent.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:40',
            'subject' => 'nullable|string|max:200',
            'message' => 'required|string|max:3000',
        ]);

        $message = Message::create([
            'name' => strip_tags($validated['name']),
            'email' => filter_var($validated['email'], FILTER_SANITIZE_EMAIL),
            'phone' => strip_tags($validated['phone'] ?? ''),
            'subject' => strip_tags($validated['subject'] ?? 'Website enquiry'),
            'message' => strip_tags($validated['message']),
            'is_read' => 0,
        ]);
        $this->notifyTeam($message);

        return redirect()->back()->with('success', 'Thank you! Your message has been sent. We usually reply the same day.');
    }

    public function privacyPolicy()
    {
        return Inertia::render('Frontend/PrivacyPolicy');
    }

    public function termsOfService()
    {
        return Inertia::render('Frontend/TermsOfService');
    }

    public function sitemap()
    {
        $urls = \App\Support\Sitemap::urls();

        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            $xml .= '<url><loc>' . e(url($u['loc'])) . '</loc>' . ($u['lastmod'] ? '<lastmod>' . $u['lastmod']->format('Y-m-d') . '</lastmod>' : '') . '</url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /** Email the business inbox and notify everyone who handles enquiries. Never blocks the customer. */
    private function notifyTeam(Message $message): void
    {
        try {
            $to = ContactContent::first()?->email;
            if ($to) {
                \Illuminate\Support\Facades\Notification::route('mail', $to)->notify(new \App\Notifications\NewEnquiry($message));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Enquiry email failed', ['message' => $message->id, 'error' => $e->getMessage()]);
        }

        try {
            $users = \App\Models\User::visible()->role(config('admin.super_role'))->get();
            try {
                $users = $users->merge(\App\Models\User::visible()->permission('enquiries.view')->get());
            } catch (\Throwable) {
            }
            foreach ($users->unique('id')->filter(fn ($u) => $u->is_active !== false) as $user) {
                $user->notify(new \App\Notifications\NewEnquiry($message));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Enquiry notification failed', ['error' => $e->getMessage()]);
        }
    }

    private function liveServices()
    {
        return ServiceDetail::where('is_active', true)->orderBy('order');
    }

    /** Adds "from S$…" (lowest active price) to each service card and drops the price rows. */
    /** First real paragraph of an HTML body (headings and short menu-like lines skipped). */
    private static function excerpt(string $html, int $limit = 150): string
    {
        $clean = fn ($t) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        preg_match_all('#<p\b[^>]*>(.*?)</p>#is', $html, $m);
        foreach ($m[1] as $para) {
            $text = $clean($para);
            if (mb_strlen($text) >= 60) {
                return \Illuminate\Support\Str::limit($text, $limit, '…');
            }
        }

        return \Illuminate\Support\Str::limit($clean(preg_replace('#<h[1-6]\b.*?</h[1-6]>#is', ' ', $html)), $limit, '…');
    }

    private function withFromPrice(Collection $services): Collection
    {
        return $services->map(function ($s) {
            $prices = $s->relationLoaded('prices') ? $s->prices : collect();
            $s->from_price = $prices->min('price_from');
            $s->unsetRelation('prices');
            // Cards always get a two-line summary: the admin summary, or the start of the description.
            if (blank($s->short_summary) && filled($s->desc)) {
                $s->short_summary = self::excerpt((string) $s->desc);
            }
            $s->makeHidden('desc');

            return $s;
        })->values();
    }
}
