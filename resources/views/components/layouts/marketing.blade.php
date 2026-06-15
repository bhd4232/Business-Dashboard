<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'ZamZam ERP' }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #111827;
            background: #f8fafc;
        }

        * { box-sizing: border-box; }
        body { margin: 0; }
        a { color: inherit; text-decoration: none; }
        .nav {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 18px clamp(20px, 5vw, 72px);
            background: rgb(248 250 252 / .92);
            border-bottom: 1px solid #e5e7eb;
            backdrop-filter: blur(12px);
        }
        .brand { font-size: 18px; font-weight: 900; }
        .links { display: flex; align-items: center; gap: 14px; color: #475569; font-size: 14px; font-weight: 700; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 16px;
            color: #111827;
            background: #f59e0b;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            font-weight: 850;
        }
        .button.secondary { background: #ffffff; border-color: #cbd5e1; }
        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, .85fr);
            gap: 34px;
            align-items: center;
            padding: 72px clamp(20px, 5vw, 72px) 42px;
        }
        .eyebrow { color: #b45309; font-size: 13px; font-weight: 900; text-transform: uppercase; }
        h1 { margin: 12px 0 14px; font-size: clamp(38px, 6vw, 68px); line-height: 1.02; letter-spacing: 0; }
        h2 { margin: 0 0 10px; font-size: 28px; line-height: 1.15; }
        p { color: #475569; font-size: 16px; line-height: 1.7; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
        .preview {
            min-height: 380px;
            padding: 18px;
            background: #111827;
            border-radius: 10px;
            box-shadow: 0 26px 70px rgb(15 23 42 / .24);
        }
        .preview-bar { height: 42px; border-bottom: 1px solid rgb(255 255 255 / .12); }
        .preview-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 18px; }
        .preview-card { min-height: 86px; padding: 14px; color: #e5e7eb; background: #1f2937; border: 1px solid #374151; border-radius: 8px; }
        .preview-card strong { display: block; margin-top: 10px; color: #fbbf24; font-size: 24px; }
        .section { padding: 34px clamp(20px, 5vw, 72px); }
        .cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .card { padding: 20px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; }
        .card strong { display: block; margin-bottom: 8px; font-size: 17px; }
        .footer { padding: 28px clamp(20px, 5vw, 72px); color: #64748b; border-top: 1px solid #e5e7eb; }
        input, select { font: inherit; }
        @media (max-width: 860px) {
            .hero, .cards { grid-template-columns: 1fr; }
            .links { flex-wrap: wrap; justify-content: flex-end; }
        }
    </style>
</head>
<body>
    <nav class="nav">
        <a class="brand" href="{{ route('marketing.home') }}">ZamZam ERP</a>
        <div class="links">
            <a href="{{ route('marketing.pricing') }}">Pricing</a>
            <a href="{{ route('marketing.docs') }}">Docs</a>
            <a href="{{ url('/install') }}">Install</a>
            <a class="button secondary" href="{{ url('/admin/login') }}">Admin Login</a>
        </div>
    </nav>
    {{ $slot }}
    <footer class="footer">ZamZam ERP for inventory, purchase costing, sales, accounts, and reporting.</footer>
</body>
</html>
