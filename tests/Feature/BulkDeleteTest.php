<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Message;
use App\Models\Redirect;
use App\Models\ServiceDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create()->assignRole('super-admin');
    }

    public function test_bulk_delete_uses_each_lists_own_rules(): void
    {
        $owner = $this->owner();
        $a = ServiceDetail::create(['name' => 'Service A', 'desc' => 'x']);
        $b = ServiceDetail::create(['name' => 'Service B', 'desc' => 'x']);

        $this->actingAs($owner)->post('/admin/bulk/services/delete', ['ids' => [$a->id, $b->id]])
            ->assertSessionHas('success', '2 services deleted.');
        $this->assertSame(0, ServiceDetail::count());
        // The single-delete rule still applies: removed pages answer 410 to Google.
        $this->assertTrue(Redirect::where('from_path', '/service/service-a')->where('code', 410)->exists());
    }

    public function test_messages_and_faqs(): void
    {
        $owner = $this->owner();
        $m = Message::create(['name' => 'A', 'email' => 'a@x.test', 'message' => 'hi', 'is_read' => 0]);
        $f = Faq::create(['question' => 'Q?', 'answer' => 'A.', 'is_active' => true]);

        $this->actingAs($owner)->post('/admin/bulk/messages/delete', ['ids' => [$m->id]])->assertSessionHas('success');
        $this->post('/admin/bulk/faqs/delete', ['ids' => [$f->id]])->assertSessionHas('success');
        $this->assertSame(0, Message::count() + Faq::count());
    }

    public function test_you_cannot_bulk_delete_yourself(): void
    {
        $owner = $this->owner();
        $other = User::factory()->create()->assignRole('writer');

        $this->actingAs($owner)->post('/admin/bulk/users/delete', ['ids' => [$owner->id, $other->id]]);
        $this->assertModelExists($owner);
        $this->assertModelMissing($other);
    }

    public function test_bulk_delete_needs_the_delete_permission(): void
    {
        $writer = User::factory()->create()->assignRole('writer');
        $s = ServiceDetail::create(['name' => 'Keep', 'desc' => 'x']);

        $this->actingAs($writer)->post('/admin/bulk/services/delete', ['ids' => [$s->id]])->assertForbidden();
        $this->post('/admin/bulk/unknown/delete', ['ids' => [1]])->assertNotFound();
        $this->assertModelExists($s);
    }
}
