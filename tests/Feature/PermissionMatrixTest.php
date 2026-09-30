<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Every admin page is closed without its permission and opens with only that permission. */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Process::fake(); // System Update page asks git for its status
    }

    /** @return array<string, string> uri => permission */
    private function pages(): array
    {
        $pages = [];
        foreach (Route::getRoutes() as $route) {
            if (!in_array('GET', $route->methods(), true) || !str_starts_with($route->uri(), 'admin/') || str_contains($route->uri(), '{')) {
                continue;
            }
            foreach ($route->gatherMiddleware() as $m) {
                if (is_string($m) && str_starts_with($m, 'can:')) {
                    $pages[$route->uri()] = substr($m, 4);
                }
            }
        }
        // Not pages: file downloads, JSON endpoints and the OAuth return address.
        return array_diff_key($pages, array_flip(['admin/redirects/export', 'admin/media/browse', 'admin/system/update/logs/latest', 'admin/insights/google/callback']));
    }

    public function test_every_admin_page_requires_its_permission(): void
    {
        $pages = $this->pages();
        $this->assertGreaterThan(25, count($pages));

        $nobody = User::factory()->create()->assignRole(Role::findOrCreate('no-access', 'web'));
        foreach ($pages as $uri => $permission) {
            $this->actingAs($nobody)->get('/' . $uri)->assertForbidden();
        }

        foreach ($pages as $uri => $permission) {
            $role = Role::findOrCreate('only-' . $permission, 'web');
            $role->syncPermissions([$permission]);
            $user = User::factory()->create()->assignRole($role);
            $status = $this->actingAs($user)->get('/' . $uri)->status();
            $this->assertNotSame(403, $status, "/{$uri} should open with {$permission}");
            $this->assertLessThan(500, $status, "/{$uri} crashed with only {$permission}");
        }
    }
}
