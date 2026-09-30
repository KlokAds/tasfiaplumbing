<?php

namespace App\Models\Concerns;

use App\Models\Faq;

trait HasFaqs
{
    public function faqs()
    {
        return $this->morphMany(Faq::class, 'faqable')->orderBy('sort_order');
    }

    /** Replace this page's FAQs with the submitted list (blank rows ignored). */
    public function syncFaqs(?array $rows): void
    {
        $rows = collect($rows ?? [])
            ->map(fn ($r) => ['question' => trim($r['question'] ?? ''), 'answer' => trim($r['answer'] ?? '')])
            ->filter(fn ($r) => $r['question'] !== '' && $r['answer'] !== '')
            ->values();

        $this->faqs()->delete();
        foreach ($rows as $i => $row) {
            $this->faqs()->create($row + ['sort_order' => $i, 'is_active' => true]);
        }
    }
}
