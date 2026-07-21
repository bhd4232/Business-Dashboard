<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $order->order_number }}</title>
    <style>
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .top { border-bottom: 3px solid #f59e0b; display: table; margin-bottom: 24px; padding-bottom: 14px; width: 100%; }
        .brand, .invoice { display: table-cell; vertical-align: top; width: 50%; }
        .brand h1 { font-size: 25px; margin: 0 0 6px; }
        .invoice { text-align: right; }
        .invoice h2 { font-size: 22px; margin: 0 0 8px; }
        .muted { color: #6b7280; }
        .panel { background: #f9fafb; border: 1px solid #e5e7eb; margin-bottom: 18px; padding: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #111827; color: #fff; font-size: 10px; text-align: left; text-transform: uppercase; }
        th, td { border: 1px solid #d1d5db; padding: 8px; }
        .right { text-align: right; }
        .totals { margin-left: auto; margin-top: 16px; width: 280px; }
        .totals td { border-color: #e5e7eb; }
        .grand td { background: #111827; color: #fff; font-weight: 700; }
        .product-image { height: 34px; object-fit: contain; width: 34px; }
        .barcode { margin-top: 10px; text-align: right; }
        .footer { border-top: 1px solid #d1d5db; margin-top: 28px; padding-top: 12px; text-align: center; }
        .cut-slip { page-break-before: always; }
    </style>
</head>
<body>
    @php($company = $company ?? ['name' => config('app.name', 'Business Dashboard'), 'currency' => 'BDT'])
    @php($invoice = $invoice ?? \App\Services\CompanySettingsService::INVOICE_DEFAULTS)
    @php($showImages = (bool) ($invoice['show_images'] ?? true))
    @php($showWeight = (bool) ($invoice['show_weight'] ?? true))
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
        <div class="invoice">
            <h2>Invoice</h2>
            <div><strong>{{ $order->order_number }}</strong></div>
            <div class="muted">{{ optional($order->order_date)->format($company['date_format'] ?? 'd M Y') }}</div>
            @if (! empty($invoice['hotline']))
                <div>Hotline: {{ $invoice['hotline'] }}</div>
            @endif
            @if (! empty($invoice['show_barcode']))
                <div class="barcode">{!! \App\Support\Code128::svg($order->order_number, 34, 1) !!}</div>
            @endif
        </div>
    </div>

    <div class="panel">
        <strong>Bill To:</strong><br>
        {{ $order->customer?->name ?? $order->customer_name }}<br>
        <span class="muted">{{ $order->customer?->phone }}</span><br>
        <span class="muted">{{ $order->customer?->email }}</span>
    </div>

    <table>
        <thead>
            <tr>
                @if ($showImages)<th>Image</th>@endif
                <th>Product</th>
                @if ($showWeight)<th class="right">Weight</th>@endif
                <th class="right">Qty</th>
                <th class="right">Unit Price</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    @if ($showImages)
                        <td>
                            @if (! empty($productImages[$item->getKey()] ?? null))
                                <img class="product-image" src="{{ $productImages[$item->getKey()] }}" alt="">
                            @endif
                        </td>
                    @endif
                    <td>{{ $item->product?->name }}</td>
                    @if ($showWeight)
                        <td class="right">{{ $item->product?->weight_kg ? rtrim(rtrim(number_format((float) $item->product->weight_kg, 3), '0'), '.').' kg' : '—' }}</td>
                    @endif
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ $company['currency'] ?? 'BDT' }} {{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">{{ $company['currency'] ?? 'BDT' }} {{ number_format((float) $item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ $company['currency'] ?? 'BDT' }} {{ number_format((float) $order->subtotal, 2) }}</td></tr>
        <tr><td>Discount</td><td class="right">{{ $company['currency'] ?? 'BDT' }} {{ number_format((float) $order->discount, 2) }}</td></tr>
        <tr><td>VAT</td><td class="right">{{ $company['currency'] ?? 'BDT' }} {{ number_format((float) $order->vat, 2) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="right">{{ $company['currency'] ?? 'BDT' }} {{ number_format((float) $order->total_amount, 2) }}</td></tr>
        <tr><td>Paid</td><td class="right">{{ $company['currency'] ?? 'BDT' }} {{ number_format((float) $order->paid_amount, 2) }}</td></tr>
        <tr><td>Due</td><td class="right">{{ $company['currency'] ?? 'BDT' }} {{ number_format((float) $order->due_amount, 2) }}</td></tr>
    </table>

    <div class="footer">
        @if (! empty($invoice['thank_you']))<strong>{{ $invoice['thank_you'] }}</strong><br>@endif
        @if (! empty($invoice['support_hotline']))Hotline: {{ $invoice['support_hotline'] }}@endif
        @if (! empty($invoice['whatsapp'])) &nbsp; WhatsApp: {{ $invoice['whatsapp'] }}@endif
        @if (! empty($invoice['facebook_label'])) &nbsp; Facebook: {{ $invoice['facebook_label'] }}@endif
        @if (! empty($invoice['website'])) &nbsp; {{ $invoice['website'] }}@endif
    </div>

    @if (! empty($invoice['show_slip']))
        <div class="cut-slip">
            <h2>Courier Cut-Slip</h2>
            <p><strong>{{ $company['name'] }}</strong></p>
            <p>Invoice: {{ $order->order_number }}</p>
            <p>Customer: {{ $order->customer?->name ?? $order->customer_name }}</p>
            <p>Phone: {{ $order->customer?->phone }}</p>
            <p>Address: {{ $order->customer?->address }}</p>
            <p>COD: {{ $company['currency'] ?? 'BDT' }} {{ number_format((float) $order->due_amount, 2) }}</p>
            @if (! empty($invoice['show_barcode']))
                <div>{!! \App\Support\Code128::svg($order->order_number, 40, 1) !!}</div>
            @endif
        </div>
    @endif
</body>
</html>
