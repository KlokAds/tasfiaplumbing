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

        $mail = (new MailMessage)
            ->subject('New enquiry: ' . ($m->subject ?: 'Website') . ' · ' . $m->name);
        // Email is optional on the form: replying to this email reaches the customer only when they gave one.
        if ($m->email) {
            $mail->replyTo($m->email, $m->name);
        }

        return $mail
            ->view(['emails.notice', 'emails.notice-text'], [
                'preheader' => \Illuminate\Support\Str::limit(trim((string) $m->message), 110),
                'badge' => 'New enquiry',
                'title' => $m->name . ' sent an enquiry',
                'fields' => array_values(array_filter([
                    ['Name', $m->name],
                    $m->phone ? ['Phone', \App\Http\Middleware\HandleInertiaRequests::formatPhone($m->phone)[0] ?: $m->phone, 'tel:+' . $digits] : null,
                    $m->email ? ['Email', $m->email, 'mailto:' . $m->email] : null,
                    ['About', $m->subject ?: 'Website enquiry'],
                    $m->wants_visit ? ['Free site visit', $m->visit_label] : null,
                    $m->postal_code ? ['Postal code', $m->postal_code] : null,
                    $m->property ? ['Property', $m->property] : null,
                    $m->photos ? ['Photos', count($m->photos) . ' (open in admin)', $open] : null,
                    ['Received', $m->created_at?->timezone(config('admin.timezone'))->format('j M Y, g:i a')],
                ])),
                'quoteLabel' => 'Message',
                'quote' => trim((string) $m->message),
                'buttons' => $buttons,
                'after' => [$m->email ? 'Reply fast: most customers ask two or three companies. Replying to this email answers the customer directly.' : 'Reply fast: most customers ask two or three companies. The customer left no email, so reply on WhatsApp or call.'],
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
