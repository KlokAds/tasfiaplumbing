<?php

namespace Tests\Feature;

use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ResizedImagePurgeTest extends TestCase
{
    public function test_purge_removes_every_resized_copy_of_one_image_only(): void
    {
        $dir = public_path('cache/img/640/Admin/purge-test');
        File::ensureDirectoryExists($dir);
        File::ensureDirectoryExists(public_path('cache/img/1280/Admin/purge-test'));
        file_put_contents("$dir/tap.jpg.webp", 'x');
        file_put_contents(public_path('cache/img/1280/Admin/purge-test/tap.jpg.webp'), 'x');
        file_put_contents("$dir/other.jpg.webp", 'x');

        ImageController::purge('Admin/purge-test/tap.jpg');

        $this->assertFileDoesNotExist("$dir/tap.jpg.webp");
        $this->assertFileDoesNotExist(public_path('cache/img/1280/Admin/purge-test/tap.jpg.webp'));
        $this->assertFileExists("$dir/other.jpg.webp");

        File::deleteDirectory(public_path('cache/img/640/Admin/purge-test'));
        File::deleteDirectory(public_path('cache/img/1280/Admin/purge-test'));
    }

    public function test_purge_ignores_paths_that_try_to_leave_the_cache_folder(): void
    {
        ImageController::purge('../../.env');
        $this->assertFileExists(base_path('.env'));
    }
}
