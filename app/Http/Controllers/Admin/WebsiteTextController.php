<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Admin → Website → Website text: headings and short texts used across the public pages. */
class WebsiteTextController extends Controller
{
    public function index()
    {
        $values = SiteSetting::values();
        $sections = [];
        foreach (config('seo.settings.texts.fields') as $key => $f) {
            $sections[$f['section']]['title'] = $f['section'];
            $sections[$f['section']]['page'] ??= $f['page'] ?? null;
            $sections[$f['section']]['fields'][] = [
                'key' => $key,
                'label' => $f['label'],
                'type' => $f['type'],
                'value' => (string) ($values[$key] ?? ''),
                'default' => (string) ($f['default'] ?? ''),
            ];
        }

        return Inertia::render('Admin/WebsiteText', ['sections' => array_values($sections)]);
    }

    public function update(Request $request)
    {
        $fields = config('seo.settings.texts.fields');
        $data = $request->validate(['texts' => 'required|array', 'texts.*' => 'nullable|string|max:600']);

        SiteSetting::putMany(collect($data['texts'])->only(array_keys($fields))->map(fn ($v) => trim((string) $v))->all());

        return back()->with('success', 'Website text saved. It shows on the site straight away.');
    }
}
