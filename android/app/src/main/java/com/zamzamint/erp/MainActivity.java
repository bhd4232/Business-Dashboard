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

        networkMonitor = new NetworkMonitor(this, this::onNetworkAvailable);
        networkMonitor.register();
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
