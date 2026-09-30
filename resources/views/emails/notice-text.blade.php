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

@foreach ($buttons ?? [] as $b)
{{ $b['label'] }}: {{ $b['url'] }}
@endforeach
@foreach ($after ?? [] as $line)
{{ str_replace(['**', '_'], '', $line) }}
@endforeach
