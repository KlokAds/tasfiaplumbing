<?php

namespace Tests\Feature;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Preview": the article on the real article page before it is live, for signed-in admin users only. */
class ArticlePreviewTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['is_active' => true])->assignRole($role);
    }

    public function test_the_editor_preview_shows_the_unsaved_text_on_the_article_page(): void
    {
        $writer = $this->user('writer');
        $payload = json_encode(['name' => 'Floor Spring Guide', 'desc' => '<h2>Why it fails</h2><p>Unsaved text.</p><script>alert(1)</script>',
            'faqs' => [['question' => 'How long?', 'answer' => 'About an hour.']]]);

        $res = $this->actingAs($writer)->post('/admin/blogs-preview', ['payload' => $payload]);
        $res->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $res->assertInertia(fn ($page) => $page->component('Frontend/Blogs/Show')
            ->where('preview', true)
            ->where('blog.name', 'Floor Spring Guide')
            ->where('blog.desc', fn ($html) => str_contains($html, 'Unsaved text.') && !str_contains($html, '<script'))
            ->where('blog.faqs.0.question', 'How long?')
            ->where('author.name', $writer->name));
        $this->assertSame(0, BlogDetail::count(), 'nothing is saved');
    }

    public function test_a_change_can_be_previewed_by_its_writer_and_approvers_only(): void
    {
        $writer = $this->user('editor');
        $other = $this->user('writer');
        $approver = $this->user('super-admin');
        $blog = BlogDetail::create(['name' => 'Live Guide', 'desc' => '<p>Live</p>', 'status' => BlogDetail::PUBLISHED]);
        $revision = ArticleRevision::create(['article_id' => $blog->id, 'user_id' => $writer->id, 'status' => 'pending',
            'payload' => ['name' => 'Live Guide', 'desc' => '<p>Proposed text</p>']]);

        $this->actingAs($writer)->get("/admin/revisions/{$revision->id}/preview")->assertOk()
            ->assertInertia(fn ($page) => $page->where('blog.desc', fn ($h) => str_contains($h, 'Proposed text')));
        $this->actingAs($approver)->get("/admin/revisions/{$revision->id}/preview")->assertOk();
        $this->actingAs($other)->get("/admin/revisions/{$revision->id}/preview")->assertForbidden();
        $this->assertSame('<p>Live</p>', $blog->fresh()->desc);

        auth()->logout();
        $this->get("/admin/revisions/{$revision->id}/preview")->assertRedirect();
        $this->post('/admin/blogs-preview', ['payload' => '{}'])->assertRedirect();
    }
}
