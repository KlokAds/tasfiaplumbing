<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BreadCumb;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BreadcrumbController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Breadcrumbs/Index', [
            'breadcrumb' => BreadCumb::first(),
        ]);
    }

    public function update(Request $request)
    {
        $breadcrumb = BreadCumb::firstOrNew();

        $validated = $request->validate([
            's_bread_name' => 'nullable|string',
            'p_bread_name' => 'nullable|string',
            'f_bread_name' => 'nullable|string',
            'b_bread_name' => 'nullable|string',
            's_meta_title' => 'nullable|string',
            's_meta_desc' => 'nullable|string',
            's_meta_tag' => 'nullable|string',
            'p_meta_title' => 'nullable|string',
            'p_meta_desc' => 'nullable|string',
            'p_meta_tag' => 'nullable|string',
            's_bread_image' => 'nullable|image|max:10240',
            'p_bread_image' => 'nullable|image|max:10240',
            'f_bread_image' => 'nullable|image|max:10240',
            'b_bread_image' => 'nullable|image|max:10240',
        ]);

        if ($request->hasFile('s_bread_image')) {
            $breadcrumb->s_bread_image = $request->file('s_bread_image')->store('Admin/Service/BreadCrumb', 'uploads');
        }
        if ($request->hasFile('p_bread_image')) {
            $breadcrumb->p_bread_image = $request->file('p_bread_image')->store('Admin/Project/BreadCrumb', 'uploads');
        }
        if ($request->hasFile('f_bread_image')) {
            $breadcrumb->f_bread_image = $request->file('f_bread_image')->store('Admin/Feedback/BreadCrumb', 'uploads');
        }
        if ($request->hasFile('b_bread_image')) {
            $breadcrumb->b_bread_image = $request->file('b_bread_image')->store('Admin/Blog/BreadCrumb', 'uploads');
        }

        $breadcrumb->s_bread_name = $validated['s_bread_name'] ?? $breadcrumb->s_bread_name;
        $breadcrumb->p_bread_name = $validated['p_bread_name'] ?? $breadcrumb->p_bread_name;
        $breadcrumb->f_bread_name = $validated['f_bread_name'] ?? $breadcrumb->f_bread_name;
        $breadcrumb->b_bread_name = $validated['b_bread_name'] ?? $breadcrumb->b_bread_name;
        $breadcrumb->s_meta_title = $validated['s_meta_title'] ?? $breadcrumb->s_meta_title;
        $breadcrumb->s_meta_desc = $validated['s_meta_desc'] ?? $breadcrumb->s_meta_desc;
        $breadcrumb->s_meta_tag = $validated['s_meta_tag'] ?? $breadcrumb->s_meta_tag;
        $breadcrumb->p_meta_title = $validated['p_meta_title'] ?? $breadcrumb->p_meta_title;
        $breadcrumb->p_meta_desc = $validated['p_meta_desc'] ?? $breadcrumb->p_meta_desc;
        $breadcrumb->p_meta_tag = $validated['p_meta_tag'] ?? $breadcrumb->p_meta_tag;
        $breadcrumb->save();

        return redirect()->back()->with('success', 'Page breadcrumbs and banner titles updated.');
    }
}
