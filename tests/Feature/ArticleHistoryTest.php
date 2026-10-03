<?php

namespace Tests\Feature;

use App\Models\ArticleVersion;
use App\Models\BlogDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Live versions are kept, the last three are shown, any can be restored; only the Super Admin approves. */
class ArticleHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['is_active' => true])->assignRole($role);
    }

    private function article(array $overrides = []): array
    {
        return $overrides + ['name' => 'Floor Spring Repair Singapore', 'desc' => '<p>Version one</p>', 'faqs' => []];
    }

    public function test_each_live_change_keeps_the_version_it_replaced_and_a_version_can_be_restored(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin');
        $this->actingAs($owner)->post('/admin/blogs', $this->article(['intent' => 'publish']));
        $blog = BlogDetail::firstOrFail();

        foreach (['two', 'three', 'four', 'five'] as $n) {
            $this->actingAs($owner)->post("/admin/blogs/{$blog->id}", $this->article(['intent' => 'publish', 'desc' => "<p>Version {$n}</p>"]));
        }
        // Saving without a change keeps no version.
        $this->actingAs($owner)->post("/admin/blogs/{$blog->id}", $this->article(['intent' => 'publish', 'desc' => '<p>Version five</p>']));
        $this->assertSame(4, ArticleVersion::count(), 'every replaced version stays stored');

        $list = $this->actingAs($owner)->getJson("/admin/blogs/{$blog->id}/versions")->assertOk()->json();
        $this->assertCount(3, $list['versions'], 'the last three are shown');
        $this->assertSame('<p>Version four</p>', $list['versions'][0]['payload']['desc']);
        $this->assertSame('Version five', $list['current']['text']);

        $this->actingAs($owner)->post('/admin/versions/' . $list['versions'][2]['id'] . '/restore')->assertRedirect();
        $this->assertSame('<p>Version two</p>', $blog->fresh()->desc);
        $this->assertSame('<p>Version five</p>', ArticleVersion::latest('id')->first()->payload['desc'], 'the replaced text is kept');
    }

    public function test_the_team_submits_and_only_the_super_admin_or_a_role_given_publish_approves(): void
    {
        Notification::fake();
        $owner = $this->user('super-admin');
        $editor = $this->user('editor');
        $admin = $this->user('admin');

        $this->actingAs($editor)->post('/admin/blogs', $this->article(['intent' => 'publish']));
        $blog = BlogDetail::firstOrFail();
        $this->assertSame(BlogDetail::PENDING, $blog->status, 'the editor submits');
        $this->actingAs($admin)->post("/admin/blogs/{$blog->id}/approve", ['mode' => 'now'])->assertForbidden();
        $this->actingAs($editor)->getJson("/admin/blogs/{$blog->id}/versions")->assertForbidden();

        // A role given "Publish & approve" can approve.
        Permission::findOrCreate('articles.publish', 'web');
        Role::findByName('admin', 'web')->givePermissionTo('articles.publish');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($admin->fresh())->post("/admin/blogs/{$blog->id}/approve", ['mode' => 'now'])->assertRedirect();
        $this->assertSame(BlogDetail::PUBLISHED, $blog->fresh()->status);
    }
}
