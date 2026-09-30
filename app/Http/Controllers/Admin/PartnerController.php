<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PartnerController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Partners/Index', [
            'partners' => Partner::all(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $imgPath = $request->file('image')->store('Admin/Partner', 'uploads');

        Partner::create([
            'image' => $imgPath,
        ]);

        return redirect()->back()->with('success', 'Partner logo added successfully.');
    }

    public function update(Request $request, $id)
    {
        $partner = Partner::findOrFail($id);

        $request->validate([
            'image' => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $partner->image = $request->file('image')->store('Admin/Partner', 'uploads');
        }

        $partner->save();

        return redirect()->back()->with('success', 'Partner logo updated successfully.');
    }

    public function destroy($id)
    {
        $partner = Partner::findOrFail($id);
        $partner->delete();

        return redirect()->back()->with('success', 'Partner logo removed.');
    }
}
