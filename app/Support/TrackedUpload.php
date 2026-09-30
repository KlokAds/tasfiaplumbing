<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Http\UploadedFile;

/**
 * A normal upload from any admin form (service photo, article image, profile photo, logo…).
 * When it is stored, the Media library remembers who uploaded it, so every file shows its uploader.
 */
class TrackedUpload extends UploadedFile
{
    private ?int $uploaderId = null;

    public static function wrap(UploadedFile $file, ?int $userId): self
    {
        // Keep the "test" flag of the original, so fake uploads in tests stay valid.
        $test = \Closure::bind(fn ($f) => $f->test, null, \Symfony\Component\HttpFoundation\File\UploadedFile::class)($file);
        $copy = new self($file->getPathname(), $file->getClientOriginalName(), $file->getClientMimeType(), $file->getError(), $test);
        $copy->uploaderId = $userId;

        return $copy;
    }

    public function storeAs($path, $name = null, $options = [])
    {
        $stored = parent::storeAs($path, $name, $options);
        $disk = is_array($options) ? ($options['disk'] ?? null) : $options;

        if ($stored && $disk === 'uploads') {
            $this->remember($stored);
        }

        return $stored;
    }

    private function remember(string $stored): void
    {
        try {
            $path = MediaLibrary::normalize($stored);
            if (!$path) {
                return;
            }
            $full = public_path($path);
            $dims = @getimagesize($full);
            Media::updateOrCreate(['path' => $path], [
                'original_name' => $this->getClientOriginalName(),
                'mime' => $this->getClientMimeType(),
                'size' => @filesize($full) ?: null,
                'width' => $dims[0] ?? null,
                'height' => $dims[1] ?? null,
                'uploaded_by' => $this->uploaderId,
            ]);
            MediaLibrary::flush();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
