<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\ServiceDetail;
use App\Support\LocationPages;
use App\Support\SingaporeTowns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Singapore area pages: missing towns are added, empty pages filled, written pages kept. */
class LocationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_towns_are_added_with_their_own_text_and_written_pages_are_kept(): void
    {
        ServiceDetail::create(['name' => 'Main service', 'slug' => 'main-service', 'desc' => '<p>Service</p>', 'is_active' => true, 'order' => 1]);
        $written = Location::create(['name' => 'Bedok', 'slug' => 'bedok', 'region' => 'East', 'is_active' => true,
            'description' => '<p>' . str_repeat('Our own text about the Bedok jobs we did last year. ', 8) . '</p>']);
        $typo = Location::create(['name' => 'Tampanies', 'slug' => 'tampanies', 'region' => 'East', 'is_active' => true, 'description' => '<p>Tampines area</p>']);

        $this->assertSame(count(SingaporeTowns::all()) - 2, LocationPages::sync(dryRun: true)['created']);
        $this->assertSame(2, Location::count(), 'a dry run changes nothing');

        $r = LocationPages::sync();
        $this->assertSame(count(SingaporeTowns::all()) - 2, $r['created']);
        $this->assertSame(1, $r['filled']);
        $this->assertSame(1, $r['kept']);
        $this->assertSame(count(SingaporeTowns::all()), Location::count());

        // The written page is not touched; the misspelt one is now Tampines, the old URL redirects.
        $this->assertStringContainsString('Our own text', $written->fresh()->description);
        $typo->refresh();
        $this->assertSame('Tampines', $typo->name);
        $this->assertSame('tampines', $typo->slug);
        $this->assertTrue(\App\Models\Redirect::where('from_path', '/locations/tampanies')->where('to_path', '/locations/tampines')->exists());

        // Each page has its own facts, its services and three FAQs; titles fit in Google.
        $toa = Location::where('slug', 'toa-payoh')->firstOrFail();
        $this->assertStringContainsString('first HDB towns', $toa->description);
        $this->assertStringContainsString('Kim Keat', $toa->description);
        $this->assertSame(1, $toa->services()->count());
        $this->assertSame(3, $toa->faqs()->count());
        foreach (Location::all() as $l) {
            $this->assertLessThanOrEqual(60, mb_strlen((string) $l->meta_title), $l->name);
            $this->assertLessThanOrEqual(160, mb_strlen((string) $l->meta_desc), $l->name);
        }
        $this->assertNotSame(Location::where('slug', 'tuas')->value('description'), Location::where('slug', 'punggol')->value('description'));

        $this->get('/locations/toa-payoh')->assertOk()->assertSee('Toa Payoh');
        $this->get('/locations')->assertOk();

        // Running it again adds nothing.
        $again = LocationPages::sync();
        $this->assertSame(0, $again['created'] + $again['filled'] + $again['services'] + $again['faqs']);
    }
}
