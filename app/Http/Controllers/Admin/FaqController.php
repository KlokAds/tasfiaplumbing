<?php

namespace App\Http\Controllers\Admin;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\BlogDetail;
use App\Models\Faq;
use App\Models\Location;
use App\Models\ServiceDetail;
use App\Support\SeoAudit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FaqController extends Controller
{
    private const TYPES = [
        'service' => ServiceDetail::class,
        'location' => Location::class,
        'article' => BlogDetail::class,
    ];

    public function index(Request $request)
    {
        $scope = $request->input('scope');

        $faqs = Faq::with('faqable:id,name,slug')
            ->when($scope === 'global', fn ($q) => $q->whereNull('faqable_type'))
            ->when(isset(self::TYPES[$scope]), fn ($q) => $q->where('faqable_type', self::TYPES[$scope]))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w
                ->where('question', 'like', '%' . $request->search . '%')
                ->orWhere('answer', 'like', '%' . $request->search . '%')))
            ->orderBy('faqable_type')
            ->orderBy('faqable_id')
            ->orderBy('sort_order')
            ->paginate(PerPage::get($request, 25))
            ->withQueryString();

        $faqs->getCollection()->transform(fn (Faq $f) => [
            'id' => $f->id,
            'question' => $f->question,
            'answer' => $f->answer,
            'is_active' => $f->is_active,
            'sort_order' => $f->sort_order,
            'scope' => array_search($f->faqable_type, self::TYPES, true) ?: 'global',
            'scope_label' => $f->scope_label,
            'parent_id' => $f->faqable_id,
            'parent_name' => $f->faqable?->name,
        ]);

        $counts = Faq::selectRaw('faqable_type, count(*) as n')->groupBy('faqable_type')->pluck('n', 'faqable_type');

        return Inertia::render('Admin/Faqs/Index', [
            'faqs' => $faqs,
            'filters' => $request->only(['scope', 'search', 'per_page']),
            'counts' => [
                'global' => Faq::whereNull('faqable_type')->count(),
                'service' => $counts[ServiceDetail::class] ?? 0,
                'location' => $counts[Location::class] ?? 0,
                'article' => $counts[BlogDetail::class] ?? 0,
            ],
            'parents' => [
                'service' => ServiceDetail::orderBy('order')->get(['id', 'name']),
                'location' => Location::orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function store(Request $request)
    {
        Faq::create($this->validated($request));
        SeoAudit::flush();

        return redirect()->back()->with('success', 'FAQ added.');
    }

    public function update(Request $request, Faq $faq)
    {
        $faq->update($this->validated($request));
        SeoAudit::flush();

        return redirect()->back()->with('success', 'FAQ updated.');
    }

    public function destroy(Faq $faq)
    {
        $faq->delete();
        SeoAudit::flush();

        return redirect()->back()->with('success', 'FAQ deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:3000',
            'scope' => ['required', Rule::in(['global', 'service', 'location', 'article'])],
            'parent_id' => 'nullable|required_unless:scope,global|integer',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $type = self::TYPES[$data['scope']] ?? null;
        if ($type && !$type::whereKey($data['parent_id'])->exists()) {
            abort(422, 'Selected page does not exist.');
        }

        return [
            'question' => $data['question'],
            'answer' => $data['answer'],
            'faqable_type' => $type,
            'faqable_id' => $type ? $data['parent_id'] : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ];
    }
}
