<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One notification for every step of the article workflow. Each event explains
 * what happened, what happens next and who has to do what, so nobody has to guess.
 *
 * $event: submitted | approved | scheduled | published | rejected
 *         | revision_submitted | revision_approved | revision_rejected
 */
class ArticleWorkflow extends Notification
{
    use Queueable;

    public function __construct(
        public string $event,
        public string $title,
        public string $url,
        public ?string $actor = null,
        public ?string $note = null,
        public ?string $when = null,
        public ?string $publicUrl = null,
        public ?array $seo = null,
    ) {}

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->content();
        $lines = $c['lines'];
        $quote = null;
        if ($this->note) {
            $quote = trim($this->note);
        }
        $after = $c['next'];
        if ($this->publicUrl && in_array($this->event, ['approved', 'published', 'revision_approved'], true)) {
            $after[] = 'Live page: ' . $this->publicUrl;
        }

        return (new MailMessage)
            ->subject($c['subject'] . ' · ' . self::brand())
            ->view(['emails.notice', 'emails.notice-text'], [
                'preheader' => $c['short'] ?? $c['subject'],
                'badge' => 'Articles',
                'title' => $c['subject'],
                'greeting' => 'Hi ' . strtok((string) $notifiable->name, ' ') . ',',
                'lines' => $lines,
                'quoteLabel' => $quote ? 'Note from ' . ($this->actor ?: 'the reviewer') : null,
                'quote' => $quote,
                'seo' => $this->seo,
                'buttons' => [['label' => $c['button'], 'url' => $this->url]],
                'after' => $after,
                'footer' => 'Sent by the ' . self::brand() . ' website admin.',
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

    public function toArray(object $notifiable): array
    {
        $c = $this->content();

        return [
            'event' => $this->event,
            'title' => $c['short'],
            'body' => ($this->note ?: $this->title) . ($this->seo ? ' · SEO ' . $this->seo['score'] . '/100' : ''),
            'url' => $this->url,
            'level' => in_array($this->event, ['rejected', 'revision_rejected'], true) ? 'danger'
                : (in_array($this->event, ['submitted', 'revision_submitted'], true) ? 'warning' : 'success'),
        ];
    }

    private function content(): array
    {
        $t = "“{$this->title}”";
        $by = $this->actor ?: 'A team member';

        return match ($this->event) {
            'submitted' => [
                'subject' => "Approval needed: {$this->title}",
                'short' => "{$by} submitted an article for approval",
                'lines' => [
                    "{$by} has finished {$t} and submitted it for approval.",
                    $this->when
                        ? "Requested publish time: **{$this->when}**. If you approve before then, it goes live automatically at that time."
                        : 'Requested publish time: as soon as it is approved.',
                ],
                'button' => 'Review the article',
                'next' => [
                    'What you can do: **Approve** (publishes now or at the scheduled time; you can change the time), or **Send back** with a note explaining what to fix.',
                    'Nothing is visible on the website until you approve it.',
                ],
            ],
            'approved' => [
                'subject' => "Published: {$this->title}",
                'short' => 'Your article is live',
                'lines' => ["Good news: {$by} approved {$t} and it is now live on the website."],
                'button' => 'Open the article in admin',
                'next' => ['It is now in the sitemap, so Google can find it. If you edit it later, your changes go to approval again and the live page stays as it is until they are approved.'],
            ],
            'scheduled' => [
                'subject' => "Approved and scheduled: {$this->title}",
                'short' => "Approved, goes live {$this->when}",
                'lines' => [
                    "{$by} approved {$t}.",
                    "It is scheduled to go live automatically on **{$this->when}**. You don't need to do anything else.",
                ],
                'button' => 'Open the article in admin',
                'next' => ['Until then it is not visible on the website. You will get another email the moment it is published.'],
            ],
            'published' => [
                'subject' => "Now live: {$this->title}",
                'short' => 'Scheduled article is now live',
                'lines' => ["{$t} reached its scheduled time and is now live on the website."],
                'button' => 'Open the article in admin',
                'next' => ['It has been added to the sitemap so search engines can pick it up.'],
            ],
            'rejected' => [
                'subject' => "Changes needed: {$this->title}",
                'short' => 'Your article was sent back',
                'lines' => ["{$by} reviewed {$t} and sent it back to you. It is not published and is back in your drafts."],
                'button' => 'Open and fix the article',
                'next' => ['How to continue: open the article, make the changes from the note, then click **Submit for approval** again. You can keep the same publish time or choose a new one.'],
            ],
            'revision_submitted' => [
                'subject' => "Approval needed: changes to {$this->title}",
                'short' => "{$by} edited a live article",
                'lines' => [
                    "{$by} made changes to the published article {$t}.",
                    'The live page has **not** changed. The edits wait for your approval.',
                ],
                'button' => 'Review the changes',
                'next' => ['Approve to replace the live content with the new version, or reject with a note.'],
            ],
            'revision_approved' => [
                'subject' => "Changes are live: {$this->title}",
                'short' => 'Your changes are live',
                'lines' => ["{$by} approved your changes to {$t}. The live page now shows the new version."],
                'button' => 'Open the article in admin',
                'next' => [],
            ],
            'revision_rejected' => [
                'subject' => "Changes not approved: {$this->title}",
                'short' => 'Your changes were not approved',
                'lines' => ["{$by} did not approve your changes to {$t}. The live page stays as it was."],
                'button' => 'Open the article',
                'next' => ['Read the note, edit the article again and resubmit your changes.'],
            ],
        };
    }
}
