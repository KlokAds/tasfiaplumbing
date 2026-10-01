<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\ServiceDetail;
use App\Models\SiteSetting;
use App\Support\ContentQuality;
use App\Support\ContentScan;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Writing guide for articles, service and location pages, with a live scan of what the site is
 * missing. The rules and checks come from the same code that scores pages (config seo.audit and
 * ContentQuality), so the guide never drifts.
 */
class SeoGuideController extends Controller
{
    public function index(Request $request)
    {
        $service = ServiceDetail::where('is_active', true)->orderBy('order')
            ->with(['prices' => fn ($q) => $q->where('is_active', true)])->first();
        $price = $service?->prices->first(fn ($p) => (float) $p->price_from > 0);

        return Inertia::render('Admin/Seo/Guide', [
            'brand' => trim((string) SiteSetting::get('business.brand_name', config('app.name'))),
            'rules' => config('seo.audit'),
            'pillars' => ContentQuality::PILLARS,
            // What the live site is missing right now (cached; cleared when content is saved).
            'scan' => ContentScan::get($request->boolean('refresh')),
            'checks' => ['article' => self::checks('article'), 'service' => self::checks('service')],
            'example' => [
                'service' => $service?->name,
                'location' => Location::where('is_active', true)->orderBy('sort_order')->value('name'),
                'price_from' => $price ? (float) $price->price_from : null,
                'price_to' => $price && $price->price_to ? (float) $price->price_to : null,
                'warranty' => $service?->warranty,
                'response_time' => $service?->response_time,
            ],
        ]);
    }

    /** Every check of one page type, grouped by pillar, with the general form of its tip. */
    private static function checks(string $type): array
    {
        return collect(ContentQuality::analyze(['type' => $type, 'faqs' => []])['pillars'])
            ->map(fn ($p) => collect($p['checks'])->map(fn ($c) => [
                'label' => $c['label'],
                'tip' => ContentScan::generalTip((string) $c['tip']),
                'weight' => $c['weight'],
            ])->values()->all())
            ->all();
    }
}
