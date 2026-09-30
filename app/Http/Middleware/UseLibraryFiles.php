<?php

namespace App\Http\Middleware;

use App\Support\LibraryFile;
use App\Support\MediaLibrary;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Choose from Media library" on any image field: the browser sends a tiny placeholder file
 * named "library--<base64 path>.<ext>". It is swapped for the real library file here, and
 * that file's store() simply returns its existing path, so no copy is made and every
 * controller keeps working unchanged.
 */
class UseLibraryFiles
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->files->count()) {
            foreach ($request->files->all() as $key => $file) {
                if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                    continue;
                }
                if (!preg_match('/^library--([A-Za-z0-9_-]+)\.\w+$/', $file->getClientOriginalName(), $m)) {
                    continue;
                }
                $path = MediaLibrary::normalize(base64_decode(strtr($m[1], '-_', '+/')));
                $full = $path ? realpath(public_path($path)) : false;
                $root = realpath(public_path(MediaLibrary::ROOT));
                $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
                if (!$full || !$root || !str_starts_with($full, $root . DIRECTORY_SEPARATOR) || !is_file($full) || !in_array($ext, MediaLibrary::LISTED_EXTENSIONS, true)) {
                    $request->files->remove($key); // not a library file: ignore it
                    continue;
                }
                $request->files->set($key, new LibraryFile($full, $path));
            }
            // Laravel caches the converted file list; make it read the swapped files.
            (fn () => $this->convertedFiles = null)->call($request);
        }

        return $next($request);
    }
}
