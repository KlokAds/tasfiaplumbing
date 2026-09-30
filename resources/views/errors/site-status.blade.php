<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $mode === 'maintenance' ? 'Back soon' : 'Not available' }} · {{ $brand }}</title>
    <link rel="icon" href="/favicon-64.png">
    <style>
        :root { --bg: #f6f7f9; --card: #fff; --text: #0f172a; --muted: #5b6475; --border: #e5e7eb; --accent: #1452b0; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0c0f14; --card: #151a22; --text: #f1f5f9; --muted: #9aa4b2; --border: #252c37; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: var(--bg); color: var(--text);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .card { width: 100%; max-width: 480px; background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 36px 28px; text-align: center; box-shadow: 0 20px 50px -24px rgba(0,0,0,.25); }
        .logo { width: 64px; height: 64px; border-radius: 16px; background: #fff; border: 1px solid var(--border); display: grid; place-items: center; margin: 0 auto 18px; }
        .logo img { max-width: 44px; max-height: 44px; }
        .icon { width: 44px; height: 44px; border-radius: 12px; background: rgba(26, 102, 210,.12); color: var(--accent); display: grid; place-items: center; margin: 0 auto 14px; }
        h1 { font-size: 24px; line-height: 1.25; margin: 0; letter-spacing: -.01em; }
        p { color: var(--muted); font-size: 15px; line-height: 1.6; margin: 10px 0 0; }
        .back { display: inline-block; margin-top: 14px; font-size: 13px; font-weight: 600; color: var(--text); background: var(--bg); border: 1px solid var(--border); padding: 6px 12px; border-radius: 999px; }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 26px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 18px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; }
        .primary { background: var(--accent); color: #fff; }
        .secondary { border: 1px solid var(--border); color: var(--text); }
        .small { margin-top: 22px; font-size: 12.5px; }
        .small a { color: var(--muted); }
    </style>
</head>
<body>
    <main class="card">
        <div class="logo"><img src="{{ $logo }}" alt="{{ $brand }}"></div>
        @if ($mode === 'maintenance')
            <div class="icon"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a4 4 0 00-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 005.4-5.4l-2.5 2.5-2.4-.6-.6-2.4 2.5-2.5z"/></svg></div>
            <h1>We are updating the website</h1>
            <p>{{ $message ?: 'The site is down for a short update. You can still reach us for quotes and bookings.' }}</p>
            @if ($back_at)<span class="back">Back {{ $back_at }}</span>@endif
        @else
            <div class="icon"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/></svg></div>
            <h1>Not available in your region</h1>
            <p>{{ $geo_message ?: 'This website only serves customers in Singapore.' }}</p>
        @endif

        @if ($whatsapp || $tel)
            <div class="actions">
                @if ($whatsapp)<a class="btn primary" href="{{ $whatsapp }}" rel="noopener">WhatsApp us</a>@endif
                @if ($tel)<a class="btn secondary" href="tel:{{ $tel }}">Call {{ $phone }}</a>@endif
            </div>
        @endif
        @if ($email)<p class="small"><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
    </main>
</body>
</html>
