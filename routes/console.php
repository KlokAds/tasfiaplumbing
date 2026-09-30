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

Artisan::command('google:sync {task? : reports, index or reviews; runs it now}', function (?string $task = null) {
    if (!\App\Support\Google\GoogleApi::connected()) {
        return $this->info('Google is not connected.');
    }
    if ($task) {
        abort_unless(array_key_exists($task, \App\Support\Google\GoogleSync::TASKS), 1, 'Unknown task.');
        $r = \App\Support\Google\GoogleSync::run($task);

        return $r['ok'] ? $this->info("{$task}: {$r['summary']}") : $this->error("{$task}: {$r['summary']}");
    }
    $done = \App\Support\Google\GoogleSync::runDue();
    foreach ($done as $name => $r) {
        $r['ok'] ? $this->info("{$name}: {$r['summary']}") : $this->error("{$name}: {$r['summary']}");
    }
    if (!$done) {
        $this->info('Nothing due.');
    }
})->purpose('Run the Google tasks that are due (reports, index check, reviews), as set in Admin → Insights → Google');

// Checks every 5 minutes which Google task is due; how often each runs is set in the admin.
Schedule::command('google:sync')->everyFiveMinutes()->withoutOverlapping(30);

// Lets Admin → System show whether the server cron is running.
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::forever('system.scheduler_seen', time()))->everyMinute()->name('scheduler-heartbeat');
