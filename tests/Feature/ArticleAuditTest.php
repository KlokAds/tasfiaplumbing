<?php

namespace Tests\Feature;

use App\Models\ArticleAudit;
use App\Models\BlogDetail;
use App\Models\ServiceDetail;
use App\Models\User;
use App\Support\ArticleAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Article audit: topic groups, suggestions, and decisions that never change the website. */
class ArticleAuditTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $name, string $text, array $extra = []): BlogDetail
    {
        return BlogDetail::create($extra + ['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'desc' => $text, 'status' => BlogDetail::PUBLISHED]);
    }

    private function text(string $seed, int $words = 400): string
    {
        $out = [];
        for ($i = 0; $i < $words; $i++) {
            $out[] = $seed . ($i % 37) . 'w' . ($i % 11);
        }

        return '<p>' . implode(' ', $out) . '</p>';
    }

    public function test_copies_on_one_topic_are_merged_into_the_strongest_and_never_into_each_other(): void
    {
        $same = $this->text('closer');
        $a = $this->article('Wardrobe Sliding Door Repair in Singapore', $same);
        $b = $this->article('Wardrobe Sliding Door Repair Service Singapore', $same);
        $c = $this->article('Wardrobe Sliding Door Repair: A Guide', $this->text('track'));
        $own = $this->article('Glass Door Hinge Replacement', $this->text('hinge', 700));

        $result = ArticleAuditor::run();
        $this->assertSame(4, $result['articles']);
        $this->assertFalse($result['search_console']);

        $rows = ArticleAudit::all()->keyBy('article_id');
        $this->assertSame('wardrobe sliding door repair', $rows[$a->id]->group_key);
        $this->assertSame(3, $rows[$a->id]->group_size);
        $this->assertGreaterThanOrEqual(90, $rows[$a->id]->dup_percent);

        // One of the group stays; the others merge into an article that stays (no chains, no loops).
        $merged = $rows->only([$a->id, $b->id, $c->id])->where('suggestion', 'merge');
        $this->assertCount(2, $merged);
        foreach ($merged as $m) {
            $this->assertNotSame('merge', $rows[$m->target_article_id]->suggestion);
            $this->assertNotSame($m->article_id, $m->target_article_id);
        }
        $this->assertContains($rows[$own->id]->suggestion, ['keep', 'update'], 'its own topic is not merged');
    }

    public function test_an_article_on_the_same_search_as_a_service_page_is_flagged(): void
    {
        ServiceDetail::create(['name' => 'Door Repair Services', 'slug' => 'door-repair', 'desc' => '<p>Service</p>', 'is_active' => true]);
        $a = $this->article('Door Repair Singapore', $this->text('door'));
        $this->article('Door Lock Replacement Singapore', $this->text('lock'));

        ArticleAuditor::run();
        $row = ArticleAudit::where('article_id', $a->id)->firstOrFail();
        $this->assertNotNull($row->service_id);
        $this->assertSame('merge', $row->suggestion);
        $this->assertStringContainsString('service page', $row->reason);
    }

    public function test_decisions_are_saved_without_changing_the_website_and_survive_a_new_run(): void
    {
        $owner = User::factory()->create(['is_active' => true])->assignRole('super-admin');
        $writer = User::factory()->create(['is_active' => true])->assignRole('writer');
        $a = $this->article('Wardrobe Sliding Door Repair in Singapore', $this->text('closer'));
        $b = $this->article('Wardrobe Sliding Door Repair Singapore', $this->text('closer'));
        ArticleAuditor::run();
        $row = ArticleAudit::where('article_id', $b->id)->firstOrFail();

        $this->actingAs($writer)->get('/admin/blogs-audit')->assertForbidden();
        $this->actingAs($owner)->get('/admin/blogs-audit')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Blogs/Audit')->where('summary.total', 2)->has('groups.data', 1));

        $this->actingAs($owner)->post("/admin/blogs-audit/{$row->id}/decide", ['decision' => 'merge', 'target_id' => $a->id])->assertSessionHas('success');
        $this->actingAs($owner)->post("/admin/blogs-audit/{$row->id}/decide", ['decision' => 'merge', 'target_id' => $b->id])->assertSessionHas('error');

        ArticleAuditor::run();
        $row->refresh();
        $this->assertSame('merge', $row->decision);
        $this->assertSame($a->id, $row->decision_target_id);
        $this->assertSame(BlogDetail::PUBLISHED, $b->fresh()->status, 'nothing on the website changes');

        $this->actingAs($owner)->post('/admin/blogs-audit/accept-group', ['group_key' => 'wardrobe sliding door repair']);
        $this->assertNotNull(ArticleAudit::where('article_id', $a->id)->value('decision'));
    }
}
