<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the owner of the account that the password was just changed through a reset link. */
class AdminPasswordChanged extends Notification
{
    public function __construct(public ?string $ip = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = now()->timezone(config('admin.timezone'))->format('j M Y, g:i a');

        return (new MailMessage)
            ->subject('Your admin password was changed')
            ->view(['emails.notice', 'emails.notice-text'], [
                'preheader' => "Your password was changed on {$when}.",
                'badge' => 'Security',
                'title' => 'Your password was changed',
                'greeting' => 'Hi ' . $notifiable->name . ',',
                'lines' => ['The password for your website admin account was just changed with a reset link. Devices that were kept signed in have been signed out.'],
                'fields' => array_values(array_filter([
                    ['When', $when],
                    $this->ip ? ['From IP', $this->ip] : null,
                ])),
                'buttons' => [['label' => 'Sign in', 'url' => url(route('admin.login', [], false))]],
                'after' => ['If this was not you, ask your Super Admin to lock the account and reset the password right away.'],
                'footer' => 'Security notice for your admin account.',
            ]);
    }
}
