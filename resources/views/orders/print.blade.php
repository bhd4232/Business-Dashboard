<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        @page {
            margin: 16mm;
            size: A4;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #111827;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 28px;
            background: #f3f4f6;
        }

        .invoice {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            margin: 0 auto;
            max-width: 900px;
            min-height: 1120px;
            padding: 40px;
        }

        .header,
        .meta,
        .summary {
            display: flex;
            justify-content: space-between;
            gap: 24px;
        }

        .brand {
            color: #6b7280;
            font-size: 12px;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        h1 {
            font-size: 30px;
            margin: 0 0 8px;
        }

        h2 {
            color: #374151;
            font-size: 16px;
            margin: 0 0 8px;
        }

        p {
            margin: 4px 0;
        }

        .muted {
            color: #6b7280;
        }

        .status {
            border: 1px solid #d1d5db;
            border-radius: 999px;
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            margin-top: 8px;
            padding: 5px 10px;
            text-transform: uppercase;
        }

        .box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            flex: 1;
            padding: 16px;
        }

        table {
            border-collapse: collapse;
            margin-top: 32px;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f9fafb;
            color: #4b5563;
            font-size: 12px;
            text-transform: uppercase;
        }

        .amount {
            text-align: right;
        }

        .summary {
            align-items: flex-start;
            margin-top: 28px;
        }

        .notes {
            color: #4b5563;
            max-width: 460px;
        }

        .totals {
            margin-left: auto;
            max-width: 360px;
            width: 100%;
        }

        .totals table {
            margin-top: 0;
        }

        .totals td {
            padding: 9px 0 9px 12px;
        }

        .total-row td {
            border-bottom: 0;
            color: #111827;
            font-size: 20px;
            font-weight: 700;
        }

        .print-button {
            background: #111827;
            border: 0;
            border-radius: 6px;
            color: #ffffff;
            cursor: pointer;
            min-width: 92px;
            padding: 10px 14px;
        }

        .print-actions {
            align-items: flex-start;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .footer {
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 12px;
            margin-top: 48px;
            padding-top: 16px;
            text-align: center;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .invoice {
                border: 0;
                min-height: auto;
                padding: 0;
            }

            .print-actions,
            .print-button {
                display: none;
            }
        }

        @media (max-width: 720px) {
            body {
                padding: 12px;
            }

            .invoice {
                min-height: auto;
                padding: 20px;
            }

            .header,
            .meta,
            .summary {
                display: block;
            }

            .print-actions,
            .print-button,
            .box,
            .totals {
                margin-top: 16px;
            }
        }
    </style>
</head>
<body>
    @php
        $company = $company ?? ['name' => config('app.name', 'Business Dashboard'), 'currency' => 'BDT'];
        $currency = $company['currency'] ?? 'BDT';
        $money = fn (float $amount): string => $currency.' '.number_format($amount, 2);
        $discount = (float) $order->discount;
        $vat = (float) $order->vat;
        $paid = (float) $order->paid_amount;
        $due = (float) $order->due_amount;
    @endphp
    <main class="invoice">
        <div class="header">
            <div>
                <div class="brand">{{ $company['name'] }}</div>
                @if (! empty($company['logo_url']))
                    <img src="{{ $company['logo_url'] }}" alt="{{ $company['name'] }}" style="max-height: 56px; max-width: 180px; object-fit: contain; margin-bottom: 12px;">
                @endif
                <h1>Invoice</h1>
                <p class="muted">Invoice No: <strong>{{ $order->order_number }}</strong></p>
                <span class="status">{{ ucfirst($order->status) }}</span>
            </div>
            <div class="print-actions">
                <button class="print-button" id="invoice-print-button" type="button">Print</button>
            </div>
        </div>

        <div class="meta" style="margin-top: 32px;">
            <section class="box">
                <h2>Bill To</h2>
                <p><strong>{{ $order->customer?->name ?? $order->customer_name }}</strong></p>
                @if ($order->customer?->phone)
                    <p>{{ $order->customer->phone }}</p>
                @endif
                @if ($order->customer?->email)
                    <p>{{ $order->customer->email }}</p>
                @endif
                @if ($order->customer?->address)
                    <p>{{ $order->customer->address }}</p>
                @endif
            </section>
            <section class="box">
                <h2>Sale Date</h2>
                <p>{{ optional($order->order_date)->format($company['date_format'] ?? 'd M Y') }}</p>
                <h2 style="margin-top: 16px;">Payment</h2>
                @if ($paid > 0)
                    <p class="muted">Paid: -{{ $money($paid) }}</p>
                @endif
                <p class="muted">Due: {{ $money($due) }}</p>
            </section>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="amount">Qty</th>
                    <th class="amount">Unit Price</th>
                    <th class="amount">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->product?->name ?? 'Product' }}</strong>
                            @if ($item->product?->sku)
                                <div class="muted">SKU: {{ $item->product->sku }}</div>
                            @endif
                        </td>
                        <td class="amount">{{ $item->quantity }}</td>
                        <td class="amount">{{ $company['currency'] ?? 'BDT' }} {{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="amount">{{ $company['currency'] ?? 'BDT' }} {{ number_format((float) $item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <div class="notes">
                @if ($order->note)
                    <h2>Note</h2>
                    <p>{{ $order->note }}</p>
                @endif
            </div>

            <div class="totals">
                <table>
                    <tr>
                        <td>Subtotal</td>
                        <td class="amount">{{ $money((float) $order->subtotal) }}</td>
                    </tr>
                    @if ($discount > 0)
                        <tr>
                            <td>Discount</td>
                            <td class="amount">{{ $money($discount) }}</td>
                        </tr>
                    @endif
                    @if ($vat > 0)
                        <tr>
                            <td>VAT</td>
                            <td class="amount">{{ $money($vat) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>Total</td>
                        <td class="amount">{{ $money((float) $order->total_amount) }}</td>
                    </tr>
                    @if ($paid > 0)
                        <tr>
                            <td>Paid</td>
                            <td class="amount">-{{ $money($paid) }}</td>
                        </tr>
                    @endif
                    <tr class="total-row">
                        <td>Due</td>
                        <td class="amount">{{ $money($due) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="footer">
            @if (! empty($company['address'])){{ $company['address'] }} | @endif
            @if (! empty($company['phone'])){{ $company['phone'] }} | @endif
            @if (! empty($company['email'])){{ $company['email'] }} | @endif
            Thank you for your business.
        </div>
    </main>
    <script>
        (function () {
            const printButton = document.getElementById('invoice-print-button');
            const openPrintDialog = function () {
                window.focus();
                setTimeout(function () {
                    window.print();
                }, 50);
            };

            if (printButton) {
                printButton.addEventListener('click', openPrintDialog);
            }

            if (new URLSearchParams(window.location.search).get('print') === '1') {
                window.addEventListener('load', openPrintDialog);
            }
        })();
    </script>
</body>
</html>
