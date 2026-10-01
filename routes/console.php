<?php

use App\Models\BlogDetail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('articles:publish-scheduled', function () {
    $n = BlogDetail::publishDue();
    $this->info($n ? "Published {$n} scheduled article(s)." : 'Nothing due.');
})->purpose('Publish approved articles whose scheduled time has passed');

// Server cron: every minute, cron.php (see that file).
// Scheduled work runs inside the scheduler process (Schedule::call), not as a separate
// "php artisan ..." process: the host disables proc_open, so separate processes cannot start.
Schedule::call(fn () => Artisan::call('articles:publish-scheduled'))->everyMinute()->name('articles:publish-scheduled')->withoutOverlapping();

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
Schedule::call(fn () => Artisan::call('google:sync'))->everyFiveMinutes()->name('google:sync')->withoutOverlapping(30);

Artisan::command('content:scan {--email : Also email the weekly plan to everyone who approves articles}', function () {
    $scan = \App\Support\ContentScan::refresh();
    $a = $scan['types']['article'];
    $this->info("Scanned {$a['total']} articles and {$scan['types']['service']['total']} services; " . count($scan['plan']['articles']) . ' articles planned for this week.');
    if ($this->option('email')) {
        $to = \App\Support\ArticleNotifier::publishers();
        \Illuminate\Support\Facades\Notification::send($to, new \App\Notifications\WeeklyContentPlan($scan));
        $this->info('Emailed the plan to ' . $to->count() . ' people.');
    }
})->purpose('Scan every article and service and build the content plan shown in Admin → Writing guide');

// Every morning: fresh scores and plan in the Writing guide. Friday: the plan is also emailed.
Schedule::call(fn () => Artisan::call('content:scan'))->dailyAt('05:30')->name('content:scan')->withoutOverlapping(60);
Schedule::call(fn () => Artisan::call('content:scan', ['--email' => true]))->weeklyOn(5, '08:30')->name('content:scan-email')->withoutOverlapping(60);

// Lets Admin → System show whether the server cron is running.
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::forever('system.scheduler_seen', time()))->everyMinute()->name('scheduler-heartbeat');

/*
 * php artisan site:hidden-admin maintenance@example.com
 * Creates (or gives a new password to) the maintenance account: a Super Admin that is not listed in
 * Users, role counts, notifications or the team page, and that other users cannot change or delete.
 * The password is shown once here and stored nowhere else (or, with --link, emailed as a
 * set-password link so nobody sees it). Every sign-in is logged.
 */
Artisan::command('site:hidden-admin {email} {--name=System maintenance} {--remove} {--link : Email a link to set the password instead of showing one}', function (string $email) {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $this->error('That is not an email address.');
    }
    $user = \App\Models\User::where('email', $email)->first();

    if ($this->option('remove')) {
        if (!$user || !$user->is_hidden) {
            return $this->error('No hidden account with that email.');
        }
        $user->syncRoles([]);
        $user->delete();
        \Illuminate\Support\Facades\Log::warning('Hidden maintenance account removed', ['email' => $email]);

        return $this->info('Removed.');
    }
    if ($user && !$user->is_hidden) {
        return $this->error('A normal user already has that email. Use another address for the maintenance account.');
    }

    $password = \Illuminate\Support\Str::password(20, symbols: false);
    $user ??= new \App\Models\User(['email' => $email]);
    $user->forceFill(['name' => $this->option('name'), 'password' => $password, 'is_active' => true, 'is_hidden' => true, 'email_verified_at' => now()])->save();
    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => config('admin.super_role'), 'guard_name' => 'web']);
    $user->syncRoles([$role]);
    \Illuminate\Support\Facades\Log::warning('Hidden maintenance account created or its password changed', ['email' => $email]);

    if ($this->option('link')) {
        // Nobody sees a password: the owner of the mailbox sets one from the emailed link.
        $status = \Illuminate\Support\Facades\Password::broker()->sendResetLink(['email' => $email]);

        return $status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT
            ? $this->info("Account ready. A link to set the password was emailed to {$email}.")
            : $this->warn("Account ready, but the email was not sent ({$status}). Use \"Forgot password\" on the login page.");
    }

    $this->newLine();
    $this->line("  Email:    {$email}");
    $this->line("  Password: {$password}");
    $this->newLine();
    $this->warn('Shown only now. Save it in a password manager. Run this command again to set a new one.');
})->purpose('Create or reset the hidden maintenance (Super Admin) account');
