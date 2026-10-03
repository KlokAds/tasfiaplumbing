<?php

namespace Tests\Feature;

use App\Models\BlogDetail;
use App\Models\User;
use App\Support\ContentQuality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every article shows an author with a job title and bio: its own, the default author, or the team. */
class AuthorBylineTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $extra = []): User
    {
        return User::factory()->create(['is_active' => true] + $extra)->assignRole($role);
    }

    private function eeatAuthorChecks(BlogDetail $b): array
    {
        $checks = collect(ContentQuality::forArticle($b->fresh())['pillars']['eeat']['checks'] ?? [])->keyBy('label');

        return [$checks['Author job title']['ok'] ?? null, $checks['Author bio']['ok'] ?? null];
    }

    public function test_an_article_without_an_author_gets_the_team_byline_with_title_and_bio(): void
    {
        $owner = $this->user('super-admin');
        $this->actingAs($owner)->post('/admin/blogs', ['name' => 'Floor Spring Repair Singapore', 'desc' => '<p>Body</p>', 'faqs' => [], 'intent' => 'publish']);
        $blog = BlogDetail::firstOrFail();
        $blog->forceFill(['author_id' => null, 'auth_name' => 'Admin'])->save();

        $by = $blog->fresh()->byline();
        $this->assertTrue($by['team']);
        $this->assertStringEndsWith(' team', $by['name']);
        $this->assertSame(config('admin.team_byline.title'), $by['job_title']);
        $this->assertNotEmpty($by['bio']);
        $this->assertSame([true, true], $this->eeatAuthorChecks($blog));

        $this->get('/blogs/' . $blog->slug)->assertInertia(fn ($page) => $page->where('author.job_title', config('admin.team_byline.title'))->where('author.team', true));
    }

    public function test_the_default_author_and_a_picked_author_are_shown_with_their_own_title_and_bio(): void
    {
        $owner = $this->user('super-admin');
        $tech = $this->user('editor', ['name' => 'Rahim Uddin', 'job_title' => 'Senior Door Technician', 'bio' => 'Twelve years fixing doors.']);
        $this->actingAs($owner)->post('/admin/blogs', ['name' => 'Floor Spring Repair Singapore', 'desc' => '<p>Body</p>', 'faqs' => [], 'intent' => 'publish']);
        $blog = BlogDetail::firstOrFail();
        $blog->forceFill(['author_id' => null, 'auth_name' => 'Admin'])->save();

        $this->actingAs($owner)->post('/admin/blogs-default-author', ['author_id' => $tech->id])->assertRedirect();
        $this->assertSame('Rahim Uddin', $blog->fresh()->byline()['name']);
        $this->assertSame('Senior Door Technician', $blog->fresh()->byline()['job_title']);

        // An approver can also set the author on the article.
        $other = $this->user('writer', ['name' => 'Mei Lin', 'job_title' => 'Writer', 'bio' => 'Writes guides.']);
        $this->actingAs($owner)->post("/admin/blogs/{$blog->id}", ['name' => 'Floor Spring Repair Singapore', 'desc' => '<p>Body</p>', 'faqs' => [], 'intent' => 'publish', 'author_id' => $other->id]);
        $this->assertSame('Mei Lin', $blog->fresh()->byline()['name']);
    }

    public function test_the_super_admin_fills_in_a_team_members_bio(): void
    {
        $owner = $this->user('super-admin');
        $editor = $this->user('editor', ['name' => 'Rahim Uddin']);

        $this->actingAs($owner)->put("/admin/users/{$editor->id}", [
            'name' => 'Rahim Uddin', 'email' => $editor->email, 'role' => 'editor', 'is_active' => true,
            'job_title' => 'Senior Door Technician', 'bio' => 'Twelve years fixing doors.', 'social_url' => 'https://www.linkedin.com/in/rahim',
        ])->assertSessionHasNoErrors();

        $editor->refresh();
        $this->assertSame('Twelve years fixing doors.', $editor->bio);
        $this->assertSame('https://www.linkedin.com/in/rahim', $editor->social_url);
    }
}
