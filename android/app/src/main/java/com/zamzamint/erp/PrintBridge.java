package com.zamzamint.erp;

import android.content.Context;
import android.print.PrintAttributes;
import android.print.PrintManager;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;

/**
 * Exposes native Android printing to the web app (window.ZzPrintBridge) as a
 * replacement for window.print() inside this app's WebView.
 *
 * Why this exists: unlike a real mobile browser, Android's WebView does not
 * implement window.print() at all -- calling it is a silent no-op, which is
 * exactly what the owner saw ("browser এ ঠিক আছে, অ্যাপে কাজ করে না"). The
 * invoice print pages (resources/views/orders/print[-bulk].blade.php) detect
 * this bridge and call ZzPrintBridge.print() instead of window.print() when
 * running inside this app, which drives WebView.createPrintDocumentAdapter()
 * through Android's own PrintManager -- the standard way a WebView-hosted
 * page is printed natively.
 */
public class PrintBridge {

    private final Context context;
    private final WebView webView;

    public PrintBridge(Context context, WebView webView) {
        this.context = context.getApplicationContext();
        this.webView = webView;
    }

    @JavascriptInterface
    public void print() {
        // @JavascriptInterface methods run on a background thread;
        // createPrintDocumentAdapter() and the PrintManager call both need
        // to happen on the WebView's own (UI) thread.
        webView.post(() -> {
            PrintManager printManager = (PrintManager) context.getSystemService(Context.PRINT_SERVICE);
            if (printManager == null) {
                return;
            }

            String jobName = context.getString(R.string.app_name) + " Invoice";
            printManager.print(
                jobName,
                webView.createPrintDocumentAdapter(jobName),
                new PrintAttributes.Builder().build()
            );
        });
    }
}
