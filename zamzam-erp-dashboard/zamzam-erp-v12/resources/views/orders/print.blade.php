<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        body {
            color: #111827;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 32px;
        }

        .invoice {
            margin: 0 auto;
            max-width: 900px;
        }

        .header,
        .meta,
        .totals {
            display: flex;
            justify-content: space-between;
            gap: 24px;
        }

        h1 {
            font-size: 28px;
            margin: 0 0 8px;
        }

        h2 {
            font-size: 16px;
            margin: 0 0 8px;
        }

        p {
            margin: 4px 0;
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
        }

        th {
            color: #4b5563;
            font-size: 12px;
            text-transform: uppercase;
        }

        .amount {
            text-align: right;
        }

        .totals {
            margin-left: auto;
            margin-top: 24px;
            max-width: 360px;
        }

        .totals table {
            margin-top: 0;
        }

        .total-row td {
            border-bottom: 0;
            font-size: 18px;
            font-weight: 700;
        }

        .print-button {
            background: #111827;
            border: 0;
            border-radius: 6px;
            color: #ffffff;
            cursor: pointer;
            padding: 10px 14px;
        }

        @media print {
            body {
                padding: 0;
            }

            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <main class="invoice">
        <div class="header">
            <div>
                <h1>Invoice</h1>
                <p><strong>{{ $order->order_number }}</strong></p>
                <p>Status: {{ ucfirst($order->status) }}</p>
            </div>
            <button class="print-button" onclick="window.print()">Print</button>
        </div>

        <div class="meta" style="margin-top: 32px;">
            <section>
                <h2>Bill To</h2>
                <p>{{ $order->customer?->name ?? $order->customer_name }}</p>
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
            <section>
                <h2>Sale Date</h2>
                <p>{{ optional($order->order_date)->format('d M Y') }}</p>
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
                        <td>{{ $item->product?->name }}</td>
                        <td class="amount">{{ $item->quantity }}</td>
                        <td class="amount">BDT {{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="amount">BDT {{ number_format((float) $item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td>Subtotal</td>
                    <td class="amount">BDT {{ number_format((float) $order->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td>Discount</td>
                    <td class="amount">BDT {{ number_format((float) $order->discount, 2) }}</td>
                </tr>
                <tr>
                    <td>VAT</td>
                    <td class="amount">BDT {{ number_format((float) $order->vat, 2) }}</td>
                </tr>
                <tr>
                    <td>Paid</td>
                    <td class="amount">BDT {{ number_format((float) $order->paid_amount, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Due</td>
                    <td class="amount">BDT {{ number_format((float) $order->due_amount, 2) }}</td>
                </tr>
            </table>
        </div>
    </main>
</body>
</html>
