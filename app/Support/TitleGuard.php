<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Keeps page titles and meta titles unique across the site. Two pages with the same
 * title compete for the same search (keyword cannibalisation) and Google may show
 * neither, so a clash blocks saving and names the page it clashes with.
 */
class TitleGuard
{
    /** type => [table, public path prefix, admin edit url pattern] */
    private const SOURCES = [
        'article' => ['blog_details', '/blogs/', '/admin/blogs?edit={id}'],
        'service' => ['service_details', '/service/', '/admin/services?edit={id}'],
        'location' => ['locations', '/locations/', '/admin/locations?edit={id}'],
    ];

    public static function normalize(?string $text): string
    {
        return Str::of((string) $text)->squish()->lower()->toString();
    }

    /**
     * First page whose title or meta title equals $text (ignoring case and extra spaces).
     *
     * @return array{type: string, id: int, name: string, field: string, edit_url: string}|null
     */
    public static function conflict(?string $text, string $type, ?int $ignoreId = null): ?array
    {
        $needle = self::normalize($text);
        if ($needle === '') {
            return null;
        }

        foreach (self::SOURCES as $sourceType => [$table, , $editUrl]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach (['name' => 'title', 'meta_title' => 'meta title'] as $column => $field) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }
                $row = DB::table($table)
                    ->select('id', 'name')
                    ->whereRaw('LOWER(TRIM(' . $column . ')) = ?', [$needle])
                    ->when($sourceType === $type && $ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                    ->first();
                if ($row) {
                    return [
                        'type' => $sourceType,
                        'id' => $row->id,
                        'name' => $row->name,
                        'field' => $field,
                        'edit_url' => str_replace('{id}', $row->id, $editUrl),
                    ];
                }
            }
        }

        if (Schema::hasTable('page_seo')) {
            $page = DB::table('page_seo')->whereRaw('LOWER(TRIM(meta_title)) = ?', [$needle])->first();
            if ($page) {
                return [
                    'type' => 'page',
                    'id' => $page->id,
                    'name' => config("seo.static_pages.{$page->key}.label", $page->key),
                    'field' => 'meta title',
                    'edit_url' => '/admin/page-seo',
                ];
            }
        }

        return null;
    }

    public static function message(array $c, string $what): string
    {
        $kind = ['article' => 'article', 'service' => 'service page', 'location' => 'location page', 'page' => 'page'][$c['type']] ?? $c['type'];

        return "This {$what} is already the {$c['field']} of the {$kind} “{$c['name']}”. Every page needs its own title so they do not compete in Google. Make it more specific (add the area, year, price or problem).";
    }

    /** Validation rule closure for a title field. */
    public static function rule(string $type, ?int $ignoreId, string $what): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($type, $ignoreId, $what) {
            if ($c = self::conflict(is_string($value) ? $value : null, $type, $ignoreId)) {
                $fail(self::message($c, $what));
            }
        };
    }
}
