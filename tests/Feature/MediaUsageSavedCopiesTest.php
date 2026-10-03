<?php

namespace Tests\Feature;

use App\Models\ArticleRevision;
use App\Models\ArticleVersion;
use App\Models\BlogDetail;
use App\Models\Draft;
use App\Models\User;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A picture used only in a change waiting for approval, an autosaved draft or the article history is "in use". */
class MediaUsageSavedCopiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pictures_in_saved_copies_are_in_use_and_cannot_be_deleted(): void
    {
        $writer = User::factory()->create(['is_active' => true])->assignRole('writer');
        $blog = BlogDetail::create(['name' => 'Floor Spring Repair', 'slug' => 'floor-spring-repair', 'desc' => '<p>Live text</p>', 'status' => BlogDetail::PUBLISHED]);

        ArticleRevision::create(['article_id' => $blog->id, 'user_id' => $writer->id, 'status' => 'pending',
            'payload' => ['name' => 'Floor Spring Repair', 'desc' => '<p>New</p><img src="/Admin/Media/2026/10/review-card.webp" alt="">', 'faqs' => []]]);
        Draft::create(['user_id' => $writer->id, 'type' => 'article', 'record_id' => 0, 'payload' => ['image_path' => 'Admin/Media/2026/10/draft-photo.jpg', 'desc' => '']]);
        ArticleVersion::create(['article_id' => $blog->id, 'user_id' => $writer->id, 'event' => 'edit', 'payload' => ['desc' => '<p><img src="/Admin/Blog/Details/old-photo.jpg"></p>']]);
        ArticleRevision::create(['article_id' => $blog->id, 'user_id' => $writer->id, 'status' => 'rejected',
            'payload' => ['desc' => '<img src="/Admin/Media/2026/10/rejected-only.webp">']]);

        $usage = MediaLibrary::usage();
        $this->assertSame('Change waiting for approval', $usage['Admin/Media/2026/10/review-card.webp'][0]['label']);
        $this->assertSame('Floor Spring Repair', $usage['Admin/Media/2026/10/review-card.webp'][0]['title']);
        $this->assertSame('Autosaved draft', $usage['Admin/Media/2026/10/draft-photo.jpg'][0]['label']);
        $this->assertSame('Article history', $usage['Admin/Blog/Details/old-photo.jpg'][0]['label']);
        $this->assertArrayNotHasKey('Admin/Media/2026/10/rejected-only.webp', $usage, 'a rejected change no longer holds a picture');

        $this->assertTrue(MediaLibrary::isInUse('Admin/Media/2026/10/review-card.webp', $usage));
    }
}
