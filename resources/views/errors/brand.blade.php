@php
    // Error pages must render even when the database is down, so every lookup is guarded.
    $d = rescue(fn () => \App\Support\SystemSettings::pageDetails(), [], false) + ['brand' => config('app.name'), 'logo' => '/logo.png', 'phone' => null, 'tel' => null, 'whatsapp' => null];
    $inAdmin = request()->is('admin', 'admin/*');
    $home = $inAdmin && auth()->check() ? url('/admin/dashboard') : url('/');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ $d['brand'] }}</title>
    <link rel="icon" href="/favicon-64.png">
    <style>
        :root { --bg: #f6f7f9; --card: #fff; --text: #0f172a; --muted: #5b6475; --border: #e5e7eb; --accent: #1452b0; --soft: rgba(26, 102, 210,.1); }
        @media (prefers-color-scheme: dark) { :root { --bg: #0c0f14; --card: #151a22; --text: #f1f5f9; --muted: #9aa4b2; --border: #252c37; --soft: rgba(26, 102, 210,.16); } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; background: var(--bg); color: var(--text);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; max-width: 1100px; width: 100%; margin: 0 auto; }
        .brand { display: flex; align-items: center; gap: 10px; color: var(--text); text-decoration: none; font-weight: 700; }
        .brand img { width: 36px; height: 36px; object-fit: contain; border-radius: 10px; background: #fff; border: 1px solid var(--border); padding: 3px; }
        main { flex: 1; display: grid; place-items: center; padding: 24px 16px 48px; }
        .card { width: 100%; max-width: 560px; text-align: center; }
        .code { display: inline-flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--accent); background: var(--soft); padding: 6px 12px; border-radius: 999px; }
        h1 { font-size: clamp(26px, 5vw, 36px); line-height: 1.15; margin: 18px 0 0; letter-spacing: -.02em; }
        p.lead { color: var(--muted); font-size: 16px; line-height: 1.6; margin: 12px auto 0; max-width: 460px; }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 28px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 18px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; cursor: pointer; border: 0; font-family: inherit; }
        .primary { background: var(--accent); color: #fff; }
        .secondary { background: var(--card); color: var(--text); border: 1px solid var(--border); }
        form.search { display: flex; gap: 8px; margin: 26px auto 0; max-width: 420px; }
        form.search input { flex: 1; min-width: 0; padding: 11px 14px; border-radius: 12px; border: 1px solid var(--border); background: var(--card); color: var(--text); font-size: 14px; }
        .popular { margin-top: 30px; font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); }
        .links { margin-top: 12px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .links a { display: inline-flex; align-items: center; gap: 6px; color: var(--text); background: var(--card); border: 1px solid var(--border);
            padding: 9px 16px; border-radius: 999px; font-size: 14px; font-weight: 600; text-decoration: none; transition: border-color .15s, color .15s; }
        .links a:hover { border-color: var(--accent); color: var(--accent); }
        footer { text-align: center; font-size: 12.5px; color: var(--muted); padding: 18px; }
        footer a { color: var(--muted); }
    </style>
</head>
<body>
    <header>
        <a class="brand" href="{{ url('/') }}"><img src="{{ $d['logo'] }}" alt="">{{ $d['brand'] }}</a>
        @if ($d['tel'])<a class="btn secondary" href="tel:{{ $d['tel'] }}">Call {{ $d['phone'] }}</a>@endif
    </header>
    <main>
        <div class="card">
            <span class="code">Error {{ $code }}</span>
            <h1>{{ $title }}</h1>
            <p class="lead">{{ $message }}</p>

            <div class="actions">
                <a class="btn primary" href="{{ $home }}">{{ $inAdmin && auth()->check() ? 'Go to dashboard' : 'Go to the homepage' }}</a>
                <button type="button" class="btn secondary" onclick="history.length > 1 ? history.back() : location.href='{{ $home }}'">Go back</button>
                @if (($retry ?? false))<button type="button" class="btn secondary" onclick="location.reload()">Try again</button>@endif
            </div>

            @if (($search ?? false) && !$inAdmin)
                <form class="search" action="{{ url('/search') }}" method="get" role="search">
                    <input type="search" name="q" placeholder="Search services and guides…" aria-label="Search the website">
                    <button class="btn secondary" type="submit">Search</button>
                </form>
                <p class="popular">Popular pages</p>
                <div class="links">
                    <a href="{{ url('/services') }}">Services</a>
                    <a href="{{ url('/pricing') }}">Price list</a>
                    <a href="{{ url('/projects') }}">Projects</a>
                    <a href="{{ url('/blogs') }}">Guides</a>
                    <a href="{{ url('/contact') }}">Contact</a>
                </div>
            @endif
        </div>
    </main>
    <footer>
        @if ($d['whatsapp'] && !$inAdmin)Need help now? <a href="{{ $d['whatsapp'] }}" rel="noopener">WhatsApp us</a>@endif
    </footer>
</body>
</html>
