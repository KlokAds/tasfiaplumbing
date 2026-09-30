<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/** A file picked from the Media library: "storing" it just returns where it already is. */
class LibraryFile extends UploadedFile
{
    public function __construct(string $fullPath, public readonly string $libraryPath)
    {
        parent::__construct($fullPath, basename($libraryPath), mime_content_type($fullPath) ?: null, null, true);
    }

    public function store($path = '', $options = [])
    {
        return $this->libraryPath;
    }

    public function storeAs($path, $name = null, $options = [])
    {
        return $this->libraryPath;
    }

    public function storePublicly($path = '', $options = [])
    {
        return $this->libraryPath;
    }

    public function storePubliclyAs($path, $name = null, $options = [])
    {
        return $this->libraryPath;
    }
}
