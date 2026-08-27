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

            // Mobile browsers (Android Chrome, iOS Safari, in-app WebViews)
            // only treat window.print() as a direct response to the user's
            // tap when it runs synchronously inside the click handler. The
            // previous setTimeout() — even at 50ms — dropped that "user
            // activation" flag on those browsers, so tapping Print silently
            // did nothing. Calling it immediately fixes that.
            if (printButton) {
                printButton.addEventListener('click', function () {
                    window.focus();
                    window.print();
                });
            }

            // The auto-print-on-load path (?print=1, used when linking here
            // from elsewhere) has no click gesture to preserve, so the short
            // delay to let the page finish laying out first stays safe here.
            if (new URLSearchParams(window.location.search).get('print') === '1') {
                window.addEventListener('load', function () {
                    window.focus();
                    setTimeout(function () {
                        window.print();
                    }, 50);
                });
            }
        })();
    </script>
</body>
</html>
