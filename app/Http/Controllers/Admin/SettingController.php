<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactContent;
use App\Models\Footer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function contactIndex()
    {
        $socials = [];
        foreach (\App\Support\Socials::PLATFORMS as $key => [$label, $setting, , $placeholder]) {
            $socials[] = ['key' => $key, 'label' => $label, 'placeholder' => $placeholder, 'value' => (string) \App\Models\SiteSetting::get($setting)];
        }

        return Inertia::render('Admin/Settings/Contact', [
            'contact' => ContactContent::first(),
            'socials' => $socials,
            'whatsapp' => (string) \App\Models\SiteSetting::get('social.whatsapp'),
        ]);
    }

    public function contactUpdate(Request $request)
    {
        $contact = ContactContent::firstOrNew();

        $validated = $request->validate([
            'title' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|string',
            'map' => 'nullable|string',
            'meta_title' => 'nullable|string',
            'meta_desc' => 'nullable|string',
            'meta_tag' => 'nullable|string',
        ]);

        $links = $request->validate([
            'socials' => 'nullable|array',
            'socials.*' => 'nullable|string|max:300',
            'whatsapp' => ['nullable', 'string', 'max:120', function ($attr, $value, $fail) {
                if (filled($value) && !preg_match('#wa\.me/\d{8,}#', $value) && strlen(preg_replace('/\D/', '', $value)) < 8) {
                    $fail('Enter a WhatsApp number such as +65 9373 0360, or leave it empty to use the phone number.');
                }
            }],
        ]);

        $settings = ['social.whatsapp' => trim((string) ($links['whatsapp'] ?? ''))];
        $errors = [];
        foreach (\App\Support\Socials::PLATFORMS as $key => [, $setting]) {
            try {
                $settings[$setting] = (string) \App\Support\Socials::normalize($key, $links['socials'][$key] ?? null);
            } catch (\InvalidArgumentException $e) {
                $errors["socials.{$key}"] = $e->getMessage();
            }
        }
        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        $contact->fill($validated);
        $contact->save();
        \App\Models\SiteSetting::putMany($settings);

        return redirect()->back()->with('success', 'Contact details and social profiles saved.');
    }

    public function footerIndex()
    {
        return Inertia::render('Admin/Settings/Footer', [
            'footer' => Footer::first(),
            'copyright' => \App\Http\Middleware\HandleInertiaRequests::copyright(Footer::first()?->c_text, \App\Models\SiteSetting::get('business.brand_name') ?: config('app.name')),
            'tracking' => [
                'tawk_enabled' => (bool) \App\Models\SiteSetting::get('tracking.tawk_enabled'),
                'tawk_id' => \App\Models\SiteSetting::get('tracking.tawk_id', ''),
                'events' => (bool) \App\Models\SiteSetting::get('tracking.events', '1'),
            ],
        ]);
    }

    public function footerUpdate(Request $request)
    {
        $footer = Footer::firstOrNew();

        $validated = $request->validate([
            'c_text' => 'nullable|string',
            'f_short_desc' => 'nullable|string',
            'g_tag' => ['nullable', 'string', 'regex:/^GTM-[A-Z0-9]+$/'],
            'g_a_tag' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]+$/'],
            'tawk_enabled' => 'boolean',
            'tawk_id' => ['nullable', 'string', 'max:120', 'regex:#^[a-z0-9]+/[a-z0-9]+$#i'],
            'events' => 'boolean',
            'main_logo' => 'nullable|image|max:5120',
            'f_logo' => 'nullable|image|max:5120',
        ], [
            'g_tag.regex' => 'A Tag Manager ID looks like GTM-ABC1234.',
            'g_a_tag.regex' => 'A GA4 ID looks like G-ABC123XYZ.',
            'tawk_id.regex' => 'Paste the part after embed.tawk.to/ — it looks like 64a1b2c3d4e5f6/1h2abc3de.',
        ]);

        if ($request->hasFile('main_logo')) {
            $footer->main_logo = $request->file('main_logo')->store('Admin/Logo/Header', 'uploads');
        }
        if ($request->hasFile('f_logo')) {
            $footer->f_logo = $request->file('f_logo')->store('Admin/Logo/Footer', 'uploads');
        }

        $footer->c_text = $validated['c_text'] ?? $footer->c_text;
        $footer->f_short_desc = $validated['f_short_desc'] ?? $footer->f_short_desc;
        // Empty means "remove": tracking codes can be switched off by clearing the field.
        $footer->g_tag = $validated['g_tag'] ?? null;
        $footer->g_a_tag = $validated['g_a_tag'] ?? null;
        $footer->save();

        \App\Models\SiteSetting::putMany([
            'tracking.tawk_enabled' => $request->boolean('tawk_enabled') ? '1' : '0',
            'tracking.tawk_id' => trim((string) ($validated['tawk_id'] ?? '')),
            'tracking.events' => $request->boolean('events') ? '1' : '0',
        ]);

        return redirect()->back()->with('success', 'Footer & tracking tag settings saved.');
    }
}
