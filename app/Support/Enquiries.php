<?php

namespace App\Support;

use App\Models\Message;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\EnquiryReminder;
use Illuminate\Support\Facades\Log;

/**
 * Enquiry follow-up: the status of each enquiry, ready replies for WhatsApp and email, and the
 * reminder when a new enquiry has had no reply for REMIND_AFTER_HOURS (sent between 8 am and 9 pm).
 */
class Enquiries
{
    public const STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'quoted' => 'Quoted', 'won' => 'Won', 'lost' => 'Lost'];

    public const REMIND_AFTER_HOURS = 2;

    /** Ready replies; {name}, {subject} and {brand} are filled in. Editable on the Enquiries page. */
    public const DEFAULT_TEMPLATES = [
        ['name' => 'Thanks, we will call', 'text' => "Hi {name}, thank you for contacting {brand}. We received your message about {subject} and will call you shortly."],
        ['name' => 'Ask for photos', 'text' => "Hi {name}, thank you for your enquiry about {subject}. Could you send us a few photos here on WhatsApp? Then we can tell you the price before we come."],
        ['name' => 'Book a visit', 'text' => "Hi {name}, we can come to check the {subject}. Which day and time suit you, and what is the address?"],
    ];

    /** @return list<array{name: string, text: string}> */
    public static function templates(): array
    {
        $saved = json_decode((string) SiteSetting::stored('enquiries.templates'), true);

        return is_array($saved) && $saved ? array_values($saved) : self::DEFAULT_TEMPLATES;
    }

    /** New enquiries without a reply for REMIND_AFTER_HOURS: one email to the team. Returns how many were listed. */
    public static function remind(): int
    {
        $hour = (int) now(config('admin.timezone'))->format('G');
        if ($hour < 8 || $hour >= 21) {
            return 0; // not at night; the morning run picks them up
        }
        $due = Message::where('status', 'new')->where('is_spam', false)->whereNull('reminded_at')
            ->whereBetween('created_at', [now()->subDays(7), now()->subHours(self::REMIND_AFTER_HOURS)])
            ->orderBy('created_at')->get();
        if ($due->isEmpty()) {
            return 0;
        }

        $to = User::where('is_active', true)->whereNotNull('email')->get()->filter(fn ($u) => $u->can('enquiries.view'));
        foreach ($to as $user) {
            try {
                $user->notify(new EnquiryReminder($due->all()));
            } catch (\Throwable $e) {
                Log::warning('Enquiry reminder email failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            }
        }
        Message::whereKey($due->modelKeys())->update(['reminded_at' => now()]);

        return $due->count();
    }

    /** A Singapore phone number for WhatsApp (65XXXXXXXX), or null. */
    public static function whatsappNumber(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (preg_match('/^[3689]\d{7}$/', $digits)) {
            return '65' . $digits;
        }

        return preg_match('/^\d{8,15}$/', $digits) ? $digits : null;
    }
}
