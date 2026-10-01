{{ $title }}

@if (!empty($greeting)){{ $greeting }}

@endif
@foreach ($lines ?? [] as $line){{ str_replace(['**', '_'], '', $line) }}
@endforeach
@foreach ($fields ?? [] as $f)
{{ $f[0] }}: {{ $f[1] }}
@endforeach
@if (!empty($quote))

{{ $quoteLabel ?? '' }}
{{ $quote }}
@endif
@if (!empty($seo))

SEO checklist: {{ $seo['score'] }}/100 ({{ $seo['words'] }} words, {{ $seo['errors'] }} errors, {{ $seo['warnings'] }} suggestions)
@if (!empty($seo['pillars']))
Content scores: {{ collect($seo['pillars'])->map(fn ($p) => $p['label'] . ' ' . $p['score'])->join(' · ') }}
@endif
@forelse ($seo['issues'] as $issue)
- {{ $issue['level'] === 'error' ? 'Error' : 'Suggestion' }}: {{ $issue['message'] }}
@empty
- Every SEO check passed.
@endforelse
@endif

@foreach ($buttons ?? [] as $b)
{{ $b['label'] }}: {{ $b['url'] }}
@endforeach
@foreach ($after ?? [] as $line)
{{ str_replace(['**', '_'], '', $line) }}
@endforeach
