<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To the hidden Super Admin (the owner's mailbox): an article or a change was approved or sent back,
 * by a person or automatically (App\Support\AutoApprove), with the note the writer got.
 *
 * $decision: approved | scheduled | sent_back | change_approved | change_rejected
 */
class ArticleDecision extends Notification
{
    use Queueable;

    public function __construct(
        public string $decision,
        public string $title,
        public ?string $writer,
        public ?string $actor,
        public bool $auto,
        public string $url,
        public ?string $note = null,
        public ?string $publicUrl = null,
        public ?string $when = null,
    ) {}

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = self::brand();
        $t = '“' . $this->title . '”';
        $who = $this->auto ? 'automatically, on behalf of the Super Admin' : 'by ' . ($this->actor ?: 'an approver');
        $from = $this->writer ? " (written by {$this->writer})" : '';
        $why = $this->auto ? ($this->sentBack() ? ', because it is not ready to publish' : ', because Content quality is 100/100') : '';

        [$subject, $line] = match ($this->decision) {
            'approved' => ["Published: {$this->title}", "The article {$t}{$from} was approved {$who}{$why}. It is live now."],
            'scheduled' => ["Approved and scheduled: {$this->title}", "The article {$t}{$from} was approved {$who}{$why}. It goes live on **{$this->when}**."],
            'change_approved' => ["Change is live: {$this->title}", "A change to the live article {$t}{$from} was approved {$who}{$why}. The live page shows the new version."],
            'change_rejected' => ["Change rejected: {$this->title}", "A change to the live article {$t}{$from} was rejected {$who}{$why}. The live page stays as it was."],
            default => ["Sent back: {$this->title}", "The article {$t}{$from} was sent back to the writer {$who}{$why}. Nothing is published."],
        };
        if ($this->auto) {
            $subject .= ' (automatic)';
        }
        $after = [];
        if ($this->publicUrl && !$this->sentBack()) {
            $after[] = 'Live page: ' . $this->publicUrl;
        }
        if ($this->sentBack()) {
            $after[] = 'When the writer fixes it and submits again, it is reviewed again the same way.';
        }

        return (new MailMessage)
            ->subject($subject . ' · ' . $brand)
            ->view(['emails.notice', 'emails.notice-text'], [
                'preheader' => $subject,
                'badge' => 'Articles',
                'title' => $subject,
                'greeting' => 'Hi ' . strtok((string) $notifiable->name, ' ') . ',',
                'lines' => [$line],
                'quoteLabel' => $this->note ? 'Note sent to the writer' : null,
                'quote' => $this->note,
                'seo' => null,
                'buttons' => [['label' => 'Open the article', 'url' => $this->url]],
                'after' => $after,
                'footer' => 'Sent by the ' . $brand . ' website admin.',
            ]);
    }

    private function sentBack(): bool
    {
        return in_array($this->decision, ['sent_back', 'change_rejected'], true);
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
