<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Create the owner account</title>
    <style>
        :root { --bg: #f6f7f9; --card: #fff; --text: #0f172a; --muted: #5b6475; --border: #e5e7eb; --accent: #1452b0; --err: #dc2626; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0c0f14; --card: #151a22; --text: #f1f5f9; --muted: #9aa4b2; --border: #252c37; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: var(--bg); color: var(--text); font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .card { width: 100%; max-width: 440px; background: var(--card); border: 1px solid var(--border); border-radius: 18px; padding: 28px; }
        h1 { margin: 0; font-size: 22px; }
        p { color: var(--muted); line-height: 1.6; font-size: 14.5px; margin: 8px 0 20px; }
        label { display: block; font-size: 13px; font-weight: 600; margin: 14px 0 6px; }
        input { width: 100%; padding: 11px 13px; border-radius: 10px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 14.5px; }
        .err { color: var(--err); font-size: 12.5px; margin-top: 5px; }
        button { width: 100%; margin-top: 22px; background: var(--accent); color: #fff; border: 0; padding: 12px; border-radius: 10px; font-weight: 700; font-size: 15px; cursor: pointer; }
        .hint { font-size: 12px; color: var(--muted); margin-top: 5px; }
    </style>
</head>
<body>
    <form class="card" method="post" action="/setup">
        @csrf
        <h1>Create the owner account</h1>
        <p>The database is ready. This first account is the Super Admin: it can do everything, including adding your team. This page closes after it is created.</p>

        <label for="name">Your name</label>
        <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name">
        @error('name')<div class="err">{{ $message }}</div>@enderror

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
        @error('email')<div class="err">{{ $message }}</div>@enderror

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">
        <div class="hint">At least 10 characters with letters and numbers.</div>
        @error('password')<div class="err">{{ $message }}</div>@enderror

        <label for="password_confirmation">Password again</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

        <button type="submit">Create account and open the admin</button>
    </form>
</body>
</html>
