<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AboutContent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AboutController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/About/Index', [
            'aboutContent' => AboutContent::first(),
        ]);
    }

    public function update(Request $request)
    {
        $about = AboutContent::firstOrNew();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'required|string',
            'short_desc' => 'required|string',
            'a_bread_title' => 'nullable|string',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'meta_tag' => 'nullable|string',
            'img_one' => 'nullable|image|max:10240',
            'img_two' => 'nullable|image|max:10240',
            'a_bread_img' => 'nullable|image|max:10240',
        ]);

        if ($request->hasFile('img_one')) {
            $about->img_one = $request->file('img_one')->store('Admin/About', 'uploads');
        }
        if ($request->hasFile('img_two')) {
            $about->img_two = $request->file('img_two')->store('Admin/About', 'uploads');
        }
        if ($request->hasFile('a_bread_img')) {
            $about->a_bread_img = $request->file('a_bread_img')->store('Admin/About/BreadCrumb', 'uploads');
        }

        $about->title = $validated['title'];
        $about->subtitle = $validated['subtitle'];
        $about->short_desc = $validated['short_desc'];
        $about->a_bread_title = $validated['a_bread_title'] ?? $about->a_bread_title;
        $about->meta_title = $validated['meta_title'] ?? $about->meta_title;
        $about->meta_description = $validated['meta_description'] ?? $about->meta_description;
        $about->meta_tag = $validated['meta_tag'] ?? $about->meta_tag;
        $about->save();

        return redirect()->back()->with('success', 'About page content updated successfully.');
    }
}
