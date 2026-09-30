<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Install {{ config('app.name') }}</title>
    <style>
        :root { --bg: #f1f5fa; --card: #fff; --text: #0a1d33; --muted: #4a5d73; --border: #dfe8f2; --accent: #1759c4; --navy: #0a2540; --err: #b91c1c; --ok: #15803d; }
        @media (prefers-color-scheme: dark) { :root { --bg: #070f1a; --card: #13243a; --text: #f4f8fd; --muted: #a8b9cc; --border: #213854; --err: #fca5a5; --ok: #6ee7a0; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: var(--bg); color: var(--text); font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .card { width: 100%; max-width: 480px; background: var(--card); border: 1px solid var(--border); border-radius: 22px; overflow: hidden; }
        .head { background: var(--navy); color: #fff; padding: 22px 28px; display: flex; align-items: center; gap: 14px; }
        .head img { width: 48px; height: 48px; border-radius: 50%; background: #fff; padding: 4px; object-fit: contain; }
        .head b { display: block; font-size: 17px; } .head span { font-size: 13px; opacity: .7; }
        .body { padding: 26px 28px 30px; }
        h1 { margin: 0; font-size: 22px; }
        p { color: var(--muted); line-height: 1.6; font-size: 14.5px; margin: 8px 0 18px; }
        label { display: block; font-size: 13.5px; font-weight: 600; margin: 14px 0 6px; }
        input { width: 100%; padding: 11px 13px; border-radius: 12px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 15px; }
        .row { display: grid; grid-template-columns: 1fr 110px; gap: 12px; }
        .err { background: color-mix(in srgb, var(--err) 12%, transparent); color: var(--err); border-radius: 12px; padding: 11px 13px; font-size: 13.5px; line-height: 1.5; margin-bottom: 8px; }
        .hint { font-size: 12.5px; color: var(--muted); margin-top: 6px; line-height: 1.5; }
        button, .btn { display: block; width: 100%; margin-top: 22px; background: var(--accent); color: #fff; border: 0; padding: 13px; border-radius: 999px; font-weight: 700; font-size: 15px; cursor: pointer; text-align: center; text-decoration: none; }
        .btn.alt { background: transparent; color: var(--text); border: 1px solid var(--border); margin-top: 10px; }
        .ok { color: var(--ok); font-weight: 700; }
        button[disabled] { opacity: .6; cursor: wait; }
        .warn { margin-top: 20px; background: color-mix(in srgb, #f59e0b 14%, transparent); border: 1px solid color-mix(in srgb, #f59e0b 45%, transparent); border-radius: 14px; padding: 14px 16px; font-size: 14px; line-height: 1.55; }
        .check { display: flex; gap: 10px; align-items: center; margin: 12px 0 0; font-size: 14.5px; }
        .check input { width: 18px; height: 18px; }
    </style>
</head>
<body>
<div class="card">
    <div class="head">
        <img src="/logo.png" alt="">
        <div><b>{{ config('app.name') }}</b><span>Website installer</span></div>
    </div>
    <div class="body">
        @if (!empty($done))
            <h1><span class="ok">✓</span> The website is installed</h1>
            <p>
                The database is connected{{ $imported ? ' and all website data (articles, services, projects, settings and accounts) has been loaded' : '' }}.
                This installer is now closed for good.
            </p>
            @if (!empty($backup))
                <p class="hint">The old data that was in this database is saved in <b>{{ $backup }}</b> (in the site folder, not reachable from the web). Keep it until you are sure everything is right.</p>
            @endif
            <a class="btn" href="/">Open the website</a>
            <a class="btn alt" href="{{ $hasUsers ? '/admin/login' : '/setup' }}">{{ $hasUsers ? 'Sign in to the admin' : 'Create the owner account' }}</a>
        @else
            <h1>Connect the database</h1>
            <p>Enter the MySQL database you created in hPanel → Databases → MySQL Databases.
                @if ($hasData) An empty database is filled with all the website data automatically. @endif
            </p>

            <form method="post" action="/install" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Installing… this can take a minute';">
                @csrf
                @error('database')<div class="err">{{ $message }}</div>@enderror

                <div class="row">
                    <div>
                        <label for="host">Database host</label>
                        <input id="host" name="host" value="{{ old('host', 'localhost') }}" required>
                    </div>
                    <div>
                        <label for="port">Port</label>
                        <input id="port" name="port" type="number" value="{{ old('port', '3306') }}" required>
                    </div>
                </div>
                <div class="hint">On Hostinger keep "localhost" and 3306.</div>

                <label for="database">Database name</label>
                <input id="database" name="database" value="{{ old('database') }}" placeholder="u605894151_plumbing" required autocomplete="off">

                <label for="username">Database username</label>
                <input id="username" name="username" value="{{ old('username') }}" placeholder="u605894151_plumbing" required autocomplete="off">

                <label for="password">Database password</label>
                <input id="password" name="password" type="password" autocomplete="new-password">

                @if (session('existing_tables'))
                    <div class="warn">
                        <b>This database already has {{ session('existing_tables') }} tables</b> (probably the old website).
                        To use it, the installer first saves a full backup of them on the server, then replaces them with the new website data. Tick the box and type the database password again.
                        <label class="check"><input type="checkbox" name="replace" value="1" required> Back up the old data and replace it</label>
                    </div>
                @endif

                <button type="submit">{{ session('existing_tables') ? 'Back up, replace and install' : 'Install the website' }}</button>
            </form>
        @endif
    </div>
</div>
</body>
</html>
