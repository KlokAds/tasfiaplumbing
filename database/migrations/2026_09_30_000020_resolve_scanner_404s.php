<?php

use App\Support\NotFoundRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Scanner noise that was logged before it was filtered out: mark it resolved so the list shows real problems only.
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('not_found_logs')) {
            return;
        }
        DB::table('not_found_logs')->where('is_resolved', false)->orderBy('id')->chunkById(500, function ($rows) {
            $ids = collect($rows)->filter(fn ($r) => NotFoundRules::isNoise($r->path))->pluck('id');
            if ($ids->isNotEmpty()) {
                DB::table('not_found_logs')->whereIn('id', $ids)->update(['is_resolved' => true]);
            }
        });
    }

    public function down(): void
    {
        // Nothing to undo: the rows are kept, only hidden from the open list.
    }
};
