<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeedBackContent;
use App\Models\SiteSetting;
use App\Support\GoogleReviews;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => FeedBackContent::orderByDesc('review_date')->latest()->get(),
            'google' => [
                'enabled' => (bool) SiteSetting::get('reviews.google_enabled'),
                'place_id' => GoogleReviews::placeId(),
                'has_key' => (bool) GoogleReviews::apiKey(),
                'min_rating' => (string) (SiteSetting::get('reviews.google_min_rating') ?: '4'),
                'show_own' => (bool) SiteSetting::get('reviews.show_own', '1'),
                'result' => GoogleReviews::enabled() ? GoogleReviews::get() : null,
            ],
            // Business Profile import (Insights → Google connections): every review, with hide/show.
            'profile' => SiteSetting::get('google.gbp_location') ? [
                'name' => SiteSetting::get('google.gbp_location_name'),
                'rating' => SiteSetting::get('google.gbp_rating'),
                'total' => SiteSetting::get('google.gbp_total'),
                'synced_at' => SiteSetting::get('google.gbp_synced_at'),
            ] : null,
            'imported' => \App\Models\GoogleReview::orderByDesc('reviewed_at')->take(500)
                ->get(['id', 'author', 'photo', 'rating', 'comment', 'reply', 'is_hidden', 'reviewed_at']),
        ]);
    }

    /** Hide a Google review from the website (it stays on Google). */
    public function toggleGoogle(\App\Models\GoogleReview $googleReview)
    {
        $googleReview->update(['is_hidden' => !$googleReview->is_hidden]);

        return back()->with('success', $googleReview->is_hidden ? 'Review hidden from the website.' : 'Review shown on the website again.');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('img')) {
            $data['img'] = $request->file('img')->store('Admin/Feedback', 'uploads');
        }
        FeedBackContent::create($data);

        return redirect()->back()->with('success', 'Review added.');
    }

    public function update(Request $request, FeedBackContent $review)
    {
        $data = $this->validated($request);
        if ($request->hasFile('img')) {
            $data['img'] = $request->file('img')->store('Admin/Feedback', 'uploads');
        } else {
            unset($data['img']);
        }
        $review->update($data);

        return redirect()->back()->with('success', 'Review updated.');
    }

    public function destroy(FeedBackContent $review)
    {
        $review->delete();

        return redirect()->back()->with('success', 'Review deleted.');
    }

    public function saveGoogle(Request $request)
    {
        $data = $request->validate([
            'enabled' => 'boolean',
            'place_id' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'api_key' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'min_rating' => 'required|in:1,2,3,4,5',
            'show_own' => 'boolean',
        ], [
            'place_id.regex' => 'That does not look like a Place ID (it usually starts with "ChIJ").',
            'api_key.regex' => 'That does not look like a Google API key.',
        ]);

        SiteSetting::putMany([
            'reviews.google_enabled' => $request->boolean('enabled') ? '1' : '0',
            'reviews.google_place_id' => trim((string) ($data['place_id'] ?? '')),
            'reviews.google_min_rating' => $data['min_rating'],
            'reviews.show_own' => $request->boolean('show_own') ? '1' : '0',
        ]);
        if (filled($data['api_key'] ?? null)) {
            GoogleReviews::storeApiKey($data['api_key']);
        }
        GoogleReviews::flush();

        if ($request->boolean('enabled')) {
            $result = GoogleReviews::get();
            if (!$result['ok']) {
                return redirect()->back()->with('error', 'Saved, but Google did not return reviews: ' . $result['error']);
            }

            return redirect()->back()->with('success', "Connected. Google rating {$result['rating']} from {$result['total']} reviews; " . count($result['reviews']) . ' shown on the site.');
        }

        return redirect()->back()->with('success', 'Review settings saved.');
    }

    public function refreshGoogle()
    {
        GoogleReviews::flush();
        $result = GoogleReviews::get();

        return redirect()->back()->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Google reviews refreshed.' : $result['error']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'desc' => 'required|string|max:3000',
            'rating' => 'required|integer|between:1,5',
            'location' => 'nullable|string|max:120',
            'job' => 'nullable|string|max:150',
            'review_date' => 'nullable|date',
            'is_active' => 'boolean',
            'img' => 'nullable|image|max:8192',
        ]);
    }
}
