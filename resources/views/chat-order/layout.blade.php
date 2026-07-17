<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') — {{ $link->company?->name }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f3f4f6; color: #111827; padding: .75rem; }
        .card { max-width: 480px; margin: .75rem auto; background: #fff; border-radius: 14px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 1.25rem; }
        h1 { font-size: 1.1rem; margin-bottom: .25rem; }
        .muted { color: #6b7280; font-size: .85rem; }
        label { display: block; font-size: .85rem; font-weight: 600; margin: .8rem 0 .25rem; }
        input[type=text], input[type=tel], textarea { width: 100%; padding: .65rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; background: inherit; color: inherit; }
        .item { display: flex; justify-content: space-between; align-items: center; gap: .5rem; padding: .6rem 0; border-bottom: 1px solid #e5e7eb; font-size: .9rem; }
        .item input { width: 4.5rem; text-align: center; padding: .4rem; border: 1px solid #d1d5db; border-radius: 8px; background: inherit; color: inherit; }
        .total { display: flex; justify-content: space-between; font-weight: 700; padding-top: .75rem; font-size: 1.05rem; }
        button { width: 100%; margin-top: 1.25rem; padding: .85rem; background: #16a34a; color: #fff; font-size: 1rem; font-weight: 700; border: 0; border-radius: 10px; cursor: pointer; }
        .error { color: #dc2626; font-size: .85rem; margin-top: .5rem; }
        .hp { position: absolute; left: -9999px; }
        @media (prefers-color-scheme: dark) {
            body { background: #111827; color: #f9fafb; }
            .card { background: #1f2937; }
            .item { border-color: #374151; }
            input[type=text], input[type=tel], textarea, .item input { border-color: #4b5563; }
        }
    </style>
</head>
<body>
    <div class="card">
        @yield('content')
    </div>
</body>
</html>
