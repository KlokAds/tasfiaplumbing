<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Friday email with the coming week's content plan from the daily site scan (App\Support\ContentScan):
 * the articles to update, new article ideas, services to improve and other pages that need care.
 */
class WeeklyContentPlan extends Notification
{
    use Queueable;

    public function __construct(public array $scan) {}

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->scan['plan'] ?? [];
        $articles = $this->scan['types']['article'] ?? [];
        $fields = [];

        foreach ($plan['articles'] ?? [] as $a) {
            $fields[] = ['Update article', $a['name'] . ' (score ' . $a['score'] . ')', url($a['url'])];
        }
        foreach (array_slice($plan['services'] ?? [], 0, 3) as $s) {
            $fields[] = ['Improve service', $s['name'] . ' (score ' . $s['score'] . ')', url($s['url'])];
        }
        foreach (array_slice($plan['site'] ?? [], 0, 4) as $t) {
            $fields[] = [$t['level'] === 'must' ? 'Fix' : 'Check', $t['label'], url($t['url'])];
        }

        $lines = [
            'Here is what to work on next week, from the daily scan of the website. The pages that will gain the most come first.',
        ];
        if (!empty($articles['avg'])) {
            $lines[] = sprintf('**%s live articles** · average score %d (SEO %d, AEO %d, GEO %d, E-E-A-T %d).',
                number_format($articles['total']), $articles['avg']['score'], $articles['avg']['seo'], $articles['avg']['aeo'], $articles['avg']['geo'], $articles['avg']['eeat']);
        }

        $after = [];
        if ($ideas = array_slice($plan['ideas'] ?? [], 0, 3)) {
            $after[] = '**New article ideas** (people search for these, no page answers them yet): ' . implode('; ', array_map(fn ($i) => '"' . $i['query'] . '"', $ideas)) . '.';
        }
        $after[] = 'The full plan, with the reason for each item and how to fix it, is in Admin → Writing guide.';

        return (new MailMessage)
            ->subject('Next week on the website: ' . ($n = count($plan['articles'] ?? [])) . ' ' . ($n === 1 ? 'article' : 'articles') . ' to update · ' . self::brand())
            ->view(['emails.notice', 'emails.notice-text'], [
                'preheader' => 'Articles to update, services to improve and other pages to check next week.',
                'badge' => 'Weekly plan',
                'title' => 'Your website plan for next week',
                'greeting' => 'Hi ' . strtok((string) $notifiable->name, ' ') . ',',
                'lines' => $lines,
                'fields' => $fields,
                'buttons' => [['label' => 'Open the full plan', 'url' => url('/admin/seo/guide')]],
                'after' => $after,
                'footer' => 'Sent every Friday by the ' . self::brand() . ' website admin.',
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
