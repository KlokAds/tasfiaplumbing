<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Draft;
use Illuminate\Http\Request;

/** Autosave storage for the service and article editors (per user, per record). */
class DraftController extends Controller
{
    public function show(Request $request, string $type, int $record = 0)
    {
        $this->check($type);
        $draft = Draft::where(['user_id' => $request->user()->id, 'type' => $type, 'record_id' => $record])->first();

        return $draft
            ? response()->json(['payload' => $draft->payload, 'saved_at' => $draft->updated_at->toIso8601String()])
            : response()->noContent();
    }

    public function store(Request $request, string $type, int $record = 0)
    {
        $this->check($type);
        $data = $request->validate(['payload' => 'required|array']);

        // Keep drafts to a sane size; files are never part of the payload.
        abort_if(strlen(json_encode($data['payload'])) > 1_500_000, 413, 'Draft too large.');

        $draft = Draft::updateOrCreate(
            ['user_id' => $request->user()->id, 'type' => $type, 'record_id' => $record],
            ['payload' => $data['payload']],
        );
        $draft->touch();

        return response()->json(['saved_at' => $draft->updated_at->toIso8601String()]);
    }

    public function destroy(Request $request, string $type, int $record = 0)
    {
        $this->check($type);
        Draft::discard($request->user()->id, $type, $record);

        return response()->json(['ok' => true]);
    }

    private function check(string $type): void
    {
        abort_unless(in_array($type, Draft::TYPES, true), 404);
    }
}
