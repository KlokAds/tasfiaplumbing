<?php

namespace App\Notifications;

use App\Support\VisitorStats;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The 6 pm summary of the website's own visitor counter: today so far, compared with yesterday. */
class DailyVisitorReport extends Notification
{
    use Queueable;

    /** @param array $today VisitorStats::summary() for today, @param array $yesterday the totals of yesterday */
    public function __construct(public string $date, public array $today, public array $yesterday) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = self::brand();
        $t = $this->today['totals'];
        $y = $this->yesterday;
        $subject = "Today: {$t['visitors']} visitors, " . ($t['whatsapp'] + $t['call'] + $t['chat']) . ' contacts';
        $vs = fn (string $k) => ' (yesterday ' . ($y[$k] ?? 0) . ')';

        $lines = [
            "The website's own count for {$this->date}, from midnight to 6 pm (" . config('admin.timezone_label') . '). Real visitors only: bots and signed-in admins are not counted.',
            '**Visitors:** ' . $t['visitors'] . $vs('visitors') . ' · **Page views:** ' . $t['visit'] . $vs('visit'),
            '**WhatsApp clicks:** ' . $t['whatsapp'] . $vs('whatsapp') . ' · **Call clicks:** ' . $t['call'] . $vs('call'),
            '**Chats started (Tawk.to):** ' . $t['chat'] . $vs('chat') . ' · **Chat messages:** ' . $t['chat_message'] . $vs('chat_message'),
        ];
        if ($this->today['countries']) {
            $lines[] = '**Countries:** ' . collect($this->today['countries'])->take(6)
                ->map(fn ($c) => "{$c['name']} {$c['visitors']}" . ($c['contacts'] ? " ({$c['contacts']} contacts)" : ''))->implode(', ');
        }
        if ($this->today['pages']) {
            $lines[] = '**Most viewed:** ' . collect($this->today['pages'])->take(3)->map(fn ($p) => "{$p['path']} ({$p['views']})")->implode(', ');
        }
        if ($this->today['contactPages']) {
            $lines[] = '**Contacts came from:** ' . collect($this->today['contactPages'])->take(3)->map(fn ($p) => "{$p['path']} ({$p['contacts']})")->implode(', ');
        }

        return (new MailMessage)
            ->subject($subject . ' · ' . $brand)
            ->view(['emails.notice', 'emails.notice-text'], [
                'preheader' => $subject,
                'badge' => 'Visitors',
                'title' => $subject,
                'greeting' => 'Hi ' . strtok((string) $notifiable->name, ' ') . ',',
                'lines' => $lines,
                'quoteLabel' => null,
                'quote' => null,
                'seo' => null,
                'buttons' => [['label' => 'Open the visitor counter', 'url' => url('/admin/visitors?range=today')]],
                'after' => ['You get this every day at 6 pm. It can be switched off on the Visitor counter page.'],
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
