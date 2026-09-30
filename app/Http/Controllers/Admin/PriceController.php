<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Price;
use App\Models\ServiceDetail;
use App\Support\SeoAudit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PriceController extends Controller
{
    public function index(Request $request)
    {
        $prices = Price::with('service:id,name,slug')
            ->when($request->filled('service'), fn ($q) => $q->where('service_id', $request->integer('service')))
            ->orderBy('service_id')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/Pricing/Index', [
            'prices' => $prices,
            'services' => ServiceDetail::orderBy('order')->get(['id', 'name']),
            'filters' => $request->only('service'),
            'staleAfterDays' => Price::STALE_AFTER_DAYS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] ??= (int) Price::where('service_id', $data['service_id'])->max('sort_order') + 1;
        Price::create($data + ['last_reviewed_at' => now()]);
        SeoAudit::flush();

        return redirect()->back()->with('success', 'Price added.');
    }

    public function update(Request $request, Price $price)
    {
        $price->update($this->validated($request) + ['last_reviewed_at' => now()]);

        return redirect()->back()->with('success', 'Price updated.');
    }

    public function markReviewed(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        Price::whereIn('id', $ids)->update(['last_reviewed_at' => now()]);

        return redirect()->back()->with('success', count($ids) . ' price(s) marked as reviewed today.');
    }

    public function destroy(Price $price)
    {
        $price->delete();
        SeoAudit::flush();

        return redirect()->back()->with('success', 'Price removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'service_id' => 'required|exists:service_details,id',
            'item' => 'required|string|max:255',
            'price_from' => 'required|integer|min:0|max:1000000',
            'price_to' => 'nullable|integer|gte:price_from|max:1000000',
            'unit' => 'nullable|string|max:60',
            'gst_note' => 'nullable|string|max:120',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if (blank($data['sort_order'] ?? null)) {
            unset($data['sort_order']);
        }

        return $data;
    }
}
