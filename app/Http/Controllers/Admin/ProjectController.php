<?php

namespace App\Http\Controllers\Admin;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\ProjectDetail;
use App\Models\ServiceDetail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = ProjectDetail::with(['service:id,name', 'location:id,name'])->orderByDesc('completed_on')->latest();

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('area', 'like', $term));
        }
        if ($request->filled('service')) {
            $request->input('service') === 'none'
                ? $query->whereNull('service_id')
                : $query->where('service_id', $request->integer('service'));
        }

        return Inertia::render('Admin/Projects/Index', [
            'projects' => $query->paginate(PerPage::get($request, 25))->withQueryString(),
            'filters' => $request->only(['search', 'service', 'per_page']),
            'services' => ServiceDetail::orderBy('order')->get(['id', 'name']),
            'locations' => Location::orderBy('name')->get(['id', 'name']),
            'propertyTypes' => Location::PROPERTY_TYPES,
            'unlinked' => ProjectDetail::whereNull('service_id')->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $data['image'] = $request->file('image')->store('Admin/Project/Details', 'uploads');
        ProjectDetail::create($data);

        return redirect()->back()->with('success', 'Project added.');
    }

    public function update(Request $request, $id)
    {
        $project = ProjectDetail::findOrFail($id);
        $data = $this->validated($request, false);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('Admin/Project/Details', 'uploads');
        } else {
            unset($data['image']);
        }
        $project->update($data);

        return redirect()->back()->with('success', 'Project updated.');
    }

    public function destroy($id)
    {
        ProjectDetail::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Project deleted.');
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'video' => 'nullable|url|max:500',
            'image' => [$creating ? 'required' : 'nullable', 'image', 'max:10240'],
            'service_id' => 'nullable|exists:service_details,id',
            'location_id' => 'nullable|exists:locations,id',
            'area' => 'nullable|string|max:120',
            'property_type' => ['nullable', Rule::in(Location::PROPERTY_TYPES)],
            'summary' => 'nullable|string|max:1000',
            'completed_on' => 'nullable|date|before_or_equal:today',
            'is_active' => 'boolean',
        ]);
    }
}
