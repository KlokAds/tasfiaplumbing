<?php

namespace Tests\Feature;

use App\Models\BlogDetail;
use App\Models\User;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MediaFoldersTest extends TestCase
{
    use RefreshDatabase;

    private string $base = 'Admin/__test_fm';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        File::deleteDirectory(public_path($this->base));
        File::ensureDirectoryExists(public_path($this->base . '/Old'));
        file_put_contents(public_path($this->base . '/Old/photo one.jpg'), 'x');
        MediaLibrary::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path($this->base));
        MediaLibrary::flush();
        parent::tearDown();
    }

    private function owner(): User
    {
        return User::factory()->create()->assignRole('super-admin');
    }

    public function test_create_rename_and_delete_folders(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post('/admin/media/folders', ['parent' => $this->base, 'name' => 'Bathroom jobs'])->assertSessionHas('success');
        $this->assertDirectoryExists(public_path($this->base . '/Bathroom jobs'));

        // Path tricks are stripped: the folder can only ever be created inside the parent.
        $this->post('/admin/media/folders', ['parent' => $this->base, 'name' => '../../evil']);
        $this->assertDirectoryExists(public_path($this->base . '/evil'));
        $this->assertDirectoryDoesNotExist(base_path('evil'));
        $this->post('/admin/media/folders', ['parent' => 'resources', 'name' => 'x'])->assertSessionHas('error');

        $this->post('/admin/media/folders/rename', ['path' => $this->base . '/Bathroom jobs', 'name' => 'Bathrooms'])->assertSessionHas('success');
        $this->assertDirectoryExists(public_path($this->base . '/Bathrooms'));

        $this->post('/admin/media/folders/delete', ['path' => $this->base . '/Old'])->assertSessionHas('error'); // not empty
        $this->post('/admin/media/folders/delete', ['path' => $this->base . '/Bathrooms'])->assertSessionHas('success');
        $this->assertDirectoryDoesNotExist(public_path($this->base . '/Bathrooms'));
        $this->post('/admin/media/folders/delete', ['path' => 'Admin'])->assertSessionHas('error');
    }

    public function test_moving_an_image_updates_the_pages_that_use_it(): void
    {
        $old = $this->base . '/Old/photo one.jpg';
        $article = BlogDetail::create([
            'name' => 'Tiles guide', 'status' => BlogDetail::PUBLISHED,
            'image' => $old,
            'desc' => '<p>See <img src="/' . str_replace(' ', '%20', $old) . '" alt="x"></p>',
        ]);
        File::ensureDirectoryExists(public_path($this->base . '/New'));

        $this->actingAs($this->owner())->post('/admin/media/transfer', ['paths' => [$old], 'to' => $this->base . '/New', 'mode' => 'move'])->assertSessionHas('success');

        $new = $this->base . '/New/photo one.jpg';
        $this->assertFileExists(public_path($new));
        $this->assertFileDoesNotExist(public_path($old));
        $article->refresh();
        $this->assertSame($new, $article->image);
        $this->assertStringContainsString('/' . str_replace(' ', '%20', $new), $article->desc);

        // Renaming the folder keeps the link working too
        $this->post('/admin/media/folders/rename', ['path' => $this->base . '/New', 'name' => 'Tiles']);
        $this->assertSame($this->base . '/Tiles/photo one.jpg', $article->fresh()->image);
    }

    public function test_copy_leaves_the_original(): void
    {
        File::ensureDirectoryExists(public_path($this->base . '/Copies'));
        $this->actingAs($this->owner())->post('/admin/media/transfer', ['paths' => [$this->base . '/Old/photo one.jpg'], 'to' => $this->base . '/Copies', 'mode' => 'copy']);

        $this->assertFileExists(public_path($this->base . '/Old/photo one.jpg'));
        $this->assertFileExists(public_path($this->base . '/Copies/photo one.jpg'));
    }

    public function test_writers_cannot_move_files(): void
    {
        $writer = User::factory()->create()->assignRole('writer'); // media.view + media.create, no media.edit
        $this->actingAs($writer)->post('/admin/media/transfer', ['paths' => [$this->base . '/Old/photo one.jpg'], 'to' => $this->base, 'mode' => 'move'])->assertForbidden();
    }
}
