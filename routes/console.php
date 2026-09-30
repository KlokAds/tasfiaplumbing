<?php

use App\Models\BlogDetail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('articles:publish-scheduled', function () {
    $n = BlogDetail::publishDue();
    $this->info($n ? "Published {$n} scheduled article(s)." : 'Nothing due.');
})->purpose('Publish approved articles whose scheduled time has passed');

// Server cron: * * * * * cd /path/to/site && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('articles:publish-scheduled')->everyMinute()->withoutOverlapping();

Artisan::command('google:sync', function () {
    if (!\App\Support\Google\GoogleApi::connected()) {
        return $this->info('Google is not connected.');
    }
    try {
        if (\App\Support\Google\BusinessProfile::selected()) {
            $this->info('Reviews synced: ' . \App\Support\Google\BusinessProfile::sync());
        }
        if (\App\Support\Google\SearchConsole::property()) {
            $this->info('Pages checked for indexing: ' . \App\Support\Google\SearchConsole::inspectBatch());
        }
        \App\Support\Google\GoogleApi::flushReports();
    } catch (\Throwable $e) {
        \App\Models\SiteSetting::putMany(['google.last_error' => mb_substr($e->getMessage(), 0, 250)]);
        $this->error($e->getMessage());
    }
})->purpose('Import Google reviews and check which pages Google has indexed');

Schedule::command('google:sync')->dailyAt('04:30')->withoutOverlapping();

// Lets Admin → System show whether the server cron is running.
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::forever('system.scheduler_seen', time()))->everyMinute()->name('scheduler-heartbeat');
