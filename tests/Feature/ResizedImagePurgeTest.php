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
        file_put_contents("$dir/door.jpg.webp", 'x');
        file_put_contents(public_path('cache/img/1280/Admin/purge-test/door.jpg.webp'), 'x');
        file_put_contents("$dir/other.jpg.webp", 'x');
        // The current folder and the newer old one too
        foreach (['cache/im', 'cache/w'] as $d) {
            File::ensureDirectoryExists(public_path("$d/640/Admin/purge-test"));
            file_put_contents(public_path("$d/640/Admin/purge-test/door.jpg.webp"), 'x');
        }

        ImageController::purge('Admin/purge-test/door.jpg');

        $this->assertFileDoesNotExist(public_path('cache/im/640/Admin/purge-test/door.jpg.webp'));
        $this->assertFileDoesNotExist(public_path('cache/w/640/Admin/purge-test/door.jpg.webp'));

        $this->assertFileDoesNotExist("$dir/door.jpg.webp");
        $this->assertFileDoesNotExist(public_path('cache/img/1280/Admin/purge-test/door.jpg.webp'));
        $this->assertFileExists("$dir/other.jpg.webp");

        File::deleteDirectory(public_path('cache/img/640/Admin/purge-test'));
        File::deleteDirectory(public_path('cache/img/1280/Admin/purge-test'));
        File::deleteDirectory(public_path('cache/im/640/Admin/purge-test'));
        File::deleteDirectory(public_path('cache/w/640/Admin/purge-test'));
    }

    public function test_old_links_redirect_to_the_current_folder(): void
    {
        $this->get('/cache/img/640/Admin/x/door.jpg.webp')->assertRedirect('/cache/w/640/Admin/x/door.jpg.webp');
        $this->get('/cache/im/640/Admin/x/door.jpg.webp')->assertRedirect('/cache/w/640/Admin/x/door.jpg.webp');
        $this->get('/cache/img/641/Admin/x/door.jpg.webp')->assertNotFound();
    }

    public function test_purge_ignores_paths_that_try_to_leave_the_cache_folder(): void
    {
        ImageController::purge('../../.env');
        $this->assertFileExists(base_path('.env'));
    }
}
