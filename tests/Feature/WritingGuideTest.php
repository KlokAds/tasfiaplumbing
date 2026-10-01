<?php

namespace Tests\Feature;

use App\Models\BlogDetail;
use App\Models\User;
use App\Notifications\WeeklyContentPlan;
use App\Support\ContentScan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WritingGuideTest extends TestCase
{
    use RefreshDatabase;

    private function oldArticle(User $author, string $name, string $status = BlogDetail::PUBLISHED): BlogDetail
    {
        $this->travel(-14)->months();
        $blog = BlogDetail::create(['name' => $name, 'desc' => '<p>Short</p>', 'status' => $status, 'author_id' => $author->id]);
        $this->travelBack();

        return $blog;
    }

    public function test_writers_see_the_guide_with_a_scan_and_a_plan(): void
    {
        $writer = User::factory()->create(['is_active' => true, 'job_title' => null, 'bio' => null])->assignRole('editor');
        $this->oldArticle($writer, 'Live Guide');
        $this->oldArticle($writer, 'Draft Guide', BlogDetail::DRAFT);

        $props = $this->actingAs($writer)->get('/admin/seo/guide')->assertOk()->viewData('page')['props'];

        $articles = $props['scan']['types']['article'];
        $this->assertSame(1, $articles['total']);
        $this->assertNotEmpty($articles['issues']);
        $this->assertSame('Live Guide', $articles['issues'][0]['pages'][0]['name']);
        $this->assertSame(1, $props['scan']['authors'][0]['articles']);
        $this->assertNotEmpty($props['checks']['article']['aeo']);
        $this->assertStringNotContainsString('(now', collect($props['checks']['article'])->flatten(1)->pluck('tip')->join(' '));

        $plan = $props['scan']['plan']['articles'];
        $this->assertSame('Live Guide', $plan[0]['name']);
        $this->assertContains('Not updated for 14 months', $plan[0]['reasons']);
        $this->assertNotEmpty($plan[0]['missing']);
    }

    public function test_recently_updated_articles_are_left_out_of_the_plan(): void
    {
        $writer = User::factory()->create(['is_active' => true])->assignRole('editor');
        $blog = $this->oldArticle($writer, 'Fresh Guide');
        $blog->update(['desc' => '<p>Changed today</p>']);

        $this->assertSame([], ContentScan::refresh()['plan']['articles']);
    }

    public function test_gaps_do_not_repeat_the_same_pages(): void
    {
        $writer = User::factory()->create(['is_active' => true])->assignRole('editor');
        foreach (range(1, 12) as $i) {
            $this->oldArticle($writer, "Guide number {$i}");
        }

        $issues = ContentScan::refresh()['types']['article']['issues'];
        $first = collect($issues[0]['pages'])->pluck('name');
        $second = collect($issues[1]['pages'])->pluck('name');
        $this->assertCount(5, $first);
        $this->assertEmpty($first->intersect($second));
    }

    public function test_article_ideas_skip_covered_brand_and_near_duplicate_searches(): void
    {
        \App\Models\SiteSetting::putMany(['business.brand_name' => 'Tasfia Door Repair']);
        $search = ['pages' => [], 'queries' => [
            ['key' => 'fire door rules hdb', 'impressions' => 90, 'clicks' => 1, 'position' => 9.1],
            ['key' => 'fire doors rules hdb', 'impressions' => 40, 'clicks' => 0, 'position' => 12.0],
            ['key' => 'tasfia door repair', 'impressions' => 300, 'clicks' => 50, 'position' => 1.2],
            ['key' => 'sliding door repair cost', 'impressions' => 200, 'clicks' => 3, 'position' => 6.0],
            ['key' => 'rare question', 'impressions' => 3, 'clicks' => 0, 'position' => 30.0],
        ]];
        $ideas = (new \ReflectionMethod(ContentScan::class, 'articleIdeas'))
            ->invoke(null, $search, collect(['Sliding Door Repair Cost in Singapore']));

        $this->assertSame(['fire door rules hdb'], array_column($ideas, 'query'));
    }

    public function test_the_scan_command_emails_the_plan(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['is_active' => true])->assignRole(config('admin.super_role'));
        $this->oldArticle($owner, 'Live Guide');

        $this->artisan('content:scan')->assertSuccessful();
        Notification::assertNothingSent();

        $this->artisan('content:scan', ['--email' => true])->assertSuccessful();
        Notification::assertSentTo($owner, WeeklyContentPlan::class, function ($n) use ($owner) {
            $mail = $n->toMail($owner);

            return str_contains($mail->subject, '1 article to update')
                && $mail->viewData['fields'][0][1] === 'Live Guide (score ' . $n->scan['plan']['articles'][0]['score'] . ')';
        });
    }
}
