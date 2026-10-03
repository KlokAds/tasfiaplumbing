<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // The /up health page also checks the database (the website monitor reads it).
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Foundation\Events\DiagnosingHealth::class, fn () => \Illuminate\Support\Facades\DB::select('select 1'));

        // SMTP details saved in Admin → System → Site settings override .env.
        // Skipped on a fresh upload with no database yet (the installer runs first).
        if (!\App\Http\Controllers\InstallController::notConfigured()) {
            \App\Support\SystemSettings::applyEnvironment();
            \App\Support\SystemSettings::applyMail();
            \App\Support\SystemSettings::applyUrl();
        }

        Gate::before(function (User $user) {
            try {
                return $user->isSuperAdmin() ? true : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }
}
