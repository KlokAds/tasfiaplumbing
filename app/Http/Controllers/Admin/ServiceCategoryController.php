<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Models\ServiceDetail;
use App\Support\SeoAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ServiceCategoryController extends Controller
{
    public function index()
    {
        $categories = ServiceCategory::with('services:id,name,slug,category_id')
            ->withCount('services')
            ->orderBy('sort_order')
            ->get()
            ->map(function (ServiceCategory $c) {
                $row = $c->toArray();
                $row['seo'] = SeoAudit::category($c);
                $row['public_path'] = $c->publicPath();
                return $row;
            });

        return Inertia::render('Admin/ServiceCategories/Index', [
            'categories' => $categories,
            'services' => ServiceDetail::orderBy('order')->get(['id', 'name', 'category_id']),
        ]);
    }

    public function store(Request $request)
    {
        $category = new ServiceCategory($this->validated($request));
        $category->sort_order ??= (int) ServiceCategory::max('sort_order') + 1;
        $this->storeImage($request, $category);
        $category->save();
        $this->syncServices($category, $request->boolean('sync_services') ? $request->input('service_ids', []) : null);
        SeoAudit::flush();

        return redirect()->route('admin.service-categories.index')->with('success', 'Category created.');
    }

    public function update(Request $request, ServiceCategory $category)
    {
        $category->fill($this->validated($request, $category->id));
        $this->storeImage($request, $category);
        $category->save();
        $this->syncServices($category, $request->boolean('sync_services') ? $request->input('service_ids', []) : null);
        SeoAudit::flush();

        return redirect()->route('admin.service-categories.index')->with('success', 'Category updated.');
    }

    public function destroy(ServiceCategory $category)
    {
        ServiceDetail::where('category_id', $category->id)->update(['category_id' => null]);
        $category->delete();
        SeoAudit::flush();

        return redirect()->route('admin.service-categories.index')->with('success', 'Category deleted. Its services are now uncategorised.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        if ($request->filled('slug')) {
            $request->merge(['slug' => Str::slug($request->input('slug'))]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('service_categories', 'slug')->ignore($ignoreId)],
            'intro' => 'nullable|string|max:1000',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_desc' => 'nullable|string|max:500',
            'canonical' => 'nullable|url|max:255',
            'noindex' => 'boolean',
            'image' => 'nullable|image|max:5120',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:service_details,id',
        ]);

        unset($data['image'], $data['service_ids']);
        foreach (['slug', 'sort_order'] as $key) {
            if (blank($data[$key] ?? null)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    private function storeImage(Request $request, ServiceCategory $category): void
    {
        if ($request->hasFile('image')) {
            $category->image = $request->file('image')->store('Admin/Service/Categories', 'uploads');
        }
    }

    private function syncServices(ServiceCategory $category, ?array $ids): void
    {
        if ($ids === null) {
            return;
        }
        ServiceDetail::where('category_id', $category->id)->whereNotIn('id', $ids)->update(['category_id' => null]);
        ServiceDetail::whereIn('id', $ids)->update(['category_id' => $category->id]);
    }
}
