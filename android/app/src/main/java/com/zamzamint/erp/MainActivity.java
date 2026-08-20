package com.zamzamint.erp;

import android.webkit.WebSettings;
import android.webkit.WebView;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    private NetworkMonitor networkMonitor;
    private ResilientBridgeWebViewClient resilientWebViewClient;

    @Override
    protected void load() {
        super.load();

        WebView webView = getBridge().getWebView();
        String targetUrl = getBridge().getConfig().getServerUrl();

        WebSettings settings = webView.getSettings();
        settings.setDomStorageEnabled(true);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);

        resilientWebViewClient = new ResilientBridgeWebViewClient(
            getBridge(),
            targetUrl,
            this::onRetryExhausted
        );
        getBridge().setWebViewClient(resilientWebViewClient);

        // Lets the web app check isPushNotificationsAvailable() before
        // calling the Capacitor Push Notifications plugin's register(),
        // which otherwise crashes the app when Firebase isn't configured
        // (no google-services.json) -- see PushAvailabilityBridge.
        webView.addJavascriptInterface(new PushAvailabilityBridge(this), "ZzNativeBridge");

        networkMonitor = new NetworkMonitor(this, this::onNetworkAvailable);
        networkMonitor.register();

        // Uploads a crash saved by CrashReporter (installed in
        // ZamZamApplication) on a previous run, if any -- native networking,
        // independent of the WebView, so it isn't blocked by whatever the
        // WebView itself might be failing on.
        CrashReporter.uploadPendingReportIfAny(this, targetUrl);
    }

    private void onRetryExhausted() {
        // Local error.html is already showing (loaded by the WebViewClient
        // itself) — nothing else to do natively; NetworkMonitor below
        // handles bringing the app back once connectivity returns.
    }

    private void onNetworkAvailable() {
        WebView webView = getBridge().getWebView();
        String currentUrl = webView.getUrl();

        if (currentUrl != null && currentUrl.contains("error.html") && resilientWebViewClient != null) {
            resilientWebViewClient.resetAndReload(webView);
        }
    }

    @Override
    public void onDestroy() {
        if (networkMonitor != null) {
            networkMonitor.unregister();
        }
        super.onDestroy();
    }
}
