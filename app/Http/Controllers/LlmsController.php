<?php

namespace App\Http\Controllers;

use App\Models\BlogDetail;
use App\Models\ServiceCategory;
use App\Models\ServiceDetail;
use App\Models\SiteSetting;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * /llms.txt: a short, plain-text guide to the business for AI assistants (llmstxt.org).
 * Built from the live content (services, categories, latest guides), so it never goes stale.
 */
class LlmsController extends Controller
{
    public function __invoke()
    {
        $text = Cache::remember('llms.txt', now()->addHours(6), fn () => $this->build());

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'X-Robots-Tag' => 'noindex']);
    }

    private function build(): string
    {
        $d = SystemSettings::pageDetails();
        $url = fn (string $path) => url($path);
        $line = fn (string $s) => trim(preg_replace('/\s+/', ' ', strip_tags($s)));
        $out = [];

        $out[] = '# ' . $d['brand'];
        $out[] = '';
        $out[] = '> ' . $line((string) SiteSetting::get('seo.default_meta_desc', ''));
        $out[] = '';
        $area = SiteSetting::get('business.area_served', 'Singapore');
        $out[] = "{$d['brand']} is a home service business serving {$area}. Customers ask for a quote by WhatsApp, phone or the form on the website.";
        $out[] = '';

        $out[] = '## Contact';
        if ($d['phone']) {
            $out[] = "- Phone and WhatsApp: {$d['phone']}";
        }
        if ($d['email']) {
            $out[] = "- Email: {$d['email']}";
        }
        $out[] = "- Area served: {$area}";
        $out[] = '- Quote and contact page: ' . $url('/contact');
        $out[] = '';

        $out[] = '## Key pages';
        foreach (['/services' => 'All services', '/pricing' => 'Prices', '/projects' => 'Recent jobs with photos', '/reviews' => 'Customer reviews', '/about' => 'About the company', '/blogs' => 'Guides and advice'] as $path => $label) {
            $out[] = "- [{$label}]({$url($path)})";
        }
        $out[] = '';

        $indexable = fn ($q) => $q->where('is_active', true)->where('noindex', false);
        $cats = ServiceCategory::where($indexable)->orderBy('sort_order')->get(['id', 'name', 'slug']);
        $services = ServiceDetail::where($indexable)->orderBy('order')->get(['name', 'slug', 'short_summary', 'category_id', 'desc']);
        $summary = fn ($s) => Str::limit($line($s->short_summary ?: (string) $s->desc), 160);

        $out[] = '## Services';
        foreach ($cats as $c) {
            $inCat = $services->where('category_id', $c->id);
            if ($inCat->isEmpty()) {
                continue;
            }
            $out[] = '';
            $out[] = "### [{$c->name}]({$url($c->publicPath())})";
            foreach ($inCat as $s) {
                $out[] = "- [{$s->name}]({$url($s->publicPath())}): {$summary($s)}";
            }
        }
        $loose = $services->whereNotIn('category_id', $cats->pluck('id'));
        if ($loose->isNotEmpty()) {
            $out[] = '';
            foreach ($loose as $s) {
                $out[] = "- [{$s->name}]({$url($s->publicPath())}): {$summary($s)}";
            }
        }
        $out[] = '';

        $blogs = BlogDetail::published()->where('noindex', false)->latest('published_at')->take(40)->get(['name', 'slug', 'excerpt']);
        if ($blogs->isNotEmpty()) {
            $out[] = '## Guides';
            foreach ($blogs as $b) {
                $out[] = "- [{$b->name}]({$url($b->publicPath())})" . ($b->excerpt ? ': ' . Str::limit($line($b->excerpt), 140) : '');
            }
            $out[] = '';
        }

        $out[] = '## Optional';
        $out[] = '- [Full sitemap](' . $url('/sitemap.xml') . ')';

        return implode("\n", $out) . "\n";
    }
}
