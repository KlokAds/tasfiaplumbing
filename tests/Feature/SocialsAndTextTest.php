<?php

namespace Tests\Feature;

use App\Models\ContactContent;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Socials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialsAndTextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_social_links_are_checked_cleaned_and_shared_everywhere(): void
    {
        ContactContent::create(['phone' => '93730360', 'email' => 'a@example.test', 'address' => 'SG']);
        $owner = User::factory()->create()->assignRole('super-admin');

        $this->actingAs($owner)->post('/admin/settings/contact', [
            'phone' => '93730360', 'email' => 'a@example.test', 'address' => 'SG',
            'whatsapp' => '',
            'socials' => ['facebook' => 'facebook.com/tasfia', 'instagram' => '@tasfia', 'youtube' => 'https://www.youtube.com/@tasfia', 'linkedin' => ''],
        ])->assertSessionHasNoErrors();

        $this->assertSame([
            'facebook' => 'https://facebook.com/tasfia',
            'instagram' => 'https://www.instagram.com/tasfia',
            'youtube' => 'https://www.youtube.com/@tasfia',
        ], Socials::links());
        $this->assertSame('https://wa.me/6593730360', Socials::whatsappUrl('93730360')); // empty WhatsApp = phone

        // A YouTube link in the LinkedIn field is refused
        $this->post('/admin/settings/contact', ['phone' => '93730360', 'socials' => ['linkedin' => 'https://youtube.com/@x']])
            ->assertSessionHasErrors('socials.linkedin');

        auth()->logout();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('https://www.instagram.com/tasfia', $html);   // footer + schema sameAs
        $this->assertStringContainsString('"telephone":"+6593730360"', $html);
    }

    public function test_website_text_is_editable_and_used_on_the_site(): void
    {
        $owner = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($owner)->get('/admin/website-text')->assertOk();
        $this->post('/admin/website-text', ['texts' => ['text.steps_title' => 'Three easy steps', 'text.cta_title' => 'Need a hand?', 'bad.key' => 'x']])
            ->assertSessionHas('success');

        $this->assertSame('Three easy steps', SiteSetting::get('text.steps_title'));
        $this->assertNull(SiteSetting::get('bad.key'));

        auth()->logout();
        $this->get('/')->assertSee('Three easy steps')->assertSee('Need a hand?');
    }

    public function test_checklist_page_lists_open_items(): void
    {
        $owner = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($owner)->get('/admin/checklist')->assertOk()->assertSee('WhatsApp number');
        $this->actingAs($owner)->get('/admin/dashboard')->assertOk();
    }
}
