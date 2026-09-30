<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Nobody can get a new account except through a Super Admin. */
class AccountCreationLockTest extends TestCase
{
    use RefreshDatabase;

    private string $lock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lock = storage_path('framework/owner.lock');
        @unlink($this->lock);
    }

    protected function tearDown(): void
    {
        @unlink($this->lock);
        parent::tearDown();
    }

    public function test_setup_page_stays_closed_once_an_owner_existed_even_if_all_users_are_gone(): void
    {
        $owner = User::factory()->create()->assignRole('super-admin');
        $this->get('/setup')->assertNotFound();
        $this->assertFileExists($this->lock);

        $owner->delete();
        $this->get('/setup')->assertNotFound();
        $this->post('/setup', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'abcd123456', 'password_confirmation' => 'abcd123456'])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_a_role_with_user_permissions_cannot_create_or_take_over_a_super_admin(): void
    {
        Role::findByName('admin')->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.delete']);
        $admin = User::factory()->create()->assignRole('admin');
        $owner = User::factory()->create()->assignRole('super-admin');

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'role' => 'super-admin', 'password' => 'abcd123456',
        ])->assertSessionHas('error');
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);

        $this->actingAs($admin)->put("/admin/users/{$owner->id}", [
            'name' => $owner->name, 'email' => $owner->email, 'role' => 'super-admin', 'is_active' => true, 'password' => 'newpass12345',
        ])->assertSessionHas('error');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $owner->fresh()->password));

        $this->actingAs($admin)->delete("/admin/users/{$owner->id}")->assertSessionHas('error');
        $this->assertNotNull($owner->fresh());

        // Normal team members can still be added.
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Writer', 'email' => 'writer@example.com', 'role' => 'writer', 'password' => 'abcd123456',
        ])->assertSessionHas('success');
    }
}
