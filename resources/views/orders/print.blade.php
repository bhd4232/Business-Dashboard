<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $order->order_number }}</title>
    @include('orders.partials.invoice-styles')
</head>
<body>
    <div class="print-actions">
        <button class="print-button" id="invoice-print-button" type="button">Print</button>
    </div>

    @include('orders.partials.invoice', ['order' => $order, 'company' => $company ?? null, 'invoice' => $invoice ?? null])

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
