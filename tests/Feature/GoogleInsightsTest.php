<?php

namespace Tests\Feature;

use App\Models\GoogleReview;
use App\Models\IndexStatus;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Google\BusinessProfile;
use App\Support\Google\GoogleApi;
use App\Support\Google\SearchConsole;
use App\Support\ReviewFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function connect(): void
    {
        SiteSetting::putMany([
            'google.client_id' => 'abc.apps.googleusercontent.com',
            'google.client_secret' => Crypt::encryptString('secret'),
            'google.refresh_token' => Crypt::encryptString('refresh'),
        ]);
    }

    public function test_connect_button_sends_owner_to_google_with_all_scopes(): void
    {
        SiteSetting::putMany(['google.client_id' => 'abc.apps.googleusercontent.com', 'google.client_secret' => Crypt::encryptString('secret')]);
        $owner = User::factory()->create()->assignRole('super-admin');

        $res = $this->actingAs($owner)->post('/admin/insights/google/connect', [], ['X-Inertia' => 'true']);
        $res->assertStatus(409);
        $location = $res->headers->get('X-Inertia-Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        $this->assertStringContainsString(urlencode('https://www.googleapis.com/auth/business.manage'), $location);
        $this->assertStringContainsString('access_type=offline', $location);

        // A callback with a wrong state is refused
        $this->get('/admin/insights/google/callback?state=wrong&code=x')->assertRedirect('/admin/insights/google')->assertSessionHas('error');
    }

    public function test_all_business_profile_reviews_are_imported_and_shown(): void
    {
        $this->connect();
        SiteSetting::putMany(['google.gbp_location' => 'accounts/1/locations/2', 'google.gbp_place_id' => 'PLACE1']);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'mybusiness.googleapis.com/v4/accounts/1/locations/2/reviews*' => Http::sequence()
                ->push(['averageRating' => 4.9, 'totalReviewCount' => 3, 'nextPageToken' => 'p2', 'reviews' => [
                    ['reviewId' => 'r1', 'reviewer' => ['displayName' => 'Kelvin Tan'], 'starRating' => 'FIVE', 'comment' => 'Great tiling job', 'createTime' => '2026-09-01T10:00:00Z',
                        'reviewReply' => ['comment' => 'Thank you Kelvin!']],
                    ['reviewId' => 'r2', 'reviewer' => ['displayName' => 'Mei'], 'starRating' => 'TWO', 'comment' => 'Late', 'createTime' => '2026-08-01T10:00:00Z'],
                ]])
                ->push(['reviews' => [
                    ['reviewId' => 'r3', 'reviewer' => ['displayName' => 'Ahmad'], 'starRating' => 'FIVE', 'comment' => '(Translated by Google) Very good (Original) Sangat bagus', 'createTime' => '2026-07-01T10:00:00Z'],
                ]]),
        ]);

        $this->assertSame(3, BusinessProfile::sync());
        $this->assertSame(3, GoogleReview::count());
        $this->assertSame('Very good', GoogleReview::where('review_id', 'r3')->value('comment'));

        $feed = ReviewFeed::build();
        $this->assertSame(4.9, $feed['google']['rating']);
        $this->assertSame(3, $feed['google']['total']);
        $this->assertSame(['Kelvin Tan', 'Ahmad'], array_column(array_filter($feed['items'], fn ($r) => $r['source'] === 'google'), 'name')); // 2★ hidden by min rating
        $this->assertSame('Thank you Kelvin!', $feed['items'][0]['reply']);

        // Owner hides one review
        $owner = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($owner)->post('/admin/reviews/imported/' . GoogleReview::where('review_id', 'r1')->value('id') . '/toggle');
        $this->assertSame(['Ahmad'], array_column(ReviewFeed::build()['items'], 'name'));
    }

    public function test_search_console_report_and_index_check(): void
    {
        $this->connect();
        SiteSetting::putMany(['google.gsc_property' => 'sc-domain:example.test']);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'www.googleapis.com/webmasters/v3/sites/*' => Http::response(['rows' => [['keys' => ['water heater repair'], 'clicks' => 12, 'impressions' => 300, 'ctr' => 0.04, 'position' => 6.2]]]),
            'searchconsole.googleapis.com/v1/urlInspection/index:inspect' => Http::response(['inspectionResult' => ['indexStatusResult' => [
                'verdict' => 'NEUTRAL', 'coverageState' => 'Crawled - currently not indexed', 'lastCrawlTime' => '2026-09-20T01:00:00Z',
            ]]]),
        ]);

        $report = SearchConsole::report(true);
        $this->assertSame(12, $report['now']['clicks']);
        $this->assertSame('water heater repair', $report['queries'][0]['key']);

        $status = SearchConsole::inspect(url('/about'));
        $this->assertFalse($status->isIndexed());
        $this->assertSame('Crawled - currently not indexed', IndexStatus::first()->coverage);

        $owner = User::factory()->create()->assignRole('super-admin');
        $this->actingAs($owner)->get('/admin/insights/indexing')->assertOk();
        $this->actingAs($owner)->get('/admin/insights')->assertOk();
    }

    public function test_expired_google_login_is_explained(): void
    {
        $this->connect();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        try {
            GoogleApi::accessToken();
            $this->fail('Expected an exception');
        } catch (\App\Support\Google\GoogleException $e) {
            $this->assertStringContainsString('Connect Google', $e->getMessage());
        }
    }

    public function test_writers_cannot_open_google_connections(): void
    {
        $writer = User::factory()->create()->assignRole('writer');
        $this->actingAs($writer)->get('/admin/insights/google')->assertForbidden();
    }
}
