<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Quotation {{ $quotation->quotation_number }} — {{ $quotation->company?->name }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f3f4f6; color: #111827; padding: 1rem; }
        .card { max-width: 720px; margin: 1rem auto; background: #fff; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 1.5rem; }
        h1 { font-size: 1.25rem; margin-bottom: .25rem; }
        .muted { color: #6b7280; font-size: .875rem; }
        .badge { display: inline-block; padding: .2rem .6rem; border-radius: 9999px; font-size: .75rem; font-weight: 600; text-transform: uppercase; }
        .badge.draft, .badge.expired, .badge.rejected { background: #fee2e2; color: #991b1b; }
        .badge.sent { background: #dbeafe; color: #1e40af; }
        .badge.accepted { background: #dcfce7; color: #166534; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: .5rem .4rem; border-bottom: 1px solid #e5e7eb; font-size: .9rem; }
        th { color: #6b7280; font-weight: 600; font-size: .75rem; text-transform: uppercase; }
        td.num, th.num { text-align: right; }
        .totals { margin-top: 1rem; margin-left: auto; max-width: 280px; }
        .totals div { display: flex; justify-content: space-between; padding: .25rem 0; font-size: .9rem; }
        .totals .grand { font-weight: 700; font-size: 1.05rem; border-top: 2px solid #111827; padding-top: .5rem; margin-top: .25rem; }
        @media (prefers-color-scheme: dark) {
            body { background: #111827; color: #f9fafb; }
            .card { background: #1f2937; }
            th, td { border-color: #374151; }
            .totals .grand { border-color: #f9fafb; }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $quotation->company?->name }}</h1>
        <p class="muted">Quotation <strong>{{ $quotation->quotation_number }}</strong> · {{ $quotation->created_at->format('d M Y') }}</p>
        <p style="margin-top: .5rem;">
            <span class="badge {{ $quotation->status }}">{{ \App\Models\Quotation::STATUSES[$quotation->status] ?? $quotation->status }}</span>
            @if ($quotation->valid_until)
                <span class="muted">— Valid until {{ $quotation->valid_until->format('d M Y') }}</span>
            @endif
        </p>

        @php($recipient = $quotation->customer?->name ?? $quotation->lead?->name)
        @if ($recipient)
            <p class="muted" style="margin-top: .75rem;">Prepared for: <strong>{{ $recipient }}</strong></p>
        @endif

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="num">Qty</th>
                    <th class="num">Unit Price</th>
                    <th class="num">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quotation->items as $item)
                    <tr>
                        <td>
                            {{ $item->product?->name }}
                            @if ($item->variant_label)
                                <span class="muted">({{ $item->variant_label }})</span>
                            @endif
                        </td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">৳{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="num">৳{{ number_format((float) $item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div><span>Subtotal</span><span>৳{{ number_format((float) $quotation->items->sum('subtotal'), 2) }}</span></div>
            @if ((float) $quotation->discount_amount > 0)
                <div><span>Discount</span><span>-৳{{ number_format((float) $quotation->discount_amount, 2) }}</span></div>
            @endif
            <div class="grand"><span>Total</span><span>৳{{ number_format((float) $quotation->total_amount, 2) }}</span></div>
        </div>
    </div>
</body>
</html>
