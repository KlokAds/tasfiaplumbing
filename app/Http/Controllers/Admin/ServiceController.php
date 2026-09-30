<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Draft;
use App\Models\ServiceCategory;
use App\Models\ServiceDetail;
use App\Support\SeoAudit;
use App\Support\TitleGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ServiceController extends Controller
{
    public function index()
    {
        $dup = SeoAudit::duplicateTitles(ServiceDetail::class);

        $services = ServiceDetail::with(['category:id,name', 'faqs', 'prices'])
            ->withCount(['prices', 'faqs', 'articles'])
            ->orderBy('order')
            ->get()
            ->map(function (ServiceDetail $s) use ($dup) {
                $row = $s->toArray();
                $row['seo'] = SeoAudit::service($s, $dup);
                $row['word_count'] = SeoAudit::wordCount($s->desc);
                $row['public_path'] = $s->publicPath();
                return $row;
            });

        return Inertia::render('Admin/Services/Index', [
            'services' => $services,
            'categories' => ServiceCategory::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $service = new ServiceDetail($data);
        $service->order = $data['order'] ?? ((int) ServiceDetail::max('order') + 1);
        $service->btn_name = 'Read More';
        $this->storeImages($request, $service);
        $service->save();
        $service->syncFaqs($request->input('faqs'));
        Draft::discard($request->user()->id, 'service', 0);
        SeoAudit::flush();

        return redirect()->route('admin.services.index')->with('success', 'Service created.');
    }

    public function update(Request $request, $id)
    {
        $service = ServiceDetail::findOrFail($id);
        $data = $this->validated($request, $service->id);
        $oldPath = $service->publicPath();

        $service->fill($data);
        $this->storeImages($request, $service);
        $service->save();
        $service->syncFaqs($request->input('faqs'));
        Draft::discard($request->user()->id, 'service', $service->id);
        SeoAudit::flush();

        $message = 'Service updated.';
        if ($oldPath !== $service->publicPath()) {
            $message .= " URL changed: {$oldPath} now 301-redirects to {$service->publicPath()}.";
        }

        return redirect()->route('admin.services.index')->with('success', $message);
    }

    public function destroy($id)
    {
        $service = ServiceDetail::findOrFail($id);
        $path = $service->publicPath();
        $service->faqs()->delete();
        $service->delete();
        SeoAudit::flush();

        return redirect()->route('admin.services.index')
            ->with('success', "Service deleted. {$path} now returns 410; point it to a replacement in Redirects if one exists.");
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        if ($request->filled('slug')) {
            $request->merge(['slug' => Str::slug($request->input('slug'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', TitleGuard::rule('service', $ignoreId, 'title')],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('service_details', 'slug')->ignore($ignoreId)],
            'category_id' => 'nullable|exists:service_categories,id',
            'order' => 'nullable|integer',
            'is_active' => 'boolean',
            'short_summary' => 'nullable|string|max:500',
            'desc' => 'required|string',
            'response_time' => 'nullable|string|max:100',
            'warranty' => 'nullable|string|max:100',
            'focus_keyword' => 'nullable|string|max:120',
            'meta_title' => ['nullable', 'string', 'max:255', TitleGuard::rule('service', $ignoreId, 'meta title')],
            'meta_desc' => 'nullable|string|max:500',
            'canonical' => 'nullable|url|max:255',
            'noindex' => 'boolean',
            'image' => 'nullable|image|max:5120',
            'bef_img' => 'nullable|image|max:5120',
            'aft_img' => 'nullable|image|max:5120',
            'faqs' => 'nullable|array|max:30',
            'faqs.*.question' => 'nullable|string|max:500',
            'faqs.*.answer' => 'nullable|string|max:3000',
        ]);

        unset($data['image'], $data['bef_img'], $data['aft_img'], $data['faqs']);
        foreach (['slug', 'order'] as $key) {
            if (blank($data[$key] ?? null)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    private function storeImages(Request $request, ServiceDetail $service): void
    {
        foreach (['image', 'bef_img', 'aft_img'] as $field) {
            if ($request->hasFile($field)) {
                $service->{$field} = $request->file($field)->store('Admin/Service/Details', 'uploads');
            }
        }
    }
}
