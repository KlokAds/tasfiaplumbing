<?php

namespace App\Support;

use Illuminate\Http\Request;

/** "Rows per page" chosen in admin lists (?per_page=50). Only fixed sizes are allowed. */
class PerPage
{
    public const OPTIONS = [10, 25, 50, 100, 500];

    public static function get(Request $request, int $default = 25): int
    {
        $value = $request->integer('per_page', $default);

        return in_array($value, self::OPTIONS, true) ? $value : $default;
    }
}
