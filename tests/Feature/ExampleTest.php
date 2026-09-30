<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_renders_with_site_schema(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"WebSite"', false);
    }

    public function test_robots_txt_blocks_everything_outside_production(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee("User-agent: *\nDisallow: /", false);
    }
}
