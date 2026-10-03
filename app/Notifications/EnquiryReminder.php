<?php

namespace App\Notifications;

use App\Support\Enquiries;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "No reply yet": new enquiries that have waited more than Enquiries::REMIND_AFTER_HOURS. */
class EnquiryReminder extends Notification
{
    use Queueable;

    /** @param list<\App\Models\Message> $messages */
    public function __construct(public array $messages) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = self::brand();
        $n = count($this->messages);
        $subject = $n === 1 ? '1 enquiry has no reply yet' : "{$n} enquiries have no reply yet";
        $lines = ['These came in more than ' . Enquiries::REMIND_AFTER_HOURS . ' hours ago and are still marked “New”. Most customers ask two or three companies: the first to reply usually gets the job.'];
        foreach (array_slice($this->messages, 0, 10) as $m) {
            $lines[] = '• **' . ($m->name ?: 'No name') . '**' . ($m->phone ? ' · ' . $m->phone : '') . ' · ' . ($m->subject ?: 'Website enquiry')
                . ' · ' . $m->created_at->diffForHumans(null, true) . ' ago';
        }

        return (new MailMessage)
            ->subject($subject . ' · ' . $brand)
            ->view(['emails.notice', 'emails.notice-text'], [
                'preheader' => $subject,
                'badge' => 'Enquiries',
                'title' => $subject,
                'greeting' => 'Hi ' . strtok((string) $notifiable->name, ' ') . ',',
                'lines' => $lines,
                'quoteLabel' => null,
                'quote' => null,
                'seo' => null,
                'buttons' => [['label' => 'Open enquiries', 'url' => url('/admin/messages?status=new')]],
                'after' => ['Reply from the enquiry (WhatsApp or email) and it is marked “Contacted”, so this reminder stops. Each enquiry is reminded once.'],
                'footer' => 'Sent by the ' . $brand . ' website admin.',
            ]);
    }

    private static function brand(): string
    {
        try {
            return \App\Models\SiteSetting::get('business.brand_name') ?: config('app.name');
        } catch (\Throwable) {
            return config('app.name');
        }
    }
}
