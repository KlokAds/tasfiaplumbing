<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Every upload shows who uploaded it, and profile photos count as "in use". */
class MediaUploaderTest extends TestCase
{
    use RefreshDatabase;

    private ?string $stored = null;

    protected function tearDown(): void
    {
        if ($this->stored) {
            @unlink(public_path($this->stored));
            Media::where('path', $this->stored)->delete();
        }
        parent::tearDown();
    }

    public function test_a_profile_photo_upload_records_the_uploader_and_counts_as_used(): void
    {
        $owner = User::factory()->create(['name' => 'Anowar'])->assignRole('super-admin');

        $this->actingAs($owner)->post('/admin/account/profile', [
            'name' => 'Anowar', 'email' => $owner->email,
            'image' => UploadedFile::fake()->image('me.jpg', 200, 200),
        ])->assertSessionHasNoErrors();

        $this->stored = $owner->fresh()->image;
        $this->assertNotNull($this->stored);
        $this->assertSame($owner->id, Media::where('path', $this->stored)->value('uploaded_by'));

        $this->actingAs($owner)->get('/admin/media?refresh=1&per_page=500')->assertOk()
            ->assertInertia(fn ($page) => $page->where('files.data', fn ($files) => collect($files)->contains(
                fn ($f) => $f['path'] === $this->stored && $f['uploaded_by'] === 'Anowar' && $f['usage_count'] === 1 && $f['usages'][0]['label'] === 'Profile photo'
            )));
    }
}
