<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_page_shows_the_branded_404(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('find that page')->assertSee('Search services and guides');
    }

    public function test_inertia_navigation_to_a_missing_page_reloads_into_the_error_page(): void
    {
        $this->withoutVite();
        $this->get('/service/no-such-service', ['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location');
    }
}
