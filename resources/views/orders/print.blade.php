<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        @page {
            margin: 10mm 12mm;
            size: A4;
        }

        :root {
            --page-width: 210mm;
            --page-height: 297mm;
            --page-margin-x: 12mm;
            --page-margin-y: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            margin: 0;
            padding: 24px;
            background: #f3f4f6;
        }

        .invoice {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 4px rgba(17, 24, 39, 0.08);
            display: flex;
            flex-direction: column;
            margin: 0 auto;
            min-height: var(--page-height);
            padding: var(--page-margin-y) var(--page-margin-x);
            width: var(--page-width);
        }

        /*
         * The contact strip, thank-you note and courier slip are grouped
         * as the invoice footer. margin-top: auto (inside the flex column
         * above) pins this group to the bottom of the page for the common
         * single-page invoice, instead of floating right under the totals.
         */
        .invoice-footer {
            margin-top: auto;
        }

        .inv-header {
            display: grid;
            grid-template-columns: 170px 1fr 170px;
            align-items: center;
            gap: 12px;
            page-break-inside: avoid;
        }

        .inv-header .logo img {
            max-height: 58px;
            max-width: 160px;
            object-fit: contain;
        }

        .inv-header .title {
            text-align: center;
        }

        .inv-header .title h1 {
            font-size: 30px;
            font-weight: 800;
            margin: 0;
        }

        .inv-header .title .hotline {
            color: #374151;
            font-size: 16px;
            margin-top: 2px;
        }

        .inv-meta {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            margin-top: 28px;
            page-break-inside: avoid;
        }

        .bill-to p {
            margin: 2px 0;
        }

        .bill-to .label {
            font-weight: 700;
        }

        .bill-to .name,
        .bill-to .phone {
            font-weight: 700;
            font-size: 14px;
        }

        .inv-ref {
            text-align: right;
            min-width: 260px;
        }

        .inv-ref .barcode svg {
            max-width: 250px;
            height: 44px;
        }

        .inv-ref p {
            margin: 3px 0;
            font-size: 14px;
        }

        .inv-ref strong {
            font-weight: 800;
        }

        table.items {
            border-collapse: collapse;
            margin-top: 24px;
            width: 100%;
        }

        table.items thead {
            display: table-header-group;
        }

        table.items th {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            color: #111827;
            font-size: 12px;
            font-weight: 700;
            padding: 9px 8px;
            text-align: left;
        }

        table.items td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            vertical-align: middle;
        }

        table.items tr {
            page-break-inside: avoid;
        }

        .num {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .item-image {
            width: 52px;
        }

        .item-image img {
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            display: block;
            height: 44px;
            object-fit: cover;
            width: 44px;
        }

        .item-name {
            font-weight: 700;
        }

        .item-variant {
            color: #6b7280;
            font-size: 12px;
            font-weight: 400;
        }

        .totals-wrap {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin-top: 0;
            page-break-inside: avoid;
        }

        .contact-block {
            flex: 1;
            padding-top: 16px;
        }

        .contact-block p {
            align-items: center;
            display: flex;
            gap: 8px;
            margin: 7px 0;
        }

        .contact-icon {
            align-items: center;
            border: 1px solid #9ca3af;
            border-radius: 50%;
            color: #374151;
            display: inline-flex;
            flex: 0 0 auto;
            font-size: 10px;
            font-weight: 700;
            height: 18px;
            justify-content: center;
            width: 18px;
        }

        table.totals {
            border-collapse: collapse;
            margin-top: 8px;
            min-width: 380px;
        }

        table.totals td {
            font-size: 13px;
            padding: 8px 12px;
        }

        table.totals tr.row {
            background: #f3f4f6;
            border-bottom: 2px solid #ffffff;
        }

        table.totals td.t-label {
            font-weight: 700;
            text-align: right;
            width: 55%;
        }

        table.totals tr.due {
            background: #000000;
            color: #ffffff;
        }

        table.totals tr.due td {
            font-weight: 800;
        }

        .contact-strip {
            background: #f3f4f6;
            display: flex;
            justify-content: space-around;
            gap: 8px;
            margin-top: 22px;
            padding: 10px 12px;
            page-break-inside: avoid;
        }

        .contact-strip span {
            font-size: 12.5px;
        }

        .contact-strip strong {
            font-weight: 800;
        }

        .thank-you {
            background: #f9fafb;
            font-size: 15px;
            font-weight: 800;
            margin-top: 14px;
            padding: 22px 12px;
            page-break-inside: avoid;
            text-align: center;
        }

        .cut-line {
            border: 0;
            border-top: 2px dashed #6b7280;
            margin: 30px 0 4px;
            position: relative;
        }

        .cut-line-wrap {
            page-break-inside: avoid;
            position: relative;
        }

        .cut-line-wrap .scissors {
            background: #ffffff;
            color: #374151;
            font-size: 16px;
            position: absolute;
            right: -6px;
            top: -14px;
        }

        .slip {
            margin-top: 14px;
            page-break-inside: avoid;
        }

        .slip-header {
            display: grid;
            grid-template-columns: 120px 1fr 120px;
            align-items: center;
        }

        .slip-header .logo img {
            max-height: 34px;
            max-width: 110px;
            object-fit: contain;
        }

        .slip-header .title {
            text-align: center;
        }

        .slip-header .title h2 {
            font-size: 21px;
            font-weight: 800;
            margin: 0;
        }

        .slip-header .title .hotline {
            color: #374151;
            font-size: 13px;
        }

        .slip-body {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            margin-top: 14px;
        }

        .slip-body .bill-to {
            font-size: 12.5px;
            max-width: 320px;
        }

        .slip-ref {
            text-align: right;
        }

        .slip-ref .barcode svg {
            max-width: 220px;
            height: 36px;
        }

        .slip-ref p {
            font-size: 12.5px;
            margin: 2px 0;
        }

        .slip-due {
            background: #000000;
            color: #ffffff;
            display: inline-block;
            font-size: 13px;
            font-weight: 800;
            margin-top: 8px;
            padding: 7px 14px;
        }

        .print-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 14px;
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

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .invoice {
                border: 0;
                box-shadow: none;
                min-height: calc(var(--page-height) - (2 * var(--page-margin-y)));
                padding: 0;
                width: 100%;
            }

            .print-actions {
                display: none;
            }
        }

        @media (max-width: 720px) {
            body {
                padding: 10px;
            }

            .invoice {
                min-height: auto;
                padding: 16px;
                width: 100%;
            }

            .inv-header,
            .slip-header {
                grid-template-columns: 1fr;
                justify-items: center;
                text-align: center;
            }

            .inv-meta,
            .totals-wrap,
            .slip-body,
            .contact-strip {
                display: block;
            }

            .inv-ref,
            .slip-ref {
                margin-top: 14px;
                text-align: left;
            }

            table.totals {
                min-width: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    @php
        use App\Support\Code128;

        $company = $company ?? ['name' => config('app.name', 'Business Dashboard'), 'currency' => 'BDT'];
        $invoice = $invoice ?? \App\Services\CompanySettingsService::INVOICE_DEFAULTS;
        $currency = $company['currency'] ?? 'BDT';
        $money = fn (float $amount): string => number_format($amount, 2);
        $discount = (float) $order->discount;
        $vat = (float) $order->vat;
        $shippingFee = (float) $order->shipping_fee;
        $paid = (float) $order->paid_amount;
        $due = (float) $order->due_amount;
        $deliveryPartner = $order->latestCourierBooking?->provider?->name;
        $invoiceDate = optional($order->order_date)->format($company['date_format'] ?? 'd M Y');
        $customerName = $order->customer?->name ?? $order->customer_name;
        $customerPhone = $order->customer?->phone;
        $customerAddress = $order->customer?->address;
        $showImages = (bool) ($invoice['show_images'] ?? true);
        $showWeight = (bool) ($invoice['show_weight'] ?? true);
        $showBarcode = (bool) ($invoice['show_barcode'] ?? true);
        $barcodeSvg = $showBarcode ? Code128::svg($order->order_number) : '';
        $columnCount = 5 + ($showImages ? 1 : 0) + ($showWeight ? 1 : 0);
        $websiteLabel = preg_replace('#^https?://#', '', rtrim((string) ($invoice['website'] ?? ''), '/'));
    @endphp
    <div class="print-actions">
        <button class="print-button" id="invoice-print-button" type="button">Print</button>
    </div>
    <main class="invoice">
        <header class="inv-header">
            <div class="logo">
                @if (! empty($company['logo_url']))
                    <img src="{{ $company['logo_url'] }}" alt="{{ $company['name'] }}">
                @endif
            </div>
            <div class="title">
                <h1>{{ $company['name'] }}</h1>
                @if (filled($invoice['hotline'] ?? null))
                    <div class="hotline">Hotline: {{ $invoice['hotline'] }}</div>
                @endif
            </div>
            <div></div>
        </header>

        <div class="inv-meta">
            <div class="bill-to">
                <p class="label">Bill To:</p>
                <p class="name">{{ $customerName }}</p>
                @if ($customerPhone)
                    <p class="phone">{{ $customerPhone }}</p>
                @endif
                @if ($customerAddress)
                    <p>{{ $customerAddress }}</p>
                @endif
            </div>
            <div class="inv-ref">
                @if ($barcodeSvg !== '')
                    <div class="barcode">{!! $barcodeSvg !!}</div>
                @endif
                <p>Invoice No: <strong>{{ $order->order_number }}</strong></p>
                @if ($deliveryPartner)
                    <p>Delivery Partner: <strong>{{ $deliveryPartner }}</strong></p>
                @endif
                <p>Date: <strong>{{ $invoiceDate }}</strong></p>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th class="center" style="width: 36px;">SL</th>
                    @if ($showImages)
                        <th style="width: 56px;">Image</th>
                    @endif
                    <th>Item Name</th>
                    @if ($showWeight)
                        <th class="center" style="width: 64px;">Weight</th>
                    @endif
                    <th class="num" style="width: 110px;">Unit Price ({{ $currency }})</th>
                    <th class="center" style="width: 60px;">Qty</th>
                    <th class="num" style="width: 130px;">Amount ({{ $currency }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        @if ($showImages)
                            <td class="item-image">
                                @if ($item->product?->image)
                                    <img src="{{ \App\Support\StorageUrl::for($item->product->image) }}" alt="{{ $item->product->name }}">
                                @endif
                            </td>
                        @endif
                        <td>
                            <span class="item-name">{{ $item->product?->name ?? 'Product' }}</span>
                            @if ($item->variant_label)
                                <div class="item-variant">{{ $item->variant_label }}</div>
                            @endif
                        </td>
                        @if ($showWeight)
                            <td class="center">
                                {{ $item->product?->weight_kg ? rtrim(rtrim(number_format((float) $item->product->weight_kg, 3), '0'), '.').' kg' : '—' }}
                            </td>
                        @endif
                        <td class="num">{{ $money((float) $item->unit_price) }}</td>
                        <td class="center">{{ $item->quantity }}</td>
                        <td class="num">{{ $money((float) $item->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals-wrap">
            <div class="contact-block">
                @if (filled($invoice['facebook_url'] ?? null) || filled($invoice['facebook_label'] ?? null))
                    <p><span class="contact-icon">f</span>{{ filled($invoice['facebook_label'] ?? null) ? $invoice['facebook_label'] : preg_replace('#^https?://#', '', $invoice['facebook_url']) }}</p>
                @endif
                @if (! empty($company['email']))
                    <p><span class="contact-icon">&#9993;</span>{{ $company['email'] }}</p>
                @endif
                @if ($websiteLabel !== '')
                    <p><span class="contact-icon">&#127760;</span>{{ $websiteLabel }}</p>
                @endif
                @if (! empty($company['address']))
                    <p><span class="contact-icon">&#9906;</span>{{ $company['address'] }}</p>
                @endif
                @if ($order->note)
                    <p><strong>Note:</strong> {{ $order->note }}</p>
                @endif
            </div>
            <div>
                <table class="totals">
                    <tr class="row">
                        <td class="t-label">Sub Total</td>
                        <td class="num">{{ $money((float) $order->subtotal) }}</td>
                    </tr>
                    @if ($discount > 0)
                        <tr class="row">
                            <td class="t-label">Discount</td>
                            <td class="num">-{{ $money($discount) }}</td>
                        </tr>
                    @endif
                    @if ($vat > 0)
                        <tr class="row">
                            <td class="t-label">VAT</td>
                            <td class="num">{{ $money($vat) }}</td>
                        </tr>
                    @endif
                    @if ($shippingFee > 0)
                        <tr class="row">
                            <td class="t-label">Delivery Charge</td>
                            <td class="num">{{ $money($shippingFee) }}</td>
                        </tr>
                    @endif
                    <tr class="row">
                        <td class="t-label">Grand Total</td>
                        <td class="num">{{ $money((float) $order->total_amount) }}</td>
                    </tr>
                    @if ($paid > 0)
                        <tr class="row">
                            <td class="t-label">Paid</td>
                            <td class="num">-{{ $money($paid) }}</td>
                        </tr>
                    @endif
                    <tr class="due">
                        <td class="t-label">Due Amount</td>
                        <td class="num">{{ $money($due) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="invoice-footer">
            @php
                $stripParts = array_filter([
                    filled($invoice['support_hotline'] ?? null) ? ['Hotline:', $invoice['support_hotline']] : null,
                    filled($invoice['facebook_label'] ?? null) ? ['Facebook Page:', $invoice['facebook_label']] : null,
                    filled($invoice['whatsapp'] ?? null) ? ['WhatsApp:', $invoice['whatsapp']] : null,
                ]);
            @endphp
            @if ($stripParts !== [])
                <div class="contact-strip">
                    @foreach ($stripParts as $part)
                        <span><strong>{{ $part[0] }}</strong> {{ $part[1] }}</span>
                    @endforeach
                </div>
            @endif

            @if (filled($invoice['thank_you'] ?? null))
                <div class="thank-you">{{ $invoice['thank_you'] }}</div>
            @endif

            @if (! empty($invoice['show_slip']))
                <div class="cut-line-wrap">
                    <hr class="cut-line">
                    <span class="scissors">&#9986;</span>
                </div>

                <section class="slip" id="courier-slip">
                    <div class="slip-header">
                        <div class="logo">
                            @if (! empty($company['logo_url']))
                                <img src="{{ $company['logo_url'] }}" alt="{{ $company['name'] }}">
                            @endif
                        </div>
                        <div class="title">
                            <h2>{{ $company['name'] }}</h2>
                            @if (filled($invoice['hotline'] ?? null))
                                <div class="hotline">Hotline: {{ $invoice['hotline'] }}</div>
                            @endif
                        </div>
                        <div></div>
                    </div>
                    <div class="slip-body">
                        <div class="bill-to">
                            <p class="label">Bill To:</p>
                            <p class="name">{{ $customerName }}</p>
                            @if ($customerPhone)
                                <p class="phone">{{ $customerPhone }}</p>
                            @endif
                            @if ($customerAddress)
                                <p>{{ $customerAddress }}</p>
                            @endif
                        </div>
                        <div class="slip-ref">
                            @if ($barcodeSvg !== '')
                                <div class="barcode">{!! $barcodeSvg !!}</div>
                            @endif
                            <p>Invoice No: <strong>{{ $order->order_number }}</strong></p>
                            @if ($deliveryPartner)
                                <p>Delivery Partner: <strong>{{ $deliveryPartner }}</strong></p>
                            @endif
                            <p>Date: <strong>{{ $invoiceDate }}</strong></p>
                            <div class="slip-due">Due Amount: {{ $currency }} {{ $money($due) }}</div>
                        </div>
                    </div>
                </section>
            @endif
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
