<?php

namespace App\Http\Controllers;

use App\Support\VisitorStats;
use Illuminate\Http\Request;

/** The website's own visitor counter: the page sends one small signal per page view or click (POST /t). */
class TrackController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            't' => 'required|string|max:20',
            'p' => 'nullable|string|max:500',
            'z' => 'nullable|string|max:64',
            'r' => 'nullable|string|max:500',
            'w' => 'nullable|integer|min:0|max:10000',
        ]);
        VisitorStats::record($request, $data['t'], $data['p'] ?? null, $data['z'] ?? null, $data['r'] ?? null, $data['w'] ?? null);

        return response()->noContent();
    }
}
