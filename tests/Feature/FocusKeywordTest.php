<?php

namespace Tests\Feature;

use App\Models\BlogDetail;
use App\Models\User;
use App\Support\FocusKeyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Focus keywords suggested from titles and filled for many articles at once (only empty ones). */
class FocusKeywordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_keyword_is_suggested_from_the_title(): void
    {
        $this->assertSame('sliding door repair', FocusKeyword::suggest('Comprehensive Guide to Sliding Door Repair: Tips, Techniques, and Best Practices'));
        $this->assertSame('sliding door roller replacement singapore', FocusKeyword::suggest('Get Your Glide Back: Sliding Door Roller Replacement in Singapore'));
        $this->assertSame('smart lock installation singapore', FocusKeyword::suggest('Smart Lock Installation & Digital Door Lock Replacement in Singapore'));
        $this->assertSame('plumbing services singapore', FocusKeyword::suggest('Navigating the Flow: The Indispensable Role of Plumbing Services in Singapore'));
        $this->assertSame('kitchen sink tap replacement singapore', FocusKeyword::suggest('Kitchen Sink Tap Replacement SG- Professional Plumbing Services'));
    }

    public function test_only_empty_focus_keywords_are_filled(): void
    {
        $owner = User::factory()->create(['is_active' => true])->assignRole('super-admin');
        $empty = BlogDetail::create(['name' => 'Glass Door Repair Singapore', 'slug' => 'glass-door-repair', 'desc' => '<p>Text</p>', 'status' => BlogDetail::PUBLISHED]);
        $set = BlogDetail::create(['name' => 'Door Closer Repair', 'slug' => 'door-closer-repair', 'desc' => '<p>Text</p>', 'status' => BlogDetail::PUBLISHED, 'focus_keyword' => 'door closer repair']);

        $this->actingAs($owner)->get('/admin/blogs?filter=no_keyword')
            ->assertInertia(fn ($page) => $page->has('blogs.data', 1)->where('blogs.data.0.suggested_keyword', 'glass door repair singapore'));

        $this->actingAs($owner)->post('/admin/blogs-bulk/focus-keywords', ['items' => [
            ['id' => $empty->id, 'keyword' => 'Glass Door Repair Singapore'],
            ['id' => $set->id, 'keyword' => 'something else'],
        ]])->assertSessionHas('success');

        $this->assertSame('glass door repair singapore', $empty->fresh()->focus_keyword);
        $this->assertSame('door closer repair', $set->fresh()->focus_keyword, 'a keyword already set is kept');
        $this->assertSame('<p>Text</p>', $empty->fresh()->desc);
    }
}
