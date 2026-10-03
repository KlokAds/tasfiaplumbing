<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** An API key, token or password was saved, changed or removed in System → API keys (security alert to the hidden admin). */
class ApiKeyChanged extends Notification
{
    use Queueable;

    public function __construct(public string $label, public string $action, public string $by, public ?string $ip, public ?string $last4) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = self::brand();
        $subject = $this->label . ' ' . $this->action;
        $time = now()->timezone(config('admin.timezone'))->format('D j M Y, g:i A') . ' (' . config('admin.timezone_label') . ')';

        return (new MailMessage)
            ->subject($subject . ' · ' . $brand)
            ->view(['emails.notice', 'emails.notice-text'], [
                'preheader' => $subject,
                'badge' => 'Security',
                'title' => $subject,
                'greeting' => 'Hi ' . strtok((string) $notifiable->name, ' ') . ',',
                'lines' => array_values(array_filter([
                    "The {$this->label} of the website was {$this->action} by {$this->by}.",
                    $this->last4 ? "The new one ends in ••••{$this->last4}." : null,
                    "When: {$time}" . ($this->ip ? " · IP {$this->ip}" : '') . '.',
                ])),
                'quoteLabel' => null,
                'quote' => null,
                'seo' => null,
                'buttons' => [['label' => 'Open API keys', 'url' => url('/admin/system/settings?tab=keys')]],
                'after' => ['If this was not you or your team, sign in, save your own key again in System → API keys and change the passwords of your admin accounts.'],
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
