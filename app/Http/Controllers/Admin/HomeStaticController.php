<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeCounter;
use App\Models\HomeSkill;
use App\Models\HomeStatic;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeStaticController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/HomeStatic/Index', [
            'homeStatic' => HomeStatic::first(),
            'counters' => HomeCounter::all(),
            'skills' => HomeSkill::all(),
        ]);
    }

    public function update(Request $request)
    {
        $homestatic = HomeStatic::firstOrNew();

        $validated = $request->validate([
            'h_s_title' => 'nullable|string',
            'h_s_subtitle' => 'nullable|string',
            'h_a_b_name' => 'nullable|string',
            'h_a_b_link' => 'nullable|string',
            'h_sl_title' => 'nullable|string',
            'h_sl_subtitle' => 'nullable|string',
            'h_n_title' => 'nullable|string',
            'h_n_subtitle' => 'nullable|string',
            'h_c_title' => 'nullable|string',
            'h_c_button_name' => 'nullable|string',
            'h_p_title' => 'nullable|string',
            'h_p_subtitle' => 'nullable|string',
            'h_n_c_title' => 'nullable|string',
            'h_n_c_desc' => 'nullable|string',
            'h_n_c_number' => 'nullable|string',
            'h_t_title' => 'nullable|string',
            'h_test_title' => 'nullable|string',
            'h_b_title' => 'nullable|string',
            'h_b_subtitle' => 'nullable|string',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'meta_tag' => 'nullable|string',
        ]);

        $homestatic->fill($validated);
        $homestatic->save();

        return redirect()->back()->with('success', 'Home section contents updated successfully.');
    }

    public function storeCounter(Request $request)
    {
        $validated = $request->validate([
            'c_count' => 'required|string',
            'c_title' => 'required|string',
            'c_subtitle' => 'nullable|string',
            'c_icon' => 'nullable|string',
        ]);

        HomeCounter::create($validated);
        return redirect()->back()->with('success', 'Stat counter added successfully.');
    }

    public function destroyCounter($id)
    {
        $counter = HomeCounter::findOrFail($id);
        $counter->delete();
        return redirect()->back()->with('success', 'Stat counter deleted.');
    }

    public function storeSkill(Request $request)
    {
        $validated = $request->validate([
            's_point' => 'required|string',
            's_title' => 'required|string',
            's_subtitle' => 'nullable|string',
            's_icon' => 'nullable|string',
        ]);

        HomeSkill::create($validated);
        return redirect()->back()->with('success', 'Skill capability added successfully.');
    }

    public function destroySkill($id)
    {
        $skill = HomeSkill::findOrFail($id);
        $skill->delete();
        return redirect()->back()->with('success', 'Skill capability deleted.');
    }
}
