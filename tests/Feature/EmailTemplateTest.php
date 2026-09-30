<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Notifications\NewEnquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_enquiry_email_uses_the_branded_template_and_links_to_the_enquiry(): void
    {
        $m = Message::create(['name' => 'Kelvin Tan', 'email' => 'k@example.test', 'phone' => '81234567', 'subject' => 'Water heater', 'message' => "Leaking since Monday.\nHDB 4-room.", 'is_read' => 0]);

        $html = (string) (new NewEnquiry($m))->toMail(new AnonymousNotifiable)->render();

        $this->assertStringContainsString('Kelvin Tan sent an enquiry', $html);
        $this->assertStringContainsString('/admin/messages?open=' . $m->id, $html);
        $this->assertStringContainsString('https://wa.me/6581234567', $html);
        $this->assertStringContainsString('/mail/seen/' . $m->id, $html);
        $this->assertStringNotContainsString('Laravel', $html, 'No Laravel default template');
    }

    public function test_opening_the_email_marks_the_enquiry_read(): void
    {
        $m = Message::create(['name' => 'A', 'email' => 'a@example.test', 'message' => 'hi', 'is_read' => 0]);

        $this->get('/mail/seen/' . $m->id)->assertForbidden(); // unsigned links are refused
        $this->get(URL::signedRoute('mail.seen', ['message' => $m->id]))->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->assertSame(1, (int) $m->fresh()->is_read);
    }
}
