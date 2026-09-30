<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeCounter;
use App\Models\HomeHero;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The homepage hero: one headline block (no slider). Slides are bad for SEO and conversion:
 * the H1 keeps changing, later slides are rarely seen and they slow the page down.
 * The first row of home_heros holds it; the trust badges and eyebrow are site settings.
 */
class HeroController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Hero/Index', [
            'hero' => HomeHero::orderBy('id')->first(),
            'extraSlides' => max(0, HomeHero::count() - 1),
            'counters' => HomeCounter::all(['id', 'c_count', 'c_title']),
            'settings' => [
                'eyebrow' => SiteSetting::get('home.eyebrow', ''),
                'badges' => SiteSetting::get('home.badges', ''),
                'show_quote_form' => (bool) SiteSetting::get('home.show_quote_form', '1'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'subtitle' => 'nullable|string|max:400',
            'btn_name' => 'nullable|string|max:40',
            'btn_link' => ['nullable', 'string', 'max:255', 'regex:#^(/|https?://|tel:|mailto:)#'],
            'img' => 'nullable|image|max:10240',
            'remove_img' => 'boolean',
            'eyebrow' => 'nullable|string|max:80',
            'badges' => 'nullable|string|max:600',
            'show_quote_form' => 'boolean',
        ], ['btn_link.regex' => 'Start the link with / (a page on this site), https://, tel: or mailto:.']);

        $hero = HomeHero::orderBy('id')->first() ?? new HomeHero();
        $hero->title = $data['title'];
        $hero->subtitle = $data['subtitle'] ?? '';
        $hero->short_desc = $data['subtitle'] ?? '';
        $hero->btn_name = $data['btn_name'] ?: 'Get a free quote';
        $hero->btn_link = $data['btn_link'] ?: '/contact';
        if ($request->hasFile('img')) {
            $hero->img = $request->file('img')->store('Admin/Home/Hero', 'uploads');
        } elseif ($request->boolean('remove_img')) {
            $hero->img = null;
        }
        $hero->save();

        $badges = collect(preg_split('/\R/', (string) ($data['badges'] ?? '')))->map(fn ($b) => trim($b))->filter()->take(6)->join("\n");
        SiteSetting::putMany([
            'home.eyebrow' => trim((string) ($data['eyebrow'] ?? '')),
            'home.badges' => $badges,
            'home.show_quote_form' => $request->boolean('show_quote_form') ? '1' : '0',
        ]);

        return redirect()->back()->with('success', 'Homepage hero saved.');
    }

    /** Old extra slides are never shown; let the owner clear them out. */
    public function destroyExtra()
    {
        $first = HomeHero::orderBy('id')->value('id');
        $n = HomeHero::where('id', '!=', $first)->delete();

        return redirect()->back()->with('success', "{$n} unused slide(s) removed.");
    }
}
