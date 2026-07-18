<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0f172a">
    <title>@yield('title') — {{ $link->company?->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --field-bg: #f8fafc;
            --accent: #059669;
            --accent-2: #10b981;
            --ring: rgba(16, 185, 129, .25);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b1120;
                --card: #111a2e;
                --text: #f1f5f9;
                --muted: #94a3b8;
                --line: #1e293b;
                --field-bg: #0f172a;
                --ring: rgba(16, 185, 129, .35);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Hind Siliguri', system-ui, -apple-system, sans-serif;
            background:
                radial-gradient(60rem 24rem at 120% -10%, rgba(16,185,129,.14), transparent 60%),
                radial-gradient(48rem 20rem at -20% 110%, rgba(59,130,246,.10), transparent 60%),
                var(--bg);
            color: var(--text);
            min-height: 100vh;
            min-height: 100dvh;
            padding: 1rem .9rem 2.5rem;
            -webkit-font-smoothing: antialiased;
        }
        .shell { max-width: 480px; margin: 0 auto; }

        .brand {
            display: flex; align-items: center; gap: .7rem;
            padding: .35rem .25rem 1rem;
        }
        .brand-mark {
            width: 2.6rem; height: 2.6rem; border-radius: .9rem; flex-shrink: 0;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff; font-weight: 700; font-size: 1.15rem;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 8px 18px -8px rgba(16,185,129,.55);
        }
        .brand-name { font-weight: 700; font-size: 1.05rem; line-height: 1.2; }
        .brand-sub { color: var(--muted); font-size: .78rem; margin-top: .1rem; }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 1.25rem;
            box-shadow: 0 20px 40px -24px rgba(15,23,42,.25), 0 2px 6px rgba(15,23,42,.05);
            padding: 1.4rem 1.25rem 1.5rem;
        }

        h1 { font-size: 1.2rem; font-weight: 700; letter-spacing: -.01em; }
        .muted { color: var(--muted); font-size: .85rem; }

        label { display: block; font-size: .84rem; font-weight: 600; margin: 1rem 0 .35rem; }
        input[type=text], input[type=tel], textarea {
            width: 100%; padding: .78rem .9rem;
            border: 1.5px solid var(--line); border-radius: .8rem;
            font-size: 1rem; font-family: inherit;
            background: var(--field-bg); color: var(--text);
            transition: border-color .15s, box-shadow .15s;
        }
        input:focus, textarea:focus {
            outline: none; border-color: var(--accent-2);
            box-shadow: 0 0 0 4px var(--ring);
        }

        .items { margin-top: 1.1rem; border: 1px solid var(--line); border-radius: 1rem; overflow: hidden; }
        .item {
            display: flex; justify-content: space-between; align-items: center; gap: .75rem;
            padding: .85rem 1rem; font-size: .9rem;
            border-bottom: 1px solid var(--line);
            background: var(--field-bg);
        }
        .item:last-of-type { border-bottom: none; }
        .item-name { font-weight: 600; line-height: 1.35; }
        .item-price { color: var(--muted); font-size: .82rem; margin-top: .15rem; }
        .item input {
            width: 4.2rem; text-align: center; padding: .5rem .3rem;
            border: 1.5px solid var(--line); border-radius: .65rem;
            font-size: .95rem; background: var(--card); color: var(--text);
        }
        .total {
            display: flex; justify-content: space-between; align-items: center;
            padding: .95rem 1rem; font-weight: 700; font-size: 1rem;
            background: linear-gradient(0deg, rgba(16,185,129,.10), rgba(16,185,129,.10));
        }
        .total .amount { font-size: 1.15rem; color: var(--accent); }

        .btn {
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            width: 100%; margin-top: 1.35rem; padding: .95rem;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff; font-size: 1.02rem; font-weight: 700; font-family: inherit;
            border: 0; border-radius: .9rem; cursor: pointer;
            box-shadow: 0 14px 26px -12px rgba(16,185,129,.6);
            transition: transform .12s, box-shadow .12s, filter .12s;
            text-decoration: none;
        }
        .btn:active { transform: translateY(1px) scale(.99); filter: brightness(.96); }
        .btn.secondary {
            background: var(--field-bg); color: var(--text);
            border: 1.5px solid var(--line); box-shadow: none;
        }

        .error {
            margin-top: .9rem; padding: .7rem .9rem; border-radius: .75rem;
            background: rgba(220,38,38,.09); border: 1px solid rgba(220,38,38,.25);
            color: #dc2626; font-size: .85rem;
        }
        .hp { position: absolute; left: -9999px; }

        .trust {
            display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;
            margin-top: 1rem; color: var(--muted); font-size: .76rem;
        }
        .trust span { display: inline-flex; align-items: center; gap: .3rem; }

        /* Success page */
        .success-hero { text-align: center; padding: .5rem 0 .25rem; }
        .check {
            width: 4.6rem; height: 4.6rem; margin: 0 auto 1rem; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 18px 34px -14px rgba(16,185,129,.65);
            animation: pop .45s cubic-bezier(.34,1.56,.64,1) both;
        }
        .check svg { width: 2.3rem; height: 2.3rem; stroke: #fff; stroke-width: 3; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .check svg path { stroke-dasharray: 30; stroke-dashoffset: 30; animation: draw .5s ease .3s forwards; }
        @keyframes pop { from { transform: scale(.4); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        @keyframes draw { to { stroke-dashoffset: 0; } }
        @media (prefers-reduced-motion: reduce) {
            .check { animation: none; }
            .check svg path { animation: none; stroke-dashoffset: 0; }
        }
        .order-no {
            margin: 1.1rem auto 0; display: inline-flex; align-items: center; gap: .5rem;
            padding: .55rem 1rem; border-radius: .8rem;
            background: var(--field-bg); border: 1.5px dashed var(--line);
            font-weight: 700; letter-spacing: .02em;
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="brand">
            <div class="brand-mark">{{ mb_substr((string) $link->company?->name, 0, 1) }}</div>
            <div>
                <div class="brand-name">{{ $link->company?->name }}</div>
                <div class="brand-sub">নিরাপদ অর্ডার পেজ</div>
            </div>
        </div>
        <div class="card">
            @yield('content')
        </div>
        <div class="trust">
            <span>🔒 নিরাপদ</span>
            <span>💵 ক্যাশ অন ডেলিভারি</span>
            <span>📞 প্রয়োজনে চ্যাটে যোগাযোগ করুন</span>
        </div>
    </div>
</body>
</html>
