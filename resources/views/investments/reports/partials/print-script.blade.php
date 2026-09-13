{{-- Same print trigger the order invoice uses — works in a real browser and
     in the ZamZam Android app's WebView (window.ZzPrintBridge). --}}
<script>
    (function () {
        const button = document.getElementById('report-print');
        const triggerPrint = function () {
            if (window.ZzPrintBridge && typeof window.ZzPrintBridge.print === 'function') {
                window.ZzPrintBridge.print();
                return;
            }
            window.print();
        };

        if (button) {
            button.addEventListener('click', function () {
                window.focus();
                triggerPrint();
            });
        }

        @if (! empty($autoPrint))
            window.addEventListener('load', function () {
                window.focus();
                setTimeout(triggerPrint, 50);
            });
        @endif
    })();
</script>
