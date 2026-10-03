<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Source check found sentences of an article waiting for approval on other websites (App\Support\SourceCheck). */
class SourceCheckFound extends Notification
{
    use Queueable;

    /** @param array{total: int, found: int, matches: list<array{sentence: string, urls: list<string>}>} $result */
    public function __construct(public string $title, public string $kind, public ?string $by, public array $result) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = self::brand();
        $subject = "Source check: {$this->result['found']} of {$this->result['total']} sentences found online";

        $lines = [
            '“' . $this->title . '” (' . $this->kind . ($this->by ? ', sent by ' . $this->by : '') . ') has sentences that are already on other websites. It may be copied. Check the pages below before approving.',
        ];
        foreach (array_slice($this->result['matches'], 0, 4) as $m) {
            $lines[] = '• “' . $m['sentence'] . '” — ' . implode(', ', array_map(fn ($u) => parse_url($u, PHP_URL_HOST) ?: $u, $m['urls']));
        }

        return (new MailMessage)
            ->subject($subject . ' · ' . $brand)
            ->view(['emails.notice', 'emails.notice-text'], [
                'preheader' => $subject,
                'badge' => 'Articles',
                'title' => $subject,
                'greeting' => 'Hi ' . strtok((string) $notifiable->name, ' ') . ',',
                'lines' => $lines,
                'quoteLabel' => null,
                'quote' => null,
                'seo' => null,
                'buttons' => [['label' => 'Open the review queue', 'url' => url('/admin/blogs?tab=review')]],
                'after' => ['A short common phrase can match by chance. Whole sentences on other sites usually mean the text was copied: send it back or rewrite it.'],
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
