<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\ServiceDetail;
use App\Support\SeoAudit;
use App\Support\TitleGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::with(['faqs', 'services:id,name'])
            ->withCount('faqs')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Location $l) {
                $row = $l->toArray();
                $row['seo'] = SeoAudit::location($l);
                $row['word_count'] = SeoAudit::wordCount($l->intro . ' ' . $l->description);
                $row['public_path'] = $l->publicPath();
                $row['service_ids'] = $l->services->pluck('id');
                return $row;
            });

        return Inertia::render('Admin/Locations/Index', [
            'locations' => $locations,
            'services' => ServiceDetail::orderBy('order')->get(['id', 'name']),
            'regions' => Location::REGIONS,
            'propertyTypes' => Location::PROPERTY_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $location = new Location($this->validated($request));
        $location->sort_order ??= (int) Location::max('sort_order') + 1;
        $this->storeImage($request, $location);
        $location->save();
        $location->syncFaqs($request->input('faqs'));
        $location->services()->sync($request->input('service_ids', []));
        SeoAudit::flush();

        return redirect()->route('admin.locations.index')->with('success', 'Location created.');
    }

    public function update(Request $request, Location $location)
    {
        $location->fill($this->validated($request, $location->id));
        $this->storeImage($request, $location);
        $location->save();
        $location->syncFaqs($request->input('faqs'));
        $location->services()->sync($request->input('service_ids', []));
        SeoAudit::flush();

        return redirect()->route('admin.locations.index')->with('success', 'Location updated.');
    }

    public function destroy(Location $location)
    {
        $location->faqs()->delete();
        $location->delete();
        SeoAudit::flush();

        return redirect()->route('admin.locations.index')->with('success', 'Location deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        if ($request->filled('slug')) {
            $request->merge(['slug' => Str::slug($request->input('slug'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', TitleGuard::rule('location', $ignoreId, 'title')],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('locations', 'slug')->ignore($ignoreId)],
            'region' => ['nullable', Rule::in(Location::REGIONS)],
            'intro' => 'nullable|string|max:2000',
            'description' => 'nullable|string',
            'property_types' => 'nullable|array',
            'property_types.*' => [Rule::in(Location::PROPERTY_TYPES)],
            'nearby_areas' => 'nullable|array|max:20',
            'nearby_areas.*' => 'string|max:80',
            'latitude' => 'nullable|numeric|between:1.1,1.5',
            'longitude' => 'nullable|numeric|between:103.5,104.1',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'meta_title' => ['nullable', 'string', 'max:255', TitleGuard::rule('location', $ignoreId, 'meta title')],
            'meta_desc' => 'nullable|string|max:500',
            'canonical' => 'nullable|url|max:255',
            'noindex' => 'boolean',
            'image' => 'nullable|image|max:5120',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:service_details,id',
            'faqs' => 'nullable|array|max:20',
            'faqs.*.question' => 'nullable|string|max:500',
            'faqs.*.answer' => 'nullable|string|max:3000',
        ]);

        unset($data['image'], $data['service_ids'], $data['faqs']);
        // Empty arrays are not sent in multipart forms, so "none selected" arrives as missing.
        $data['property_types'] = $data['property_types'] ?? [];
        $data['nearby_areas'] = $data['nearby_areas'] ?? [];
        foreach (['slug', 'sort_order'] as $key) {
            if (blank($data[$key] ?? null)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    private function storeImage(Request $request, Location $location): void
    {
        if ($request->hasFile('image')) {
            $location->image = $request->file('image')->store('Admin/Locations', 'uploads');
        }
    }
}
