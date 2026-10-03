<?php

namespace App\Support;

use App\Models\Message;

/**
 * Is an enquiry spam? Points for what sales pitches and bots have in common; 3 points or more is spam.
 * Spam goes to the Spam folder without an email alert. Nothing is ever deleted, and "Not spam" brings
 * it back. A normal customer message ("my sliding door is stuck, Bedok, 9123 4567") scores 0.
 */
class SpamCheck
{
    public const LIMIT = 3;

    /** Sales pitches the business never needs. */
    private const PHRASES = [
        'mockup', 'mock-up', 'seo', 'search engine', 'google visibility', 'first page of google', 'rank higher', 'ranking', 'backlink',
        'guest post', 'writing needs', 'content writing', 'newsletter', 'web design', 'website design', 'app development',
        'digital marketing', 'marketing services', 'more leads', 'generate leads', 'lead generation', 'more traffic', 'website traffic',
        'came across your website', 'i noticed your website', 'i was looking at your website', 'your website could', 'remote job',
        'job opportunity', 'business proposal', 'partnership proposal', 'crypto', 'bitcoin', 'investment opportunity', 'loan offer',
        'unsubscribe', 'opt out', 'casino', 'viagra', 'qualified leads', 'traffic', 'keyword', 'keywords', 'free shipping', 'limited time',
        'today only', 'promo code', 'administrator', 'website owner', 'site owner', 'social media', 'ai chatbot', 'chatgpt',
        'investor', 'investment', 'content opportunity', 'publish an article', 'explainer video', 'instagram', 'followers',
        'dear beloved', 'beneficiary', 'inheritance', 'special offers', 'send me updates', 'weekly updates', 'email updates',
        'ai-powered', 'field service management', 'potential customers', 'streamline',
    ];

    /** Words of the jobs the business does: a message about one of these is a customer, not a pitch. */
    private const JOB_WORDS = '/\b(door|doors|lock|locks|hinge|glass|slid|sliding|closer|wardrobe|cabinet|repair|replace|replacement|install|installation|leak|leaking|pipe|toilet|tap|sink|basin|heater|shower|choke|paint|painting|wall|ceiling|floor|aircon|kitchen|bathroom|window|handle|roller|quote|quotation|price|cost|hdb|condo|flat|unit|renovation|plaster|tile|tiles|carpenter|plumber|electric|wiring|light|switch|socket|fan)\b/u';

    /** @return array{0: bool, 1: ?string} spam?, why */
    public static function check(array $m, ?int $exceptId = null): array
    {
        $text = mb_strtolower(($m['subject'] ?? '') . ' ' . ($m['message'] ?? ''));
        $points = 0;
        $why = [];

        $found = array_values(array_filter(self::PHRASES, fn ($p) => preg_match('/\b' . preg_quote($p, '/') . '\b/u', $text)));
        if ($found) {
            $points += min(4, 2 * count($found));
            $why[] = 'sales words: ' . implode(', ', array_slice($found, 0, 3));
        }

        $links = preg_match_all('#https?://|www\.#i', (string) ($m['message'] ?? ''));
        if ($links) {
            $points += 2; // customers rarely send links
            $why[] = $links . ' link' . ($links > 1 ? 's' : '');
        }

        // Pitches name the website ("tasfiadoorrepairsg.com's visibility"); customers rarely do.
        $domain = preg_replace('/^www\./', '', strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)));
        if ($domain && str_contains($text, $domain)) {
            $points += 2;
            $why[] = 'names the website address';
        }
        $aboutAJob = (bool) preg_match(self::JOB_WORDS, $text);

        // Random letters as a name, subject or message ("eutwLIEtICVXfPilgTJplZpM").
        foreach (['name', 'subject', 'message'] as $field) {
            if (preg_match('/\b(?=[A-Za-z]*[a-z][A-Z])(?=[A-Za-z]*[A-Z][a-z])[A-Za-z]{14,}\b/', (string) ($m[$field] ?? '')) && !preg_match('/\s/', trim((string) ($m[$field] ?? '')))) {
                $points += 3;
                $why[] = 'random letters';
                break;
            }
        }

        $phone = preg_replace('/[^\d]/', '', (string) ($m['phone'] ?? ''));
        if ($phone === '') {
            $points += 1;
            $why[] = 'no phone number';
        } elseif (!preg_match('/^(65)?[3689]\d{7}$/', $phone)) {
            $points += $aboutAJob ? 1 : 2;
            $why[] = 'not a Singapore number' . ($aboutAJob ? '' : ', and nothing about a job');
        }

        if (preg_match('/[\x{0400}-\x{04FF}]/u', $text)) {
            $points += 2;
            $why[] = 'Cyrillic text';
        }

        // The same sender or subject again and again (a mail-merge pitch).
        $email = mb_strtolower(trim((string) ($m['email'] ?? '')));
        $subject = trim((string) ($m['subject'] ?? ''));
        $repeats = Message::query()->where('created_at', '>=', now()->subDays(60))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->where(fn ($q) => $q->where('email', $email)
                ->when($subject !== '' && !in_array(mb_strtolower($subject), ['website enquiry', 'quote request', 'enquiry'], true), fn ($w) => $w->orWhere('subject', $subject)))
            ->count();
        if ($repeats >= 2) {
            $points += 2;
            $why[] = "sent {$repeats} times before";
        }

        return [$points >= self::LIMIT, $points >= self::LIMIT ? ucfirst(implode('; ', $why)) . '.' : null];
    }
}
