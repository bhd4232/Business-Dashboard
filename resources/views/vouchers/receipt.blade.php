<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $voucher->voucher_number }}</title>
    <style>
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .top { border-bottom: 3px solid #10b981; display: table; margin-bottom: 24px; padding-bottom: 14px; width: 100%; }
        .brand, .receipt { display: table-cell; vertical-align: top; width: 50%; }
        .brand h1 { font-size: 25px; margin: 0 0 6px; }
        .receipt { text-align: right; }
        .receipt h2 { font-size: 22px; margin: 0 0 8px; }
        .muted { color: #6b7280; }
        .panel { background: #f9fafb; border: 1px solid #e5e7eb; margin-bottom: 18px; padding: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #111827; color: #fff; font-size: 10px; text-align: left; text-transform: uppercase; }
        th, td { border: 1px solid #d1d5db; padding: 8px; }
        .stamp { border: 2px solid #10b981; color: #10b981; display: inline-block; font-weight: 700; margin-top: 24px; padding: 8px 16px; text-transform: uppercase; }
    </style>
</head>
<body>
    @php($company = $company ?? ['name' => config('app.name', 'Business Dashboard'), 'currency' => 'BDT'])
    <div class="top">
        <div class="brand">
            @if (! empty($company['logo_path']))
                <img src="{{ $company['logo_path'] }}" alt="{{ $company['name'] }}" style="max-height: 52px; max-width: 170px; margin-bottom: 8px;">
            @endif
            <h1>{{ $company['name'] }}</h1>
            <div class="muted">
                @if (! empty($company['address'])){{ $company['address'] }}<br>@endif
                @if (! empty($company['phone'])){{ $company['phone'] }}@endif
                @if (! empty($company['email'])) | {{ $company['email'] }}@endif
            </div>
        </div>
        <div class="receipt">
            <h2>Money Receipt</h2>
            <div><strong>{{ $voucher->voucher_number }}</strong></div>
            <div class="muted">{{ optional($voucher->approved_at)->format($company['date_format'] ?? 'd M Y') }}</div>
        </div>
    </div>

    <div class="panel">
        <strong>Received From:</strong><br>
        {{ $voucher->customer?->name ?? $voucher->supplier?->name ?? '—' }}<br>
        <span class="muted">{{ $voucher->customer?->phone ?? $voucher->supplier?->phone }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Payment Method</th>
                <th class="right" style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $voucher->purpose ?: (\App\Models\Voucher::TRANSACTION_TYPES[$voucher->transaction_type] ?? 'Payment') }}</td>
                <td>{{ $voucher->payment_method ?: '—' }}</td>
                <td style="text-align: right;">{{ $voucher->currency }} {{ number_format((float) $voucher->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="stamp">Verified &amp; Approved</div>
</body>
</html>
