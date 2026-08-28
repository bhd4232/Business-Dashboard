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

            // The ZamZam Dashboard Android app's WebView has no
            // window.print() of its own (Android's WebView never
            // implements it — calling it there is a silent no-op, unlike a
            // real mobile browser). MainActivity registers this native
            // bridge (see PrintBridge.java) precisely so this page can
            // still trigger a real print dialog when running inside the app.
            const triggerPrint = function () {
                if (window.ZzPrintBridge && typeof window.ZzPrintBridge.print === 'function') {
                    window.ZzPrintBridge.print();
                    return;
                }

                window.print();
            };

            // Mobile browsers (Android Chrome, iOS Safari, in-app WebViews)
            // only treat window.print() as a direct response to the user's
            // tap when it runs synchronously inside the click handler. The
            // previous setTimeout() — even at 50ms — dropped that "user
            // activation" flag on those browsers, so tapping Print silently
            // did nothing. Calling it immediately fixes that.
            if (printButton) {
                printButton.addEventListener('click', function () {
                    window.focus();
                    triggerPrint();
                });
            }

            // Bulk print is only ever reached by explicitly clicking "Print
            // invoices" on the Orders list (not a tap on this page), so the
            // print dialog can still open automatically on load with the
            // short delay to let the page finish laying out first.
            window.addEventListener('load', function () {
                window.focus();
                setTimeout(triggerPrint, 50);
            });
        })();
    </script>
</body>
</html>
