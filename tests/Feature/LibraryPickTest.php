<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LibraryPickTest extends TestCase
{
    use RefreshDatabase;

    private string $path = 'Admin/__test_lib/logo-one.png';

    protected function setUp(): void
    {
        parent::setUp();
        File::ensureDirectoryExists(public_path('Admin/__test_lib'));
        // A real 1×1 PNG so the "image" validation passes.
        file_put_contents(public_path($this->path), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('Admin/__test_lib'));
        parent::tearDown();
    }

    private function placeholder(string $path): UploadedFile
    {
        $code = rtrim(strtr(base64_encode($path), '+/', '-_'), '=');

        return UploadedFile::fake()->createWithContent("library--{$code}.png", "\0");
    }

    public function test_choosing_from_the_library_reuses_the_file(): void
    {
        $owner = User::factory()->create()->assignRole('super-admin');
        $before = is_dir(public_path('Admin/Partner')) ? count(File::allFiles(public_path('Admin/Partner'))) : 0;

        $this->actingAs($owner)->post('/admin/partners', ['image' => $this->placeholder($this->path)])->assertSessionHasNoErrors();

        $this->assertSame($this->path, Partner::latest('id')->value('image'));
        $after = is_dir(public_path('Admin/Partner')) ? count(File::allFiles(public_path('Admin/Partner'))) : 0;
        $this->assertSame($before, $after, 'No copy is made');
    }

    public function test_paths_outside_the_library_are_ignored(): void
    {
        $owner = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($owner)->post('/admin/partners', ['image' => $this->placeholder('../.env')])->assertSessionHasErrors('image');
        $this->assertSame(0, Partner::count());
    }
}
