<?php

namespace App\Support;

/**
 * A focus keyword suggested from an article title, for filling many articles at once
 * (Articles → select → "Set focus keywords"). The approver checks and edits each one before saving.
 *
 * "Comprehensive Guide to Sliding Door Repair: Tips…"  → "sliding door repair"
 * "Water Heater Installation Replacement Repair Service in Singapore" → "water heater installation replacement singapore"
 */
class FocusKeyword
{
    /** Words that say nothing about the topic. */
    private const FILLER = [
        'a', 'an', 'the', 'of', 'to', 'for', 'in', 'into', 'on', 'at', 'with', 'and', 'or', 'by', 'from', 'your', 'our', 'you', 'we', 'its', 'it',
        'how', 'can', 'why', 'what', 'when', 'which', 'who', 'is', 'are', 'do', 'does', 'will', 'should', 'need', 'this', 'that', 'these', 'those',
        'best', 'top', 'ultimate', 'guide', 'guideline', 'guidelines', 'comprehensive', 'complete', 'mastering', 'master', 'art', 'deep', 'dive',
        'expert', 'experts', 'professional', 'effortless', 'excellence', 'essential', 'essentials', 'tips', 'tricks', 'step', 'steps', 'everything',
        'know', 'need', 'ways', 'reasons', 'benefits', 'importance', 'understanding', 'exploring', 'discover', 'transforming', 'enhancing',
        'reliable', 'fast', 'quick', 'easy', 'simple', 'affordable', 'trusted', 'quality', 'premium', 'tasfia', 'sg', 'singapore', 'singapore\'s',
        'get', 'back', 'revive', 'conquer', 'unlocking', 'unlock', 'secrets', 'finding', 'right', 'enhance', 'space', 'spaces', 'smooth', 'solution',
        'solutions', 'one', 'stop', 'needs', 'elevating', 'elevate', 'upgrade', 'secure', 'ensuring', 'powering', 'lion', 'city',
        'full', 'potential', 'home', 'homes', 'navigating', 'waters', 'flow', 'indispensable', 'role', 'seamless', 'living', 'efficient',
        'efficiently', 'quickly', 'hassle', 'free', 'find', 'out', "don't", 'ditch', 'fix', 'rated', 'team', 'partners', 'partner',
    ];

    /** Words that name the job; the keyword ends with the first one (after at least one word before it). */
    private const SERVICE = [
        'repair', 'repairs', 'replacement', 'replacements', 'installation', 'installations', 'service', 'services', 'assembly', 'cleaning',
        'painting', 'plaster', 'plastering', 'plumbing', 'plumber', 'locksmith', 'handyman', 'electrician', 'electrical', 'wiring', 'fixing',
        'maintenance', 'renovation', 'servicing', 'cost', 'price', 'prices', 'leak', 'leaking', 'unclog', 'choke',
        'technician', 'technicians', 'specialist', 'specialists', 'contractor', 'contractors',
    ];

    private const MAX_WORDS = 4;

    public static function suggest(string $title): string
    {
        $title = html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $hasSingapore = (bool) preg_match('/\b(singapore|sg)\b/i', $title);

        // The title split at ":", " - ", "–", "|", "?" ("Plaster Service Excellence- Transforming …" too).
        // The part that names the job wins ("Get Your Glide Back: Sliding Door Roller Replacement" → the second).
        $parts = array_values(array_filter(array_map('trim', preg_split('/\s*(?::|\||–|—|\?|!|,|\s-\s|-\s|\s-)\s*/u', $title))));
        $best = [];
        $bestScore = -1;
        foreach ($parts ?: [$title] as $part) {
            $topic = self::topicWords($part);
            $score = ($topic && array_intersect($topic, self::SERVICE) ? 10 : 0) + min(count($topic), 3); // ties: the earlier part
            if ($score > $bestScore) {
                [$best, $bestScore] = [$topic, $score];
            }
        }
        if (!$best) {
            return '';
        }

        // End at the first job word that has a word before it: "smart lock installation", "plumbing service".
        foreach ($best as $i => $word) {
            if ($i >= 1 && in_array($word, self::SERVICE, true)) {
                $best = array_slice($best, max(0, $i + 1 - self::MAX_WORDS), min($i + 1, self::MAX_WORDS));
                break;
            }
        }
        $words = array_slice($best, 0, self::MAX_WORDS);
        if ($hasSingapore) {
            $words[] = 'singapore';
        }

        return implode(' ', $words);
    }

    /** @return list<string> */
    private static function topicWords(string $text): array
    {
        $words = preg_split('/[^a-z0-9\']+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter($words, fn ($w) => !in_array($w, self::FILLER, true) && !preg_match('/^(19|20)\d\d$/', $w) && strlen(trim($w, "'")) > 1));
    }
}
