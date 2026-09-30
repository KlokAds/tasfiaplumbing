<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_test_email_button_reports_back(): void
    {
        $owner = User::factory()->create()->assignRole('super-admin');

        $this->actingAs($owner)->from('/admin/system/settings')
            ->post('/admin/system/settings/test-mail', ['to' => 'owner@example.com'])
            ->assertRedirect('/admin/system/settings')
            ->assertSessionHas('success');
    }
}
