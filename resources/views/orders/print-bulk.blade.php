<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $orders->count() }} Invoice{{ $orders->count() === 1 ? '' : 's' }}</title>
    @include('orders.partials.invoice-styles')
</head>
<body>
    <div class="print-actions">
        <button class="print-button" id="invoice-print-button" type="button">Print</button>
    </div>

    @foreach ($orders as $order)
        @include('orders.partials.invoice', ['order' => $order, 'company' => $company, 'invoice' => $invoice])
    @endforeach

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

            // Bulk print is only ever reached by explicitly clicking "Print
            // invoices" on the Orders list, so the print dialog opens
            // automatically every time — no `?print=1` opt-in needed like
            // the single-order view (which is also linked to from places
            // where auto-printing isn't wanted).
            window.addEventListener('load', openPrintDialog);
        })();
    </script>
</body>
</html>
