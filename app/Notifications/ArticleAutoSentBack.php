<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the hidden admin: an article or a change was sent back automatically 10 minutes after the approval email (App\Support\AutoApprove). */
class ArticleAutoSentBack extends Notification
{
    use Queueable;

    public function __construct(public string $title, public string $kind, public ?string $by, public string $note, public string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = self::brand();
        $subject = "Sent back automatically: {$this->title}";

        return (new MailMessage)
            ->subject($subject . ' · ' . $brand)
            ->view(['emails.notice', 'emails.notice-text'], [
                'preheader' => $subject,
                'badge' => 'Articles',
                'title' => $subject,
                'greeting' => 'Hi ' . strtok((string) $notifiable->name, ' ') . ',',
                'lines' => [
                    '“' . $this->title . '” (' . $this->kind . ($this->by ? ', sent by ' . $this->by : '') . ') was not approved within '
                        . \App\Support\AutoApprove::MINUTES . ' minutes and is not ready to publish, so it was sent back to the writer on behalf of the Super Admin.',
                    'The writer got this note with what to fix:',
                ],
                'quoteLabel' => 'Note sent to the writer',
                'quote' => $this->note,
                'seo' => null,
                'buttons' => [['label' => 'Open the article', 'url' => $this->url]],
                'after' => ['Nothing changed on the website. When the writer fixes it and submits again, it is reviewed again the same way.'],
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
