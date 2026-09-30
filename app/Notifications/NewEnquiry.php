<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A customer sent the contact/quote form: email the business and ping the team's bell. */
class NewEnquiry extends Notification
{
    public function __construct(public Message $message) {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = $this->message;
        $digits = preg_replace('/\D/', '', (string) $m->phone);
        if (strlen($digits) === 8) {
            $digits = '65' . $digits;
        }
        $open = url('/admin/messages?open=' . $m->id);
        $buttons = array_values(array_filter([
            $digits ? ['label' => 'Reply on WhatsApp', 'url' => 'https://wa.me/' . $digits, 'style' => 'whatsapp'] : null,
            ['label' => 'Open in admin', 'url' => $open, 'style' => $digits ? 'secondary' : 'primary'],
        ]));

        return (new MailMessage)
            ->subject('New enquiry: ' . ($m->subject ?: 'Website') . ' · ' . $m->name)
            ->replyTo($m->email, $m->name)
            ->view(['emails.notice', 'emails.notice-text'], [
                'preheader' => \Illuminate\Support\Str::limit(trim((string) $m->message), 110),
                'badge' => 'New enquiry',
                'title' => $m->name . ' sent an enquiry',
                'fields' => array_values(array_filter([
                    ['Name', $m->name],
                    $m->phone ? ['Phone', \App\Http\Middleware\HandleInertiaRequests::formatPhone($m->phone)[0] ?: $m->phone, 'tel:+' . $digits] : null,
                    ['Email', $m->email, 'mailto:' . $m->email],
                    ['About', $m->subject ?: 'Website enquiry'],
                    ['Received', $m->created_at?->timezone(config('admin.timezone'))->format('j M Y, g:i a')],
                ])),
                'quoteLabel' => 'Message',
                'quote' => trim((string) $m->message),
                'buttons' => $buttons,
                'after' => ['Reply fast: most customers ask two or three companies. Replying to this email answers the customer directly.'],
                // Opening this email marks the enquiry as read in the admin (when images are shown).
                'pixel' => \Illuminate\Support\Facades\URL::signedRoute('mail.seen', ['message' => $m->id]),
                'footer' => 'You get this because this address receives website enquiries.',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'enquiry',
            'title' => 'New enquiry from ' . $this->message->name,
            'body' => $this->message->subject ?: \Illuminate\Support\Str::limit($this->message->message, 80),
            'url' => url('/admin/messages'),
            'level' => 'warning',
        ];
    }
}
