<?php

namespace Tests\Feature;

use App\Models\BlogDetail;
use App\Models\ServiceDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private string $public;

    protected function setUp(): void
    {
        parent::setUp();
        $this->public = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tasfia-media-' . uniqid();
        File::ensureDirectoryExists($this->public . '/Admin/Blog');
        $this->app->usePublicPath($this->public);
        $this->actingAs(User::factory()->create()->assignRole('super-admin'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->public);
        parent::tearDown();
    }

    private function file(string $path): string
    {
        file_put_contents($this->public . '/' . $path, 'x');
        return $path;
    }

    public function test_files_used_by_a_column_or_inside_html_cannot_be_deleted(): void
    {
        $this->file('Admin/Blog/cover photo.jpg');
        $this->file('Admin/Blog/inline.jpg');
        $this->file('Admin/Blog/orphan.jpg');

        ServiceDetail::create(['name' => 'Tiling', 'desc' => '<p>x</p><img src="/Admin/Blog/inline.jpg" alt="a">']);
        BlogDetail::create(['name' => 'Guide', 'desc' => 'y', 'image' => 'Admin/Blog/cover photo.jpg']);

        $this->post('/admin/media/delete', ['paths' => ['Admin/Blog/cover photo.jpg', 'Admin/Blog/inline.jpg', 'Admin/Blog/orphan.jpg']])
            ->assertRedirect();

        $this->assertFileExists($this->public . '/Admin/Blog/cover photo.jpg');
        $this->assertFileExists($this->public . '/Admin/Blog/inline.jpg');
        $this->assertFileDoesNotExist($this->public . '/Admin/Blog/orphan.jpg');
    }

    public function test_paths_outside_the_upload_folder_are_never_deleted(): void
    {
        file_put_contents($this->public . '/logo.png', 'x');

        $this->post('/admin/media/delete', ['paths' => ['Admin/../logo.png', '../logo.png', '/logo.png']])->assertRedirect();

        $this->assertFileExists($this->public . '/logo.png');
    }

    public function test_upload_returns_json_for_the_editor(): void
    {
        $this->postJson('/admin/media', ['files' => [UploadedFile::fake()->image('Kitchen Tiles.jpg', 800, 600)], 'alt' => 'Kitchen tiles'])
            ->assertOk()
            ->assertJsonPath('files.0.width', 800)
            ->assertJsonPath('files.0.alt', 'Kitchen tiles');

        $this->assertDatabaseHas('media', ['alt' => 'Kitchen tiles']);
    }

    public function test_svg_uploads_are_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"></svg>');
        $this->postJson('/admin/media', ['files' => [$svg]])->assertStatus(422);
    }
}
