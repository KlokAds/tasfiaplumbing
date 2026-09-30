<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Reads the permission catalogue in config/admin.php. */
class Permissions
{
    public const STANDARD = ['view', 'create', 'edit', 'delete'];

    /** @return string[] every permission name, e.g. "services.edit" */
    public static function all(): array
    {
        $names = [];
        foreach (config('admin.modules') as $modules) {
            foreach ($modules as $module => $def) {
                foreach (array_keys($def['actions']) as $action) {
                    $names[] = "{$module}.{$action}";
                }
            }
        }

        return $names;
    }

    /** name => "Module: Action" (for badges and validation messages). */
    public static function labels(): array
    {
        $labels = [];
        foreach (config('admin.modules') as $modules) {
            foreach ($modules as $module => $def) {
                foreach ($def['actions'] as $action => $label) {
                    $labels["{$module}.{$action}"] = "{$def['label']}: {$label}";
                }
            }
        }

        return $labels;
    }

    /** Expand patterns like "*", "users.*" and exclusions "!system.update". */
    public static function expand(array $patterns): array
    {
        $all = collect(self::all());
        $include = collect($patterns)->reject(fn ($p) => str_starts_with($p, '!'));
        $exclude = collect($patterns)->filter(fn ($p) => str_starts_with($p, '!'))->map(fn ($p) => substr($p, 1));

        return $all
            ->filter(fn ($name) => $include->contains(fn ($p) => Str::is($p, $name)))
            ->reject(fn ($name) => $exclude->contains(fn ($p) => Str::is($p, $name)))
            ->values()->all();
    }
}
