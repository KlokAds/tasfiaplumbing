@php
    $c = config('database.connections.' . config('database.default'), []);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Setup needed</title>
    <style>
        :root { --bg: #f6f7f9; --card: #fff; --text: #0f172a; --muted: #5b6475; --border: #e5e7eb; --accent: #1452b0; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0c0f14; --card: #151a22; --text: #f1f5f9; --muted: #9aa4b2; --border: #252c37; } }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: var(--bg); color: var(--text); font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .card { width: 100%; max-width: 560px; background: var(--card); border: 1px solid var(--border); border-radius: 18px; padding: 28px; }
        h1 { margin: 0; font-size: 22px; }
        p { color: var(--muted); line-height: 1.6; font-size: 15px; }
        .err { font-family: ui-monospace, Consolas, monospace; font-size: 12.5px; background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 12px; white-space: pre-wrap; word-break: break-word; color: var(--text); }
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 8px; }
        td { padding: 7px 0; border-bottom: 1px solid var(--border); }
        td:first-child { font-family: ui-monospace, Consolas, monospace; color: var(--muted); width: 45%; }
        .btn { display: inline-block; margin-top: 18px; background: var(--accent); color: #fff; padding: 10px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 14px; }
    </style>
</head>
<body>
    <main class="card">
        @if (!empty($migrate))
            <h1>The database update did not finish</h1>
            <p>The site tried to update the database automatically and stopped. Nothing was deleted. Fix the cause below, then refresh.</p>
        @else
            <h1>The website cannot reach its database</h1>
            <p>Open the <b>.env</b> file in the site folder and check these values (from your hosting's Databases page), then refresh this page. Everything else is set up automatically.</p>
            <table>
                <tr><td>DB_CONNECTION</td><td>{{ config('database.default') }}</td></tr>
                @if (config('database.default') !== 'sqlite')
                    <tr><td>DB_HOST</td><td>{{ $c['host'] ?? '—' }}</td></tr>
                    <tr><td>DB_PORT</td><td>{{ $c['port'] ?? '—' }}</td></tr>
                    <tr><td>DB_DATABASE</td><td>{{ $c['database'] ?? '—' }}</td></tr>
                    <tr><td>DB_USERNAME</td><td>{{ $c['username'] ?? '—' }}</td></tr>
                    <tr><td>DB_PASSWORD</td><td>{{ filled($c['password'] ?? null) ? 'set' : 'empty' }}</td></tr>
                @else
                    <tr><td>DB_DATABASE</td><td>{{ $c['database'] ?? '—' }}</td></tr>
                @endif
            </table>
        @endif
        <p style="margin-top:16px">What the database said:</p>
        <div class="err">{{ $error }}</div>
        <a class="btn" href="{{ request()->url() }}">Try again</a>
    </main>
</body>
</html>
