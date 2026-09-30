<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A one-time link to choose a new admin password. */
class AdminPasswordReset extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('admin.password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()], false));
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset your admin password')
            ->view(['emails.notice', 'emails.notice-text'], [
                'preheader' => "Your password reset link works once and expires in {$minutes} minutes.",
                'badge' => 'Password reset',
                'title' => 'Choose a new password',
                'greeting' => 'Hi ' . $notifiable->name . ',',
                'lines' => ['Someone (hopefully you) asked to reset the password for your website admin account. Use the button below to choose a new one.'],
                'buttons' => [['label' => 'Reset password', 'url' => $url]],
                'after' => [
                    "The link works once and expires in {$minutes} minutes.",
                    'If you did not ask for this, ignore this email. Your password stays the same.',
                ],
                'footer' => 'You get this because a password reset was requested for this address.',
            ]);
    }
}
