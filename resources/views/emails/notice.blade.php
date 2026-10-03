@php
    // Every email the site sends uses this one branded layout (no Laravel default template).
    $d = rescue(fn () => \App\Support\SystemSettings::pageDetails(), [], false) + ['brand' => config('app.name'), 'logo' => '/logo.png', 'phone' => null, 'tel' => null, 'whatsapp' => null, 'email' => null];
    $logo = rescue(fn () => \App\Support\EmailLogo::url($d['logo']), url('/apple-touch-icon.png'), false);
    $accent = '#1452b0';
    $md = function (?string $text) {
        $html = e((string) $text);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
        $html = preg_replace('/(?<![\w])_(.+?)_(?![\w])/s', '<em>$1</em>', $html);
        return preg_replace('#(https?://[^\s<]+)#', '<a href="$1" style="color:#1452b0;text-decoration:underline;">$1</a>', $html);
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <title>{{ $title ?? $d['brand'] }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">
    @if (!empty($preheader))
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
    @endif
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f5f7;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;">
                    <!-- Brand -->
                    <tr>
                        <td style="padding:0 4px 16px;">
                            <table role="presentation" cellspacing="0" cellpadding="0"><tr>
                                <td style="background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;padding:4px;"><img src="{{ $logo }}" width="40" height="40" alt="{{ $d['brand'] }}" style="display:block;border:0;width:40px;height:40px;object-fit:contain;"></td>
                                <td style="padding-left:10px;font-size:15px;font-weight:700;color:#0f172a;">{{ $d['brand'] }}</td>
                            </tr></table>
                        </td>
                    </tr>
                    <!-- Card -->
                    <tr>
                        <td style="background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr><td style="height:4px;background:{{ $accent }};font-size:0;line-height:0;">&nbsp;</td></tr>
                                <tr>
                                    <td style="padding:28px 28px 8px;">
                                        @if (!empty($badge))
                                            <span style="display:inline-block;background:#fff1e8;color:{{ $accent }};font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:5px 10px;border-radius:999px;">{{ $badge }}</span>
                                        @endif
                                        <h1 style="margin:14px 0 0;font-size:22px;line-height:1.3;color:#0f172a;">{{ $title }}</h1>
                                        @if (!empty($greeting))<p style="margin:14px 0 0;font-size:15px;line-height:1.6;color:#334155;">{{ $greeting }}</p>@endif
                                        @foreach ($lines ?? [] as $line)
                                            <p style="margin:12px 0 0;font-size:15px;line-height:1.6;color:#334155;">{!! $md($line) !!}</p>
                                        @endforeach
                                    </td>
                                </tr>

                                @if (!empty($fields))
                                    <tr>
                                        <td style="padding:16px 28px 0;">
                                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb;border-radius:12px;">
                                                @foreach ($fields as $i => $f)
                                                    <tr>
                                                        <td style="padding:11px 14px;font-size:13px;color:#64748b;width:32%;{{ $i ? 'border-top:1px solid #eef0f3;' : '' }}">{{ $f[0] }}</td>
                                                        <td style="padding:11px 14px;font-size:14px;color:#0f172a;font-weight:600;{{ $i ? 'border-top:1px solid #eef0f3;' : '' }}">
                                                            @if (!empty($f[2]))<a href="{{ $f[2] }}" style="color:#0f172a;text-decoration:none;">{{ $f[1] }}</a>@else{{ $f[1] }}@endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                @if (!empty($quote))
                                    <tr>
                                        <td style="padding:16px 28px 0;">
                                            @if (!empty($quoteLabel))<p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;">{{ $quoteLabel }}</p>@endif
                                            <div style="background:#f8fafc;border-left:3px solid {{ $accent }};border-radius:8px;padding:14px 16px;font-size:15px;line-height:1.6;color:#1e293b;white-space:pre-line;">{{ $quote }}</div>
                                        </td>
                                    </tr>
                                @endif

                                @if (!empty($seo))
                                    @php
                                        $sc = (int) $seo['score'];
                                        [$scColor, $scBg, $scLabel] = $seo['errors'] > 0 || $sc < 70 ? ['#b91c1c', '#fef2f2', $seo['errors'] > 0 ? 'Fix the errors before publishing' : 'Needs work before publishing']
                                            : ($sc >= 90 ? ['#15803d', '#f0fdf4', 'Ready to publish'] : ['#b45309', '#fffbeb', 'Good, small fixes suggested']);
                                    @endphp
                                    <tr>
                                        <td style="padding:18px 28px 0;">
                                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb;border-radius:12px;">
                                                <tr>
                                                    <td style="padding:16px 18px;background:{{ $scBg }};border-radius:12px 12px 0 0;">
                                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>
                                                            <td style="width:118px;padding-right:14px;vertical-align:middle;white-space:nowrap;">
                                                                <span style="font-size:34px;font-weight:800;line-height:1;color:{{ $scColor }};">{{ $sc }}</span><span style="font-size:14px;font-weight:700;color:#64748b;">/100</span>
                                                            </td>
                                                            <td style="vertical-align:middle;">
                                                                <p style="margin:0;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;">Content quality</p>
                                                                <p style="margin:3px 0 0;font-size:15px;font-weight:700;color:{{ $scColor }};">{{ $scLabel }}</p>
                                                                <p style="margin:3px 0 0;font-size:13px;color:#64748b;">{{ number_format($seo['words']) }} words · {{ $seo['errors'] }} {{ $seo['errors'] === 1 ? 'error' : 'errors' }} · {{ $seo['warnings'] }} {{ $seo['warnings'] === 1 ? 'suggestion' : 'suggestions' }}</p>
                                                            </td>
                                                        </tr></table>
                                                    </td>
                                                </tr>
                                                @if (!empty($seo['pillars']))
                                                    {{-- SEO, AEO, GEO and E-E-A-T, the same scores as in the editor --}}
                                                    <tr>
                                                        <td style="padding:12px 14px;border-top:1px solid #eef0f3;">
                                                            <p style="margin:0 4px 2px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;">Content scores</p><p style="margin:0 4px 10px;font-size:13px;color:#64748b;">Same as in the article editor</p>
                                                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>
                                                                @foreach ($seo['pillars'] as $p)
                                                                    @php [$pc, $pb] = $p['score'] >= 80 ? ['#15803d', '#f0fdf4'] : ($p['score'] >= 60 ? ['#b45309', '#fffbeb'] : ['#b91c1c', '#fef2f2']); @endphp
                                                                    <td style="width:25%;padding:0 3px;vertical-align:top;">
                                                                        <div style="border-radius:10px;background:{{ $pb }};padding:10px 2px;text-align:center;" title="{{ $p['name'] }}">
                                                                            <p style="margin:0;font-size:11px;font-weight:700;color:#64748b;white-space:nowrap;">{{ $p['label'] }}</p>
                                                                            <p style="margin:2px 0 0;font-size:20px;font-weight:800;line-height:1.1;color:{{ $pc }};">{{ $p['score'] }}</p>
                                                                        </div>
                                                                    </td>
                                                                @endforeach
                                                            </tr></table>
                                                        </td>
                                                    </tr>
                                                @endif
                                                @forelse ($seo['issues'] as $issue)
                                                    <tr>
                                                        <td style="padding:10px 18px;border-top:1px solid #eef0f3;font-size:14px;line-height:1.5;color:#1e293b;">
                                                            <span style="display:inline-block;min-width:74px;margin-right:6px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;text-align:center;text-transform:uppercase;letter-spacing:.04em;{{ $issue['level'] === 'error' ? 'background:#fee2e2;color:#b91c1c;' : 'background:#fef3c7;color:#92400e;' }}">{{ $issue['level'] === 'error' ? 'Error' : 'Suggestion' }}</span>{{ $issue['message'] }}
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td style="padding:10px 18px;border-top:1px solid #eef0f3;font-size:14px;color:#15803d;">Every check passed.</td>
                                                    </tr>
                                                @endforelse
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                @if (!empty($buttons))
                                    <tr>
                                        <td style="padding:22px 28px 4px;">
                                            @foreach ($buttons as $b)
                                                <a href="{{ $b['url'] }}" style="display:inline-block;margin:0 8px 8px 0;padding:12px 20px;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none;{{ ($b['style'] ?? 'primary') === 'primary' ? "background:{$accent};color:#ffffff;" : ($b['style'] === 'whatsapp' ? 'background:#15803d;color:#ffffff;' : 'background:#ffffff;color:#0f172a;border:1px solid #d7dbe1;') }}">{{ $b['label'] }}</a>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif

                                @if (!empty($after))
                                    <tr>
                                        <td style="padding:10px 28px 0;">
                                            @foreach ($after as $line)
                                                <p style="margin:8px 0 0;font-size:14px;line-height:1.6;color:#475569;">{!! $md($line) !!}</p>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif
                                <tr><td style="height:26px;font-size:0;line-height:0;">&nbsp;</td></tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding:18px 8px 0;text-align:center;font-size:12px;line-height:1.7;color:#94a3b8;">
                            {{ $footer ?? 'Sent by the ' . $d['brand'] . ' website.' }}<br>
                            @if ($d['phone']){{ $d['phone'] }}@endif @if ($d['phone'] && $d['email']) · @endif @if ($d['email']){{ $d['email'] }}@endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    @if (!empty($pixel))<img src="{{ $pixel }}" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;">@endif
</body>
</html>
