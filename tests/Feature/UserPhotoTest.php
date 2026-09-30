<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UserPhotoTest extends TestCase
{
    public function test_a_missing_photo_file_is_never_sent_to_the_page(): void
    {
        $this->assertNull((new User(['image' => 'profile-that-does-not-exist.jpg']))->photo());
        $this->assertNull((new User(['image' => null]))->photo());
        $this->assertNull((new User(['image' => '../.env']))->photo());
    }

    public function test_an_existing_photo_is_kept(): void
    {
        File::ensureDirectoryExists(public_path('Admin/Users/photo-test'));
        file_put_contents(public_path('Admin/Users/photo-test/me.jpg'), 'x');

        $this->assertSame('Admin/Users/photo-test/me.jpg', (new User(['image' => '/Admin/Users/photo-test/me.jpg']))->photo());

        File::deleteDirectory(public_path('Admin/Users/photo-test'));
    }
}
