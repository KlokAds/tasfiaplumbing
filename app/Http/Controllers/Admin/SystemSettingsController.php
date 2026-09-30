<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class SystemSettingsController extends Controller
{
    public function index(Request $request)
    {
        $geo = SystemSettings::geo();
        $seen = Cache::get('system.scheduler_seen');

        return Inertia::render('Admin/System/Settings', [
            'status' => [
                'maintenance' => SystemSettings::maintenanceOn(),
                'maintenance_message' => SiteSetting::get('system.maintenance_message'),
                'maintenance_back' => SiteSetting::get('system.maintenance_back'),
                'app_url' => SiteSetting::get('system.app_url'),
                'env' => SiteSetting::get('system.env'),
                'env_file' => (string) env('APP_ENV', 'production'),
                'debug_until' => SystemSettings::debugUntil() ? date(DATE_ATOM, SystemSettings::debugUntil()) : null,
                'debug_minutes' => SystemSettings::DEBUG_MINUTES,
            ],
            'mail' => SystemSettings::mail() + ['env_mailer' => env('MAIL_MAILER', 'log')],
            'geo' => [
                'mode' => $geo['mode'],
                'countries' => implode(', ', $geo['countries']),
                'message' => $geo['message'],
                'your_country' => SystemSettings::country($request),
            ],
            'server' => [
                'environment' => app()->environment(),
                'env_debug' => (bool) env('APP_DEBUG'),
                'https' => $request->isSecure() || str_starts_with((string) config('app.url'), 'https://'),
                'app_url' => config('app.url'),
                'app_name' => config('app.name'),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'upload_max' => ini_get('upload_max_filesize'),
                'post_max' => ini_get('post_max_size'),
                'memory' => ini_get('memory_limit'),
                'scheduler_seen' => $seen ? date(DATE_ATOM, (int) $seen) : null,
                'queue' => config('queue.default'),
                'storage_writable' => is_writable(storage_path('app')) && is_writable(public_path()),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $section = $request->validate(['section' => 'required|in:status,mail,geo'])['section'];

        if ($section === 'status') {
            $data = $request->validate([
                'maintenance' => 'boolean',
                'maintenance_message' => 'nullable|string|max:300',
                'maintenance_back' => 'nullable|string|max:60',
                'app_url' => ['nullable', 'url', 'max:190', 'regex:#^https?://[^/]+/?$#'],
                'env' => 'nullable|in:production,local',
            ], ['app_url.regex' => 'Only the domain, e.g. https://tasfiaplumbing.sg (no page path).']);
            SiteSetting::putMany([
                'system.maintenance' => (bool) ($data['maintenance'] ?? false),
                'system.maintenance_message' => $data['maintenance_message'] ?? '',
                'system.maintenance_back' => $data['maintenance_back'] ?? '',
                'system.app_url' => rtrim((string) ($data['app_url'] ?? ''), '/'),
                'system.env' => $data['env'] ?? '',
            ]);
            Log::warning('Maintenance mode ' . (($data['maintenance'] ?? false) ? 'ON' : 'OFF'), ['user' => $request->user()->id]);

            return back()->with('success', ($data['maintenance'] ?? false)
                ? 'Maintenance mode is ON. Visitors see the "back soon" page; you and your team still see the site.'
                : 'Maintenance mode is off. The website is live.');
        }

        if ($section === 'mail') {
            $data = $request->validate([
                'enabled' => 'boolean',
                'host' => 'nullable|required_if:enabled,true|string|max:190',
                'port' => 'nullable|required_if:enabled,true|integer|between:1,65535',
                'encryption' => 'required|in:tls,ssl,none',
                'username' => 'nullable|string|max:190',
                'password' => 'nullable|string|max:500',
                'from_address' => 'nullable|required_if:enabled,true|email|max:190',
                'from_name' => 'nullable|string|max:120',
            ]);
            $values = [
                'mail.enabled' => (bool) ($data['enabled'] ?? false),
                'mail.host' => trim((string) ($data['host'] ?? '')),
                'mail.port' => (string) ($data['port'] ?? ''),
                'mail.encryption' => $data['encryption'],
                'mail.username' => trim((string) ($data['username'] ?? '')),
                'mail.from_address' => trim((string) ($data['from_address'] ?? '')),
                'mail.from_name' => trim((string) ($data['from_name'] ?? '')),
            ];
            // Blank password = keep the saved one. The password is stored encrypted.
            if (filled($data['password'] ?? null)) {
                $values['mail.password'] = Crypt::encryptString($data['password']);
            }
            SiteSetting::putMany($values);

            return back()->with('success', 'Email settings saved. Send a test email to check them.');
        }

        $data = $request->validate([
            'mode' => 'required|in:off,block,allow',
            'countries' => 'nullable|required_unless:mode,off|string|max:1000',
            'message' => 'nullable|string|max:300',
        ]);
        $countries = SystemSettings::parseCountries((string) ($data['countries'] ?? ''));
        // Never let the owner lock themselves out from the country they are in right now.
        $mine = SystemSettings::country($request);
        if ($mine && $data['mode'] === 'allow' && !in_array($mine, $countries, true)) {
            $countries[] = $mine;
        }
        if ($mine && $data['mode'] === 'block') {
            $countries = array_values(array_diff($countries, [$mine]));
        }
        SiteSetting::putMany([
            'geo.mode' => $data['mode'],
            'geo.countries' => implode(', ', $countries),
            'geo.message' => $data['message'] ?? '',
        ]);

        return back()->with('success', $data['mode'] === 'off' ? 'Country access: everyone can open the site.' : 'Country access saved.');
    }

    /** Shows the owner exactly what visitors get while offline / blocked. */
    public function preview(Request $request)
    {
        $mode = $request->query('mode') === 'blocked' ? 'blocked' : 'maintenance';

        return response()->view('errors.site-status', ['mode' => $mode] + SystemSettings::pageDetails());
    }

    public function debug(Request $request)
    {
        $on = $request->validate(['on' => 'required|boolean'])['on'];
        SiteSetting::putMany(['system.debug_until' => $on ? (string) (time() + SystemSettings::DEBUG_MINUTES * 60) : '']);
        Log::warning('Debug mode ' . ($on ? 'ON' : 'OFF') . ' from admin', ['user' => $request->user()->id]);

        return back()->with('success', $on
            ? 'Debug mode is on for ' . SystemSettings::DEBUG_MINUTES . ' minutes, only for signed-in team members. It switches itself off.'
            : 'Debug mode is off.');
    }

    public function testMail(Request $request)
    {
        $to = $request->validate(['to' => 'required|email'])['to'];
        SystemSettings::applyMail();

        try {
            Mail::send(['emails.notice', 'emails.notice-text'], [
                'badge' => 'Test email',
                'title' => 'Your email settings work',
                'lines' => ['If you can read this, enquiry alerts and article notifications will reach you.', 'Sent through: **' . config('mail.mailers.smtp.host', 'server') . '**'],
                'buttons' => [['label' => 'Open the admin', 'url' => url('/admin/dashboard')]],
            ], fn ($m) => $m->to($to)->subject('Test email from ' . (SiteSetting::get('business.brand_name') ?: config('app.name'))));
        } catch (\Throwable $e) {
            Log::error('Test email failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Email could not be sent: ' . \Illuminate\Support\Str::limit($e->getMessage(), 220));
        }

        $mailer = config('mail.default');
        Log::info('Test email sent', ['to' => $to, 'mailer' => $mailer, 'by' => $request->user()?->id]);

        return back()->with('success', $mailer === 'smtp'
            ? "Test email sent to {$to}. Check the inbox (and spam folder)."
            : "The server is using the \"{$mailer}\" mailer, so no real email was sent. Turn on SMTP above to send real emails.");
    }
}
