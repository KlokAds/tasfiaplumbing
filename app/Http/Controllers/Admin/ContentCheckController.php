<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogDetail;
use App\Models\ServiceDetail;
use App\Support\ContentQuality;
use App\Support\TitleGuard;
use Illuminate\Http\Request;

/** Live checks used by the editors while typing. */
class ContentCheckController extends Controller
{
    public function title(Request $request)
    {
        $data = $request->validate([
            'text' => 'nullable|string|max:255',
            'type' => 'required|in:article,service,location',
            'id' => 'nullable|integer',
        ]);

        return response()->json(['conflict' => TitleGuard::conflict($data['text'] ?? null, $data['type'], $data['id'] ?? null)]);
    }

    /** Scores the unsaved editor form (SEO, AEO, GEO, E-E-A-T). */
    public function quality(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:article,service',
            'id' => 'nullable|integer',
            'name' => 'nullable|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_desc' => 'nullable|string|max:500',
            'excerpt' => 'nullable|string|max:1000',
            'body' => 'nullable|string|max:400000',
            'focus_keyword' => 'nullable|string|max:120',
            'slug' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:512',
            'faqs' => 'nullable|array|max:50',
            'faqs.*.question' => 'nullable|string|max:500',
            'faqs.*.answer' => 'nullable|string|max:3000',
            'has_service' => 'boolean',
            'warranty' => 'nullable|string|max:255',
            'response_time' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        if ($data['type'] === 'article') {
            $blog = !empty($data['id']) ? BlogDetail::with('author')->find($data['id']) : null;
            $author = $blog?->author ?? $user;
            $data['author'] = ['name' => $author->name, 'job_title' => $author->job_title, 'bio' => $author->bio];
            $data['updated_at'] = now()->toIso8601String();
        } else {
            $service = !empty($data['id']) ? ServiceDetail::find($data['id']) : null;
            $data['prices'] = $service ? $service->prices()->count() : 0;
            $data['updated_at'] = now()->toIso8601String();
        }

        return response()->json(ContentQuality::analyze($data));
    }
}
