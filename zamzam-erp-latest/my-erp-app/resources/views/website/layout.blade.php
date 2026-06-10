@php
    use App\Http\Controllers\WebsiteController;

    $siteName = $settings?->site_name ?? config('app.name', 'ZamZam ERP');
    $title = $title ?? ($settings?->seo_title ?: $siteName);
    $description = $description ?? ($settings?->seo_description ?: $settings?->tagline);
    $logoUrl = WebsiteController::mediaUrl($settings?->logo);
    $faviconUrl = WebsiteController::mediaUrl($settings?->favicon ?: $settings?->logo);
    $canonical = $canonical ?? url()->current();
    $ogTitle = $ogTitle ?? $title;
    $ogDescription = $ogDescription ?? $description;
    $ogImage = $ogImage ?? WebsiteController::absoluteMediaUrl($settings?->og_image ?: $settings?->logo);
    $ogType = $ogType ?? 'website';
    $showHeaderSiteName = (bool) ($settings?->header_show_site_name ?? false);
    $showHeaderTagline = (bool) ($settings?->header_show_tagline ?? false);
    $headerLogoWidth = min(max((int) ($settings?->header_logo_width ?: 180), 48), 360);
    $headerLogoHeight = min(max((int) ($settings?->header_logo_height ?: 64), 32), 120);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @if($description)
        <meta name="description" content="{{ $description }}">
    @endif
    <link rel="canonical" href="{{ $canonical }}">
    @if($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    @endif
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    @if($ogDescription)
        <meta property="og:description" content="{{ $ogDescription }}">
    @endif
    <meta property="og:url" content="{{ $canonical }}">
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    @if($ogDescription)
        <meta name="twitter:description" content="{{ $ogDescription }}">
    @endif
    @if($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            color-scheme: light;
            --zz-ink: #17211d;
            --zz-muted: #62716b;
            --zz-line: #d9e0dc;
            --zz-green: #006a4e;
            --zz-red: #d21f3c;
            --zz-gold: #c7953b;
            --zz-bg: #f7f9f8;
            --zz-panel: #ffffff;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Instrument Sans, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--zz-bg);
            color: var(--zz-ink);
        }
        a { color: inherit; text-decoration: none; }
        .site-shell { min-height: 100vh; display: flex; flex-direction: column; }
        .site-header {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(255, 255, 255, .94);
            border-bottom: 1px solid var(--zz-line);
            backdrop-filter: blur(16px);
        }
        .site-header-inner,
        .site-main,
        .site-footer-inner {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
        }
        .site-header-inner {
            min-height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .brand-mark {
            width: var(--header-logo-width, 180px);
            height: var(--header-logo-height, 64px);
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: #fff;
            font-weight: 800;
            flex: 0 0 auto;
            overflow: hidden;
        }
        .brand-mark-empty {
            width: 54px;
            height: 54px;
            background: var(--zz-green);
        }
        .brand-mark img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .brand-text {
            display: grid;
            min-width: 0;
        }
        .brand-name { font-weight: 800; font-size: 1.05rem; line-height: 1.1; }
        .brand-tagline { color: var(--zz-muted); font-size: .82rem; margin-top: 3px; }
        .site-nav { display: flex; align-items: center; gap: 18px; color: #26332f; font-size: .95rem; }
        .site-nav a { padding: 8px 0; }
        .site-nav a:hover { color: var(--zz-green); }
        .admin-link {
            border: 1px solid var(--zz-line);
            border-radius: 8px;
            padding: 9px 13px;
            background: #fff;
            font-weight: 700;
        }
        .site-main { flex: 1; }
        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(320px, .95fr);
            gap: 42px;
            align-items: center;
            padding: 58px 0 44px;
        }
        .hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--zz-green);
            font-weight: 800;
            font-size: .86rem;
            text-transform: uppercase;
            letter-spacing: 0;
        }
        .hero-kicker::before {
            content: "";
            width: 28px;
            height: 3px;
            background: var(--zz-red);
            border-radius: 999px;
        }
        .hero h1 {
            margin: 14px 0 18px;
            font-size: clamp(2.45rem, 6vw, 5.2rem);
            line-height: .98;
            letter-spacing: 0;
            max-width: 820px;
        }
        .hero p {
            color: var(--zz-muted);
            font-size: 1.08rem;
            line-height: 1.72;
            max-width: 650px;
            margin: 0;
        }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
        .button {
            border-radius: 8px;
            padding: 12px 16px;
            border: 1px solid var(--zz-line);
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
        }
        .button-primary { background: var(--zz-green); border-color: var(--zz-green); color: #fff; }
        .button-secondary { background: #fff; color: var(--zz-ink); }
        .hero-media {
            min-height: 380px;
            border-radius: 8px;
            overflow: hidden;
            background: linear-gradient(135deg, #e7f1ec, #fff8eb);
            border: 1px solid var(--zz-line);
            position: relative;
        }
        .hero-media img { width: 100%; height: 100%; min-height: 380px; object-fit: cover; }
        .hero-media-fallback {
            height: 100%;
            min-height: 380px;
            display: grid;
            align-content: center;
            gap: 16px;
            padding: 34px;
        }
        .metric-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--zz-line);
            border: 1px solid var(--zz-line);
            border-radius: 8px;
            overflow: hidden;
            margin: 10px 0 54px;
        }
        .metric { background: #fff; padding: 22px; }
        .metric strong { display: block; font-size: 1.55rem; color: var(--zz-green); }
        .metric span { color: var(--zz-muted); font-size: .92rem; }
        .section {
            padding: 42px 0;
            border-top: 1px solid var(--zz-line);
        }
        .section h2 { font-size: 1.85rem; margin: 0 0 14px; }
        .section-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-top: 22px;
        }
        .content-card {
            background: #fff;
            border: 1px solid var(--zz-line);
            border-radius: 8px;
            padding: 22px;
        }
        .content-card-wide { grid-column: span 2; }
        .content-card-image {
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .card-kicker {
            display: block;
            color: var(--zz-green);
            font-size: .8rem;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .content-card h3 { margin: 0 0 10px; font-size: 1.1rem; }
        .content-card p,
        .card-body { margin: 0; color: var(--zz-muted); line-height: 1.65; }
        .card-body p { margin: 0 0 10px; }
        .card-body p:last-child { margin-bottom: 0; }
        .text-link {
            display: inline-flex;
            margin-top: 16px;
            color: var(--zz-green);
            font-weight: 800;
        }
        .contact-layout {
            display: grid;
            grid-template-columns: minmax(260px, .82fr) minmax(0, 1.18fr);
            gap: 18px;
            margin-top: 22px;
            align-items: start;
        }
        .contact-details {
            display: grid;
            gap: 14px;
        }
        .contact-form {
            background: #fff;
            border: 1px solid var(--zz-line);
            border-radius: 8px;
            padding: 22px;
            display: grid;
            gap: 16px;
        }
        .contact-form label { display: grid; gap: 7px; font-weight: 800; }
        .contact-form input,
        .contact-form textarea {
            width: 100%;
            border: 1px solid var(--zz-line);
            border-radius: 8px;
            padding: 12px 13px;
            color: var(--zz-ink);
            font: inherit;
            font-weight: 500;
            background: #fff;
        }
        .contact-form textarea { resize: vertical; min-height: 132px; }
        .contact-form small { color: var(--zz-red); font-weight: 700; }
        .form-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .form-status {
            border: 1px solid #b8ddce;
            background: #eef8f4;
            color: var(--zz-green);
            border-radius: 8px;
            padding: 12px 14px;
            font-weight: 800;
        }
        .page-content {
            max-width: 820px;
            padding: 56px 0 80px;
        }
        .page-content h1 { font-size: clamp(2rem, 5vw, 4rem); line-height: 1; margin: 0 0 18px; }
        .page-body { color: var(--zz-muted); line-height: 1.75; font-size: 1.05rem; }
        .site-footer { border-top: 1px solid var(--zz-line); background: #fff; margin-top: 50px; }
        .site-footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 28px 0;
            color: var(--zz-muted);
            font-size: .92rem;
        }
        .footer-menu { display: flex; flex-wrap: wrap; gap: 14px; }

        @media (max-width: 860px) {
            .site-header-inner { align-items: flex-start; flex-direction: column; padding: 14px 0; }
            .brand-mark {
                width: min(var(--header-logo-width, 180px), 58vw);
            }
            .site-nav { width: 100%; overflow-x: auto; padding-bottom: 4px; }
            .hero { grid-template-columns: 1fr; padding-top: 38px; }
            .metric-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .section-grid { grid-template-columns: 1fr; }
            .content-card-wide { grid-column: auto; }
            .contact-layout { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .site-footer-inner { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="site-shell">
        <header class="site-header">
            <div class="site-header-inner">
                <a class="brand" href="{{ route('website.home') }}" style="--header-logo-width: {{ $headerLogoWidth }}px; --header-logo-height: {{ $headerLogoHeight }}px;">
                    <span class="brand-mark {{ $logoUrl ? '' : 'brand-mark-empty' }}">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $siteName }}">
                        @else
                            ZZ
                        @endif
                    </span>
                    @if($showHeaderSiteName || ($showHeaderTagline && $settings?->tagline))
                    <span class="brand-text">
                        @if($showHeaderSiteName)
                            <span class="brand-name">{{ $siteName }}</span>
                        @endif
                        @if($showHeaderTagline && $settings?->tagline)
                            <span class="brand-tagline">{{ $settings->tagline }}</span>
                        @endif
                    </span>
                    @endif
                </a>
                <nav class="site-nav" aria-label="Primary navigation">
                    @forelse($headerMenus as $menu)
                        <a href="{{ $menu->url }}">{{ $menu->label }}</a>
                    @empty
                        <a href="{{ route('website.home') }}">Home</a>
                        <a href="#about">About</a>
                        <a href="#contact">Contact</a>
                    @endforelse
                    <a class="admin-link" href="/admin">Dashboard</a>
                </nav>
            </div>
        </header>

        <main class="site-main">
            @yield('content')
        </main>

        <footer class="site-footer">
            <div class="site-footer-inner">
                <span>{{ $settings?->footer_text ?: $siteName.'. China to Bangladesh wholesale ERP and trading operations.' }}</span>
                <span class="footer-menu">
                    @if($settings?->facebook_url)
                        <a href="{{ $settings->facebook_url }}">Facebook</a>
                    @endif
                    @if($settings?->whatsapp_url)
                        <a href="{{ $settings->whatsapp_url }}">WhatsApp</a>
                    @endif
                    @foreach($footerMenus as $menu)
                        <a href="{{ $menu->url }}">{{ $menu->label }}</a>
                    @endforeach
                </span>
            </div>
        </footer>
    </div>
</body>
</html>
