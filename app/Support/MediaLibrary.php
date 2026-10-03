<?php

namespace App\Support;

use App\Models\Media;
use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Files live under public/Admin. A file is "in use" when any image column or any
 * HTML body in the database points at it; those files can never be deleted here.
 */
class MediaLibrary
{
    public const ROOT = 'Admin';
    public const UPLOAD_DIR = 'Admin/Media';
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'pdf'];
    // Legacy SVGs are listed and protected, but new SVG uploads are refused (they can carry scripts).
    public const LISTED_EXTENSIONS = [...self::ALLOWED_EXTENSIONS, 'svg'];

    /**
     * Folders the old websites used (frontend/…, images/…). Their pictures are listed, can be picked
     * and their use is tracked, but they are never deleted, moved or renamed here (only inside ROOT).
     */
    public const LEGACY_ROOTS = ['frontend', 'images', 'Images'];

    private const SCAN_CACHE = 'media.scan';

    /**
     * table => [label, edit url (id appended when it ends with "="), title column, image columns, html columns]
     */
    private const SOURCES = [
        'service_details' => ['Service', '/admin/services?edit=', 'name', ['image', 'bef_img', 'aft_img', 'og_image'], ['desc', 'short_summary']],
        'blog_details' => ['Article', '/admin/blogs?edit=', 'name', ['image', 'og_image'], ['desc', 'excerpt']],
        'service_categories' => ['Category', '/admin/service-categories?edit=', 'name', ['image'], ['intro', 'description']],
        'locations' => ['Location', '/admin/locations?edit=', 'name', ['image'], ['intro', 'description']],
        'project_details' => ['Project', '/admin/projects', 'name', ['image'], []],
        'home_heroes' => ['Hero slide', '/admin/hero', 'title', ['img'], ['short_desc']],
        'home_services' => ['Home service card', '/admin/home-static', 'name', ['img'], ['short_desc']],
        'home_statics' => ['Homepage section', '/admin/home-static', null, ['h_test_img'], []],
        'about_contents' => ['About page', '/admin/about', 'title', ['img_one', 'img_two', 'a_bread_img'], ['short_desc', 'subtitle']],
        'bread_cumbs' => ['Page header', '/admin/breadcrumbs', null, ['s_bread_image', 'p_bread_image', 'f_bread_image', 'b_bread_image'], []],
        'contact_contents' => ['Contact page', '/admin/settings/contact', null, ['c_bread_img'], []],
        'feed_back_contents' => ['Review', '/admin/reviews', 'name', ['img'], []],
        'partners' => ['Partner logo', '/admin/partners', null, ['image'], []],
        'footers' => ['Logo / footer', '/admin/settings/footer', null, ['main_logo', 'f_logo'], ['f_short_desc']],
        'faqs' => ['FAQ', '/admin/faqs', 'question', [], ['answer']],
        'users' => ['Profile photo', '/admin/users', 'name', ['image'], []],
    ];

    /** All files under public/Admin: [path => [size, modified]] */
    public static function scan(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::SCAN_CACHE);
        }

        return Cache::remember(self::SCAN_CACHE, now()->addMinutes(5), function () {
            $files = [];
            foreach (self::roots() as $name) {
                $root = public_path($name);
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $file) {
                    if (!$file->isFile() || !in_array(strtolower($file->getExtension()), self::LISTED_EXTENSIONS, true)) {
                        continue;
                    }
                    $relative = $name . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                    $files[$relative] = [$file->getSize(), $file->getMTime()];
                }
            }

            return $files;
        });
    }

    /** ROOT plus the old websites' folders that exist here (each real folder once). @return list<string> */
    public static function roots(): array
    {
        $out = [];
        foreach ([self::ROOT, ...self::LEGACY_ROOTS] as $name) {
            $real = realpath(public_path($name));
            if ($real && is_dir($real) && !isset($out[$real])) {
                $out[$real] = $name;
            }
        }

        return array_values($out);
    }

    /** A file in one of the old websites' folders: shown, never changed here. */
    public static function isLegacy(string $path): bool
    {
        return !str_starts_with($path, self::ROOT . '/');
    }

    /** path => list of usages. Always computed fresh; callers cache if needed. */
    public static function usage(): array
    {
        $map = [];
        $add = function (?string $raw, array $usage) use (&$map) {
            $path = self::normalize($raw);
            if ($path) {
                $map[$path][] = $usage;
            }
        };

        foreach (self::SOURCES as $table => [$label, $url, $titleCol, $imageCols, $htmlCols]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $columns = array_values(array_filter(
                array_merge(['id', $titleCol], $imageCols, $htmlCols),
                fn ($c) => $c && Schema::hasColumn($table, $c)
            ));

            DB::table($table)->select($columns)->orderBy('id')->chunk(300, function ($rows) use ($label, $url, $titleCol, $imageCols, $htmlCols, $add) {
                foreach ($rows as $row) {
                    $usage = [
                        'label' => $label,
                        'title' => $titleCol ? Str::limit(strip_tags((string) ($row->{$titleCol} ?? '')), 70) : $label,
                        'url' => str_ends_with($url, '=') ? $url . $row->id : $url,
                    ];
                    foreach ($imageCols as $col) {
                        $add($row->{$col} ?? null, $usage + ['field' => $col]);
                    }
                    foreach ($htmlCols as $col) {
                        foreach (self::pathsInHtml($row->{$col} ?? null) as $p) {
                            $add($p, $usage + ['field' => $col . ' (in text)']);
                        }
                    }
                }
            });
        }

        try {
            $add(SiteSetting::get('seo.default_og_image'), ['label' => 'Settings', 'title' => 'Default share image', 'url' => '/admin/seo/settings', 'field' => 'seo.default_og_image']);
        } catch (\Throwable $e) {
        }

        return $map;
    }

    public static function isInUse(string $path, ?array $usage = null): bool
    {
        $usage ??= self::usage();

        return !empty($usage[self::normalize($path)]);
    }

    public static function store(UploadedFile $file, ?int $userId = null, ?string $alt = null, ?string $folder = null): Media
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $dir = ($folder && self::folderPath($folder)) ? self::cleanFolder($folder) : self::UPLOAD_DIR . '/' . now()->format('Y/m');
        $name = Str::limit($base, 80, '') . '-' . Str::lower(Str::random(5)) . '.' . $ext;

        $file->move(public_path($dir), $name);
        $path = $dir . '/' . $name;
        $full = public_path($path);
        $dims = in_array($ext, self::IMAGE_EXTENSIONS, true) ? @getimagesize($full) : false;

        self::flush();

        return Media::updateOrCreate(['path' => $path], [
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => filesize($full) ?: null,
            'width' => $dims[0] ?? null,
            'height' => $dims[1] ?? null,
            'alt' => $alt,
            'uploaded_by' => $userId,
        ]);
    }

    /** Deletes a file only if it is inside public/Admin and nothing references it. */
    public static function delete(string $path, ?array $usage = null): string
    {
        $path = self::normalize($path);
        $full = $path ? realpath(public_path($path)) : false;
        $root = realpath(public_path(self::ROOT));

        if (!$full || !$root || !str_starts_with($full, $root . DIRECTORY_SEPARATOR) || !is_file($full)) {
            return 'not_found';
        }
        if (self::isInUse($path, $usage)) {
            return 'in_use';
        }

        @unlink($full);
        \App\Http\Controllers\ImageController::purge($path);
        Media::where('path', $path)->delete();
        self::flush();

        return 'deleted';
    }

    // ------------------------------------------------------------------ folders

    /** Every folder under public/Admin (including empty ones), e.g. ["Admin", "Admin/Blog", …]. */
    public static function folders(): array
    {
        return Cache::remember(self::SCAN_CACHE . '.folders', now()->addMinutes(5), function () {
            $out = [];
            foreach (self::roots() as $name) {
                $root = public_path($name);
                $out[] = $name;
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
                foreach ($it as $f) {
                    if ($f->isDir()) {
                        $out[] = $name . '/' . str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
                    }
                }
            }
            sort($out, SORT_NATURAL | SORT_FLAG_CASE);

            return $out;
        });
    }

    public static function flush(): void
    {
        Cache::forget(self::SCAN_CACHE);
        Cache::forget(self::SCAN_CACHE . '.folders');
    }

    /** "Admin/Blog/" → "Admin/Blog"; anything outside public/Admin → null. */
    public static function cleanFolder(?string $folder): ?string
    {
        $folder = trim(str_replace('\\', '/', (string) $folder), '/');
        if ($folder === '' || str_contains($folder, '..') || !($folder === self::ROOT || str_starts_with($folder, self::ROOT . '/'))) {
            return null;
        }

        return $folder;
    }

    /** Absolute path of an existing folder inside public/Admin, or null. */
    public static function folderPath(?string $folder): ?string
    {
        $folder = self::cleanFolder($folder);
        $full = $folder ? realpath(public_path($folder)) : false;
        $root = realpath(public_path(self::ROOT));

        return ($full && $root && is_dir($full) && ($full === $root || str_starts_with($full, $root . DIRECTORY_SEPARATOR))) ? $full : null;
    }

    /** Folder names: letters, numbers, spaces, - and _ only. */
    public static function cleanName(string $name): ?string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $name = preg_replace('/[^A-Za-z0-9 _-]/', '', $name);

        return $name !== '' && mb_strlen($name) <= 60 ? $name : null;
    }

    public static function createFolder(string $parent, string $name): string
    {
        $base = self::folderPath($parent) ?: throw new \InvalidArgumentException('That folder does not exist.');
        $name = self::cleanName($name) ?: throw new \InvalidArgumentException('Use letters, numbers, spaces, - or _ (60 characters max).');
        if (file_exists($base . DIRECTORY_SEPARATOR . $name)) {
            throw new \InvalidArgumentException("A folder called \"{$name}\" already exists here.");
        }
        mkdir($base . DIRECTORY_SEPARATOR . $name, 0755);
        self::flush();

        return self::cleanFolder($parent) . '/' . $name;
    }

    /** Rename a folder; every page that uses a file inside it is updated to the new address. */
    public static function renameFolder(string $folder, string $name): string
    {
        $folder = self::cleanFolder($folder);
        $full = self::folderPath($folder);
        if (!$full || $folder === self::ROOT) {
            throw new \InvalidArgumentException('This folder cannot be renamed.');
        }
        $name = self::cleanName($name) ?: throw new \InvalidArgumentException('Use letters, numbers, spaces, - or _ (60 characters max).');
        $target = dirname($folder) . '/' . $name;
        if ($target === $folder) {
            return $folder;
        }
        if (file_exists(public_path($target)) && strcasecmp($target, $folder) !== 0) {
            throw new \InvalidArgumentException("A folder called \"{$name}\" already exists here.");
        }
        rename($full, public_path($target));
        self::rewrite($folder . '/', $target . '/');
        Media::where('path', 'like', $folder . '/%')->get()->each(fn ($m) => $m->update(['path' => $target . substr($m->path, strlen($folder))]));
        self::flush();

        return $target;
    }

    public static function deleteFolder(string $folder): void
    {
        $folder = self::cleanFolder($folder);
        $full = self::folderPath($folder);
        if (!$full || $folder === self::ROOT) {
            throw new \InvalidArgumentException('This folder cannot be deleted.');
        }
        if ((new \FilesystemIterator($full))->valid()) {
            throw new \InvalidArgumentException('Only empty folders can be deleted. Move or delete the files inside first.');
        }
        rmdir($full);
        self::flush();
    }

    /**
     * Move files to another folder. Pages that use them are updated, so nothing breaks.
     *
     * @return array{moved: int, skipped: int}
     */
    public static function move(array $paths, string $to): array
    {
        return self::transfer($paths, $to, false);
    }

    /** Copy files to another folder (the copies are new files, nothing else changes). */
    public static function copy(array $paths, string $to): array
    {
        return self::transfer($paths, $to, true);
    }

    private static function transfer(array $paths, string $to, bool $copy): array
    {
        $dest = self::folderPath($to) ?: throw new \InvalidArgumentException('Choose a folder that exists.');
        $to = self::cleanFolder($to);
        $done = 0;
        $skipped = 0;
        foreach ($paths as $raw) {
            $path = self::normalize($raw);
            $full = $path ? realpath(public_path($path)) : false;
            if (!$full || !is_file($full) || !str_starts_with($full, realpath(public_path(self::ROOT)) . DIRECTORY_SEPARATOR) || dirname($path) === $to && !$copy) {
                $skipped++;
                continue;
            }
            $name = basename($path);
            $target = $to . '/' . $name;
            if (file_exists($dest . DIRECTORY_SEPARATOR . $name)) {
                $info = pathinfo($name);
                $target = $to . '/' . $info['filename'] . '-' . Str::lower(Str::random(4)) . (isset($info['extension']) ? '.' . $info['extension'] : '');
            }
            if ($copy) {
                copy($full, public_path($target));
                $meta = Media::where('path', $path)->first();
                if ($meta) {
                    Media::updateOrCreate(['path' => $target], collect($meta->toArray())->only(['original_name', 'mime', 'size', 'width', 'height', 'alt', 'uploaded_by'])->all());
                }
            } else {
                rename($full, public_path($target));
                \App\Http\Controllers\ImageController::purge($path);
                self::rewrite($path, $target);
                Media::where('path', $path)->update(['path' => $target]);
            }
            $done++;
        }
        self::flush();

        return ['moved' => $done, 'skipped' => $skipped];
    }

    /**
     * Point every database reference from one file (or folder, when both end with "/") to
     * another: image columns, images inside page text and the default share image.
     */
    public static function rewrite(string $from, string $to): int
    {
        $isFolder = str_ends_with($from, '/');
        $enc = fn ($p) => implode('/', array_map('rawurlencode', explode('/', $p)));
        $pairs = array_unique([$from => $to, $enc($from) => $enc($to)]);
        $swap = function (?string $value) use ($pairs, $isFolder, $from) {
            if ($value === null || $value === '') {
                return $value;
            }

            return str_replace(array_keys($pairs), array_values($pairs), $value);
        };
        $changed = 0;

        foreach (self::SOURCES as $table => [, , , $imageCols, $htmlCols]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $cols = array_values(array_filter(array_merge($imageCols, $htmlCols), fn ($c) => Schema::hasColumn($table, $c)));
            if (!$cols) {
                continue;
            }
            DB::table($table)->select(array_merge(['id'], $cols))
                ->where(function ($q) use ($cols, $pairs) {
                    foreach ($cols as $c) {
                        foreach (array_keys($pairs) as $needle) {
                            $q->orWhere($c, 'like', '%' . $needle . '%');
                        }
                    }
                })
                ->orderBy('id')->chunkById(200, function ($rows) use ($table, $cols, $imageCols, $swap, $from, $isFolder, &$changed) {
                    foreach ($rows as $row) {
                        $update = [];
                        foreach ($cols as $c) {
                            $old = $row->{$c};
                            if (in_array($c, $imageCols, true)) {
                                // An image column holds exactly one path ("Admin/x.jpg" or "/Admin/x.jpg").
                                $path = self::normalize($old);
                                $hit = $path && ($isFolder ? str_starts_with($path, $from) : $path === $from);
                                $new = $hit ? $swap($old) : $old;
                            } else {
                                $new = $swap($old);
                            }
                            if ($new !== $old) {
                                $update[$c] = $new;
                            }
                        }
                        if ($update) {
                            DB::table($table)->where('id', $row->id)->update($update);
                            $changed++;
                        }
                    }
                });
        }

        try {
            $og = SiteSetting::get('seo.default_og_image');
            $path = self::normalize($og);
            if ($path && ($isFolder ? str_starts_with($path, $from) : $path === $from)) {
                SiteSetting::putMany(['seo.default_og_image' => $swap($og)]);
            }
        } catch (\Throwable $e) {
        }

        return $changed;
    }

    /** "/Admin/x%20y.jpg", "https://site/Admin/x y.jpg" and "Admin/x y.jpg" all become "Admin/x y.jpg". */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);
        if (preg_match('#^https?://#i', $raw)) {
            $host = parse_url($raw, PHP_URL_HOST);
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);
            if ($host && $appHost && strcasecmp($host, $appHost) !== 0 && !str_contains($raw, '/' . self::ROOT . '/')) {
                return null;
            }
            $raw = (string) parse_url($raw, PHP_URL_PATH);
        }
        $raw = rawurldecode(strtok($raw, '?#'));
        $raw = ltrim(str_replace('\\', '/', $raw), '/');

        foreach ([self::ROOT, ...self::LEGACY_ROOTS] as $root) {
            if (str_starts_with($raw, $root . '/')) {
                return $raw;
            }
        }

        return null;
    }

    private static function pathsInHtml(?string $html): array
    {
        if (!$html || !preg_match('#(' . implode('|', array_map('preg_quote', [self::ROOT, ...self::LEGACY_ROOTS])) . ')/#', $html)) {
            return [];
        }
        preg_match_all('#(?:src|href|srcset|data-src)\s*=\s*["\']([^"\']+)["\']#i', $html, $m);
        $paths = [];
        foreach ($m[1] as $value) {
            foreach (preg_split('/\s*,\s*/', $value) as $candidate) {
                $paths[] = explode(' ', trim($candidate))[0];
            }
        }
        preg_match_all('#url\(\s*["\']?([^"\')]+)#i', $html, $css);

        return array_merge($paths, $css[1]);
    }
}
