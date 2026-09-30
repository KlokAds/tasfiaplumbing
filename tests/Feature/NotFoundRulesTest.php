<?php

namespace Tests\Feature;

use App\Models\NotFoundLog;
use App\Support\NotFoundRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotFoundRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_probes_are_not_logged_but_real_pages_are(): void
    {
        foreach (['/.env', '/wp-login.php', '/vendor/autoload.php', '/composer.json', '/artisan', '/cron.php', '/install', '/backup.sql', '/.well-known/ai-catalog.json', '/.git/config'] as $p) {
            $this->assertTrue(NotFoundRules::isNoise($p), $p);
        }
        foreach (['/blogs/old-article', '/service/leaking-pipe', '/llms.txt', '/.well-known/security.txt', '/pricing-old'] as $p) {
            $this->assertFalse(NotFoundRules::isNoise($p), $p);
        }

        $this->get('/wp-admin/setup-config.php')->assertNotFound();
        $this->get('/some-old-page-that-is-gone')->assertNotFound();
        $this->assertFalse(NotFoundLog::where('path', 'like', '/wp-admin%')->exists());
        $this->assertTrue(NotFoundLog::where('path', '/some-old-page-that-is-gone')->exists());
    }

    public function test_it_suggests_where_a_broken_url_should_go(): void
    {
        $pages = ['/', '/contact', '/blogs/fast-toilet-flush-replacement-service', '/service/leaking-pipe-repair', '/blogs/water-heater-guide'];

        $this->assertSame('/admin/forgot-password', NotFoundRules::suggest('/forget-password', $pages));
        $this->assertSame('/admin', NotFoundRules::suggest('/dashboard', $pages));
        $this->assertSame('/blogs/fast-toilet-flush-replacement-service', NotFoundRules::suggest('/blogs/fast-toilet-flush-replacement-service-in-singapore-tasfia-engineering', $pages));
        $this->assertSame('/service/leaking-pipe-repair', NotFoundRules::suggest('/service/leaking-pipe-repair-singapore/', $pages));
        $this->assertNull(NotFoundRules::suggest('/completely-unrelated-thing', $pages));
    }

    public function test_llms_txt_describes_the_site_for_ai_assistants(): void
    {
        $this->get('/llms.txt')->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('## Contact', false)
            ->assertSee('## Services', false)
            ->assertSee(url('/contact'), false);
    }
}
