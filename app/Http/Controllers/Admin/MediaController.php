<?php

namespace App\Http\Controllers\Admin;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MediaController extends Controller
{
    private const USAGE_CACHE = 'media.usage';

    public function index(Request $request)
    {
        $fresh = $request->boolean('refresh');
        $files = MediaLibrary::scan($fresh);
        if ($fresh) {
            Cache::forget(self::USAGE_CACHE);
        }
        $usage = Cache::remember(self::USAGE_CACHE, now()->addMinutes(3), fn () => MediaLibrary::usage());
        $meta = Media::whereIn('path', array_keys($files))->get()->keyBy('path');
        $uploaders = \App\Models\User::whereIn('id', $meta->pluck('uploaded_by')->filter()->unique())->pluck('name', 'id');

        $all = collect($files)->map(fn ($info, $path) => [
            'path' => $path,
            'url' => '/' . implode('/', array_map('rawurlencode', explode('/', $path))),
            'name' => basename($path),
            'folder' => dirname($path),
            'ext' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            'size' => $info[0],
            'modified' => date('c', $info[1]),
            'alt' => $meta[$path]->alt ?? null,
            'width' => $meta[$path]->width ?? null,
            'height' => $meta[$path]->height ?? null,
            'usages' => array_slice($usage[$path] ?? [], 0, 8),
            'usage_count' => count($usage[$path] ?? []),
            'legacy' => MediaLibrary::isLegacy($path), // the old website's folders: shown, never deleted here
            'uploaded_by' => isset($meta[$path]) ? ($uploaders[$meta[$path]->uploaded_by] ?? null) : null,
            'uploaded_at' => isset($meta[$path]) ? $meta[$path]->created_at?->toIso8601String() : null,
        ])->values();

        $summary = [
            'total' => $all->count(),
            'total_size' => $all->sum('size'),
            'unused' => $all->where('usage_count', 0)->count(),
            'unused_size' => $all->where('usage_count', 0)->sum('size'),
            'missing' => count(array_diff_key($usage, $files)),
        ];

        $counts = $all->countBy('folder');
        $folders = collect(MediaLibrary::folders())->map(fn ($f) => ['path' => $f, 'name' => basename($f), 'depth' => substr_count($f, '/'), 'count' => $counts[$f] ?? 0])->values();

        $filtered = $all
            ->when($request->input('status') === 'used', fn ($c) => $c->where('usage_count', '>', 0))
            ->when($request->input('status') === 'unused', fn ($c) => $c->where('usage_count', 0))
            ->when($request->filled('folder'), fn ($c) => $c->where('folder', $request->input('folder')))
            ->when($request->filled('search'), fn ($c) => $c->filter(fn ($f) => stripos($f['name'], $request->input('search')) !== false))
            ->sortByDesc(fn ($f) => $request->input('sort') === 'size' ? $f['size'] : $f['modified'])
            ->values();

        $perPage = PerPage::get($request, 50);
        $page = max(1, $request->integer('page', 1));

        return Inertia::render('Admin/Media/Index', [
            'files' => new LengthAwarePaginator($filtered->forPage($page, $perPage)->values(), $filtered->count(), $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]),
            'summary' => $summary,
            'folders' => $folders,
            'filters' => $request->only(['status', 'folder', 'search', 'sort', 'per_page']),
            'uploadFolder' => MediaLibrary::UPLOAD_DIR,
        ]);
    }

    /** JSON list of images for the editor's image picker. */
    public function browse(Request $request)
    {
        $folder = MediaLibrary::cleanFolder($request->input('folder'))
            ?? (in_array((string) $request->input('folder'), MediaLibrary::folders(), true) ? (string) $request->input('folder') : null); // old site folders too
        $files = collect(MediaLibrary::scan())
            ->filter(fn ($info, $path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), MediaLibrary::IMAGE_EXTENSIONS, true))
            ->when($folder, fn ($c) => $c->filter(fn ($i, $path) => dirname($path) === $folder))
            ->when($request->filled('search'), fn ($c) => $c->filter(fn ($i, $path) => stripos(basename($path), $request->input('search')) !== false))
            ->sortByDesc(fn ($info) => $info[1]);

        $page = max(1, $request->integer('page', 1));
        $slice = $files->slice(($page - 1) * 40, 40);
        $meta = Media::whereIn('path', $slice->keys())->pluck('alt', 'path');

        return response()->json([
            'data' => $slice->map(fn ($info, $path) => [
                'path' => $path,
                'url' => '/' . implode('/', array_map('rawurlencode', explode('/', $path))),
                'name' => basename($path),
                'alt' => $meta[$path] ?? null,
            ])->values(),
            'has_more' => $files->count() > $page * 40,
            'folders' => $page === 1 ? MediaLibrary::folders() : null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'files' => 'required|array|max:20',
            'files.*' => 'file|max:8192|mimes:' . implode(',', MediaLibrary::ALLOWED_EXTENSIONS),
            'alt' => 'nullable|string|max:255',
            'folder' => 'nullable|string|max:300',
        ], [
            'files.*.uploaded' => 'The file is larger than this server accepts (' . ini_get('upload_max_filesize') . '). Resize it, or raise upload_max_filesize in php.ini.',
            'files.*.max' => 'Files must be 8 MB or smaller.',
            'files.*.mimes' => 'Only JPG, PNG, WebP, GIF, AVIF and PDF files can be uploaded.',
        ]);

        $stored = [];
        foreach ($request->file('files') as $file) {
            $media = MediaLibrary::store($file, $request->user()->id, $request->input('alt'), $request->input('folder'));
            $stored[] = [
                'path' => $media->path,
                'url' => '/' . implode('/', array_map('rawurlencode', explode('/', $media->path))),
                'alt' => $media->alt,
                'width' => $media->width,
                'height' => $media->height,
            ];
        }
        Cache::forget(self::USAGE_CACHE);

        if ($request->expectsJson()) {
            return response()->json(['files' => $stored]);
        }

        return redirect()->back()->with('success', count($stored) . ' file(s) uploaded.');
    }

    public function updateAlt(Request $request)
    {
        $data = $request->validate([
            'path' => 'required|string|max:512',
            'alt' => 'nullable|string|max:255',
        ]);
        $path = MediaLibrary::normalize($data['path']);
        abort_unless($path && array_key_exists($path, MediaLibrary::scan()), 404);

        Media::updateOrCreate(['path' => $path], ['alt' => $data['alt']]);

        return redirect()->back()->with('success', 'Alt text saved.');
    }

    public function createFolder(Request $request)
    {
        $data = $request->validate(['parent' => 'required|string|max:300', 'name' => 'required|string|max:60']);

        return $this->run(fn () => MediaLibrary::createFolder($data['parent'], $data['name']), fn ($f) => ['success' => "Folder \"" . basename($f) . "\" created.", 'folder' => $f]);
    }

    public function renameFolder(Request $request)
    {
        $data = $request->validate(['path' => 'required|string|max:300', 'name' => 'required|string|max:60']);

        return $this->run(fn () => MediaLibrary::renameFolder($data['path'], $data['name']), fn ($f) => ['success' => 'Folder renamed. Pages using its images were updated.', 'folder' => $f]);
    }

    public function deleteFolder(Request $request)
    {
        $data = $request->validate(['path' => 'required|string|max:300']);

        return $this->run(fn () => MediaLibrary::deleteFolder($data['path']), fn () => ['success' => 'Folder deleted.', 'folder' => dirname(MediaLibrary::cleanFolder($data['path']) ?: 'Admin/x')]);
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'paths' => 'required|array|min:1|max:500',
            'paths.*' => 'string|max:512',
            'to' => 'required|string|max:300',
            'mode' => 'required|in:move,copy',
        ]);
        abort_unless($request->user()->can($data['mode'] === 'move' ? 'media.edit' : 'media.create'), 403);

        return $this->run(fn () => $data['mode'] === 'move' ? MediaLibrary::move($data['paths'], $data['to']) : MediaLibrary::copy($data['paths'], $data['to']),
            fn ($r) => ['success' => ($data['mode'] === 'move' ? "{$r['moved']} file(s) moved" : "{$r['moved']} file(s) copied") . ($data['mode'] === 'move' ? '; pages that use them were updated.' : '.') . ($r['skipped'] ? " {$r['skipped']} skipped." : '')]);
    }

    /** One file, with its real name. */
    public function download(Request $request)
    {
        $path = MediaLibrary::normalize((string) $request->query('path'));
        $full = $path ? realpath(public_path($path)) : false;
        $root = realpath(public_path(MediaLibrary::ROOT));
        abort_unless($full && $root && str_starts_with($full, $root . DIRECTORY_SEPARATOR) && is_file($full), 404);

        return response()->download($full, basename($path));
    }

    /** Several files or a whole folder as one .zip (folders inside keep their structure). */
    public function downloadZip(Request $request)
    {
        $data = $request->validate([
            'paths' => 'nullable|array|max:1000',
            'paths.*' => 'string|max:512',
            'folder' => 'nullable|string|max:300',
        ]);
        if (!class_exists(\ZipArchive::class)) {
            return response()->json(['message' => 'The server cannot create zip files (PHP zip extension missing). Download files one by one, or ask the hosting to enable "zip".'], 422);
        }

        $root = realpath(public_path(MediaLibrary::ROOT));
        $files = [];
        if (!empty($data['folder'])) {
            $dir = MediaLibrary::folderPath($data['folder']) ?: abort(404);
            $base = dirname($dir);
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $files[$f->getPathname()] = str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));
                }
            }
            $name = Str::slug(basename($dir)) ?: 'uploads';
        } else {
            foreach ($data['paths'] ?? [] as $raw) {
                $path = MediaLibrary::normalize($raw);
                $full = $path ? realpath(public_path($path)) : false;
                if ($full && str_starts_with($full, $root . DIRECTORY_SEPARATOR) && is_file($full)) {
                    $files[$full] = basename($path);
                }
            }
            $name = 'media-' . now()->format('Y-m-d');
        }
        if (!$files) {
            return response()->json(['message' => 'Nothing to download.'], 422);
        }
        $size = array_sum(array_map('filesize', array_keys($files)));
        if ($size > 500 * 1024 * 1024) {
            return response()->json(['message' => 'That is more than 500 MB. Download a smaller folder or fewer files at a time.'], 422);
        }

        $tmp = storage_path('app/tmp');
        if (!is_dir($tmp)) {
            mkdir($tmp, 0755, true);
        }
        $zipPath = $tmp . '/' . Str::random(16) . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $used = [];
        foreach ($files as $full => $entry) {
            // Two selected files with the same name: keep both.
            $key = strtolower($entry);
            if (isset($used[$key])) {
                $entry = pathinfo($entry, PATHINFO_FILENAME) . '-' . (++$used[$key]) . '.' . pathinfo($entry, PATHINFO_EXTENSION);
            } else {
                $used[$key] = 1;
            }
            $zip->addFile($full, $entry);
        }
        $zip->close();

        return response()->download($zipPath, $name . '.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    /** Runs a folder/file action and turns friendly errors into a flash message. */
    private function run(callable $action, callable $message)
    {
        try {
            $result = $action();
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
        Cache::forget(self::USAGE_CACHE);
        $m = $message($result);

        return isset($m['folder'])
            ? redirect()->route('admin.media.index', ['folder' => $m['folder']])->with('success', $m['success'])
            : back()->with('success', $m['success']);
    }

    public function destroy(Request $request)
    {
        $paths = $request->validate([
            'paths' => 'required|array|min:1|max:500',
            'paths.*' => 'string|max:512',
        ])['paths'];

        $usage = MediaLibrary::usage();
        $result = ['deleted' => 0, 'in_use' => 0, 'not_found' => 0];
        foreach ($paths as $path) {
            $result[MediaLibrary::delete($path, $usage)]++;
        }
        Cache::forget(self::USAGE_CACHE);

        $message = "{$result['deleted']} file(s) deleted.";
        if ($result['in_use']) {
            $message .= " {$result['in_use']} skipped because they are in use.";
        }

        return redirect()->back()->with('success', $message);
    }
}
