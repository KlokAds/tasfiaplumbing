<!DOCTYPE html>
<html lang="en-SG" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $isPublicPage = !request()->is('admin', 'admin/*', 'login');
        $gtmId = $ga4Id = null;
        if ($isPublicPage) {
            $seo = \App\Models\SiteSetting::values();
            $meta = \App\Support\PageMeta::resolve(request());
            try { $footerSettings = \App\Models\Footer::first(); } catch (\Throwable $e) { $footerSettings = null; }
            $gtmId = preg_match('/^GTM-[A-Z0-9]+$/', trim((string) ($footerSettings->g_tag ?? ''))) ? trim($footerSettings->g_tag) : null;
            $ga4Id = preg_match('/^G-[A-Z0-9]+$/', trim((string) ($footerSettings->g_a_tag ?? ''))) ? trim($footerSettings->g_a_tag) : null;
        }
    @endphp

    @if ($isPublicPage)
        {{-- Server-rendered so crawlers that skip JavaScript still read them; Inertia replaces them with identical values. --}}
        <title inertia>{{ $meta['title'] }}</title>
        <meta inertia="description" name="description" content="{{ $meta['description'] }}">
        <meta inertia="robots" name="robots" content="{{ $meta['robots'] }}">
        <link inertia="canonical" rel="canonical" href="{{ $meta['canonical'] }}">
        <meta inertia="og:type" property="og:type" content="{{ $meta['type'] }}">
        <meta inertia="og:site_name" property="og:site_name" content="{{ $meta['site_name'] }}">
        <meta inertia="og:title" property="og:title" content="{{ $meta['title'] }}">
        <meta inertia="og:description" property="og:description" content="{{ $meta['description'] }}">
        <meta inertia="og:url" property="og:url" content="{{ $meta['canonical'] }}">
        <meta inertia="og:image" property="og:image" content="{{ $meta['image'] }}">
        <meta inertia="twitter:card" name="twitter:card" content="summary_large_image">
        @if (!empty($seo['seo.gsc_verification']))
            <meta name="google-site-verification" content="{{ $seo['seo.gsc_verification'] }}">
        @endif
        @if (!empty($seo['seo.bing_verification']))
            <meta name="msvalidate.01" content="{{ $seo['seo.bing_verification'] }}">
        @endif
        <script type="application/ld+json">{!! json_encode(\App\Support\SiteSchema::graph(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        {!! \App\Support\ResponsiveImage::preloadTag() !!}
        @if ($pageSchema = \App\Support\PageSchema::current())
            <script type="application/ld+json">{!! json_encode($pageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endif
        <meta name="color-scheme" content="light dark">
        {{-- Follows the visitor's device setting (light or dark) and switches live when it changes. --}}
        <script>
            (function () {
                var m = window.matchMedia('(prefers-color-scheme: dark)');
                var set = function () { document.documentElement.classList.toggle('dark', m.matches); };
                set();
                m.addEventListener ? m.addEventListener('change', set) : m.addListener(set);
            })();
        </script>
        @php
            $tawkId = ($seo['tracking.tawk_enabled'] ?? '0') === '1' && preg_match('#^[a-z0-9]+/[a-z0-9]+$#i', (string) ($seo['tracking.tawk_id'] ?? '')) ? $seo['tracking.tawk_id'] : null;
            $sendEvents = ($seo['tracking.events'] ?? '1') === '1' && ($gtmId || $ga4Id);
        @endphp
        @if ($sendEvents)
            {{-- Conversion events for GTM/GA4: calls, WhatsApp, email clicks and sent forms. --}}
            <script>window.__trackEvents = true;</script>
        @endif
        @if ($tawkId)
            {{-- Tawk.to chat loads after the first interaction (or 10 s) so it never slows the page. --}}
            <script>
                (function () {
                    var done = false;
                    function load() {
                        if (done) return; done = true;
                        window.Tawk_API = window.Tawk_API || {}; window.Tawk_LoadStart = new Date();
                        // Keep the chat bubble above the mobile action bar.
                        Tawk_API.customStyle = { visibility: { mobile: { position: 'br', xOffset: 12, yOffset: 84 }, desktop: { position: 'br', xOffset: 20, yOffset: 20 } } };
                        var s = document.createElement('script'); s.async = true; s.charset = 'UTF-8';
                        s.src = 'https://embed.tawk.to/{{ $tawkId }}'; s.setAttribute('crossorigin', '*');
                        document.head.appendChild(s);
                    }
                    ['scroll', 'pointerdown', 'keydown', 'touchstart'].forEach(function (e) { addEventListener(e, load, { once: true, passive: true }); });
                    addEventListener('load', function () { setTimeout(load, 10000); });
                })();
            </script>
        @endif
        @if ($gtmId || $ga4Id)
            {{-- Tracking (GTM/GA4 and the pixels inside GTM) loads on the first scroll, tap or key press, or after 10 seconds.
                 Visits are still counted; the page just paints first, which keeps PageSpeed scores high. --}}
            <script>
                (function () {
                    var loaded = false;
                    window.dataLayer = window.dataLayer || [];
                    function load() {
                        if (loaded) return; loaded = true;
                        ['scroll', 'pointerdown', 'keydown', 'touchstart'].forEach(function (e) { removeEventListener(e, load); });
                        var s = document.createElement('script'); s.async = true;
                        @if ($gtmId)
                            dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
                            s.src = 'https://www.googletagmanager.com/gtm.js?id={{ $gtmId }}';
                        @else
                            window.gtag = function () { dataLayer.push(arguments); };
                            gtag('js', new Date()); gtag('config', '{{ $ga4Id }}');
                            s.src = 'https://www.googletagmanager.com/gtag/js?id={{ $ga4Id }}';
                        @endif
                        document.head.appendChild(s);
                    }
                    ['scroll', 'pointerdown', 'keydown', 'touchstart'].forEach(function (e) { addEventListener(e, load, { once: true, passive: true }); });
                    addEventListener('load', function () { setTimeout(load, 10000); });
                })();
            </script>
        @endif
    @else
        <title inertia>Admin · {{ rescue(fn () => \App\Models\SiteSetting::get('business.brand_name'), null, false) ?: config('app.name') }}</title>
        <meta name="robots" content="noindex, nofollow">
        {{-- Admin theme: light, dark or follow the device (chosen in the header). --}}
        <script>
            (function () {
                try {
                    var t = localStorage.getItem('tasfia_admin_theme') || 'system';
                    var dark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);
                } catch (e) {}
            })();
        </script>
    @endif
    {{-- Fonts are self-hosted (bundled by Vite): no extra request to Google, no render blocking. --}}
    <link rel="icon" type="image/png" sizes="64x64" href="/favicon-64.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @if ($isPublicPage)
        {{-- Start downloading the two text fonts with the page, so text never shifts when they arrive. --}}
        <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('node_modules/@fontsource-variable/figtree/files/figtree-latin-wght-normal.woff2') }}">
        <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('node_modules/@fontsource-variable/sora/files/sora-latin-wght-normal.woff2') }}">
    @endif
    {{-- The current page's own code is preloaded too, instead of waiting for app.js to ask for it. --}}
    @vite([$isPublicPage ? 'resources/css/app.css' : 'resources/css/admin-app.css', 'resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>
<body class="font-sans antialiased min-h-screen flex flex-col">
    @if ($isPublicPage && $gtmId)
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif
    @inertia
</body>
</html>
