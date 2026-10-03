<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Down" / "Working again" from the website monitor (App\Support\SiteMonitor). Needs no database. */
class SiteMonitorAlert extends Notification
{
    use Queueable;

    public function __construct(public string $subject, public array $lines) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = (string) config('app.name');

        return (new MailMessage)
            ->subject($this->subject . ' · ' . $brand)
            ->view(['emails.notice', 'emails.notice-text'], [
                'preheader' => $this->subject,
                'badge' => 'Website monitor',
                'title' => $this->subject,
                'greeting' => 'Hi,',
                'lines' => $this->lines,
                'quoteLabel' => null,
                'quote' => null,
                'seo' => null,
                'buttons' => [],
                'after' => ['The four websites check each other every 5 minutes.'],
                'footer' => 'Sent by the ' . $brand . ' website monitor.',
            ]);
    }
}
