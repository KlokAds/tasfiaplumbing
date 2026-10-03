<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Still waiting for approval": the articles and changes that waited too long (App\Support\ReviewReminder). */
class PendingReviewReminder extends Notification
{
    use Queueable;

    /** @param array<int, array{kind: string, title: ?string, by: ?string, since: \Carbon\CarbonInterface}> $items */
    public function __construct(public array $items, public int $hours) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $n = count($this->items);
        $brand = self::brand();
        $subject = $n === 1 ? '1 article is still waiting for approval' : "{$n} articles are still waiting for approval";

        $lines = ["These have been waiting for more than {$this->hours} hours. The team cannot go further until someone approves, edits or sends them back."];
        foreach ($this->items as $i) {
            $lines[] = '• ' . ($i['title'] ?: 'Untitled') . ' — ' . $i['kind'] . ($i['by'] ? ', sent by ' . $i['by'] : '') . ', waiting ' . $i['since']->diffForHumans(null, true) . '.';
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
                'after' => ["You get this reminder at most once every {$this->hours} hours while something waits."],
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
