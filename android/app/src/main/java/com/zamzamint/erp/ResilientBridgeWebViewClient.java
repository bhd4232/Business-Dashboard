package com.zamzamint.erp;

import android.net.Uri;
import android.os.Handler;
import android.os.Looper;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeWebViewClient;
import java.util.HashSet;
import java.util.Set;

/**
 * Retries transient network errors (Wi-Fi/mobile data switching, brief
 * disconnects) a few times before giving up and showing a local friendly
 * error page. Extends Capacitor's own BridgeWebViewClient (not plain
 * WebViewClient) so plugin bridging, local-server interception, and
 * external-link handling keep working exactly as before.
 */
public class ResilientBridgeWebViewClient extends BridgeWebViewClient {

    public interface RetryExhaustedListener {
        void onRetryExhausted();
    }

    private static final int MAX_RETRIES = 3;
    private static final long RETRY_DELAY_MS = 2500L;

    // net::ERR_* codes that are worth retrying — anything else (e.g. a real
    // 404/500 from the app, or an SSL cert problem) is left alone.
    private static final Set<Integer> RETRYABLE_ERROR_CODES = new HashSet<>();

    static {
        RETRYABLE_ERROR_CODES.add(WebViewClient.ERROR_CONNECT);
        RETRYABLE_ERROR_CODES.add(WebViewClient.ERROR_TIMEOUT);
        RETRYABLE_ERROR_CODES.add(WebViewClient.ERROR_HOST_LOOKUP);
        RETRYABLE_ERROR_CODES.add(-6); // net::ERR_CONNECTION_RESET
        RETRYABLE_ERROR_CODES.add(-7); // net::ERR_CONNECTION_REFUSED
        RETRYABLE_ERROR_CODES.add(-21); // net::ERR_NETWORK_CHANGED
        RETRYABLE_ERROR_CODES.add(-100); // net::ERR_CONNECTION_CLOSED
        RETRYABLE_ERROR_CODES.add(-102); // net::ERR_SOCKET_NOT_CONNECTED
        RETRYABLE_ERROR_CODES.add(-105); // net::ERR_NAME_NOT_RESOLVED
        RETRYABLE_ERROR_CODES.add(-106); // net::ERR_INTERNET_DISCONNECTED
    }

    private final String targetUrl;
    private final RetryExhaustedListener retryExhaustedListener;
    private final Handler handler = new Handler(Looper.getMainLooper());

    private int retryCount = 0;
    private boolean retryScheduled = false;

    public ResilientBridgeWebViewClient(Bridge bridge, String targetUrl, RetryExhaustedListener retryExhaustedListener) {
        super(bridge);
        this.targetUrl = targetUrl;
        this.retryExhaustedListener = retryExhaustedListener;
    }

    @Override
    public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
        super.onReceivedError(view, request, error);

        // Only retry main-document failures — a failed sub-resource (image,
        // font, analytics call) should not reload the whole app.
        if (!request.isForMainFrame()) {
            return;
        }

        int errorCode = error.getErrorCode();

        if (!RETRYABLE_ERROR_CODES.contains(errorCode)) {
            return;
        }

        if (retryCount >= MAX_RETRIES) {
            showConnectionError(view);
            return;
        }

        retryCount++;

        if (retryScheduled) {
            return;
        }
        retryScheduled = true;

        handler.postDelayed(() -> {
            retryScheduled = false;
            view.reload();
        }, RETRY_DELAY_MS);
    }

    @Override
    public void onPageFinished(WebView view, String url) {
        super.onPageFinished(view, url);

        // A page (the real app, not our local error page) finished loading
        // successfully — the connection is healthy again.
        if (url != null && url.startsWith(targetUrl)) {
            retryCount = 0;
        }
    }

    private void showConnectionError(WebView view) {
        String errorPageUrl = "file:///android_asset/error.html?target=" + Uri.encode(targetUrl);
        view.loadUrl(errorPageUrl);

        if (retryExhaustedListener != null) {
            retryExhaustedListener.onRetryExhausted();
        }
    }

    /**
     * Resets the retry budget and loads the real app again — called when
     * connectivity returns while the local error page is showing.
     */
    public void resetAndReload(WebView view) {
        retryCount = 0;
        view.loadUrl(targetUrl);
    }
}
