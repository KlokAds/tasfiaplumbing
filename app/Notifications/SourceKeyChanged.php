<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The Brave Search key of the source check was saved, changed or removed (security alert to the hidden admin). */
class SourceKeyChanged extends Notification
{
    use Queueable;

    public function __construct(public string $action, public string $by, public ?string $ip, public ?string $last4) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = self::brand();
        $subject = 'Source check key ' . $this->action;
        $time = now()->timezone(config('admin.timezone'))->format('D j M Y, g:i A') . ' (' . config('admin.timezone_label') . ')';

        return (new MailMessage)
            ->subject($subject . ' · ' . $brand)
            ->view(['emails.notice', 'emails.notice-text'], [
                'preheader' => $subject,
                'badge' => 'Security',
                'title' => $subject,
                'greeting' => 'Hi ' . strtok((string) $notifiable->name, ' ') . ',',
                'lines' => array_values(array_filter([
                    "The Brave Search key used by the article source check was {$this->action} by {$this->by}.",
                    $this->last4 ? "The new key ends in ••••{$this->last4}." : null,
                    "When: {$time}" . ($this->ip ? " · IP {$this->ip}" : '') . '.',
                ])),
                'quoteLabel' => null,
                'quote' => null,
                'seo' => null,
                'buttons' => [['label' => 'Open Articles', 'url' => url('/admin/blogs')]],
                'after' => ['If this was not you or your team, sign in, save your own key again and change the passwords of your admin accounts.'],
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
