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
 * disconnects, a server-side connection reset) several times before giving
 * up and leaving the user on a local friendly error page. Extends
 * Capacitor's own BridgeWebViewClient (not plain WebViewClient) so plugin
 * bridging, local-server interception, and external-link handling keep
 * working exactly as before.
 *
 * IMPORTANT: on a retryable failure this shows the friendly error.html
 * IMMEDIATELY (first failure, not after retries are exhausted). Android's
 * WebView renders its own raw "Web page not available / net::ERR_..." page
 * automatically the instant a main-frame load fails, and there is no public
 * API to suppress that render after the fact -- the only way to keep the
 * user from ever seeing it is to win the race by loading our own content
 * right here in onReceivedError. Retries then continue silently underneath
 * this friendly page; if a retry itself fails, the raw page would flash
 * again for an instant before we're notified, so `showingFriendlyError`
 * avoids reloading error.html on every single retry (it's already showing).
 */
public class ResilientBridgeWebViewClient extends BridgeWebViewClient {

    public interface RetryExhaustedListener {
        void onRetryExhausted();
    }

    // Bumped from 3/2500ms: since the friendly page now shows immediately
    // (see class doc), retrying more often/longer has no UX cost -- it just
    // gives a flaky connection (e.g. the app server resetting connections
    // under load) more chances to recover before the user has to tap
    // "Try Again" themselves.
    private static final int MAX_RETRIES = 6;
    private static final long RETRY_DELAY_MS = 1500L;

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
    private boolean showingFriendlyError = false;
    private boolean retryExhaustedNotified = false;

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

        // Win the race against Android's own raw error page on the very
        // first failure — see class doc. A no-op on the 2nd..Nth failure
        // since it's already showing.
        showFriendlyErrorPage(view);

        if (retryCount >= MAX_RETRIES) {
            if (!retryExhaustedNotified) {
                retryExhaustedNotified = true;
                if (retryExhaustedListener != null) {
                    retryExhaustedListener.onRetryExhausted();
                }
            }
            return;
        }

        retryCount++;

        if (retryScheduled) {
            return;
        }
        retryScheduled = true;

        handler.postDelayed(() -> {
            retryScheduled = false;
            // loadUrl(targetUrl), not view.reload(): once the friendly page
            // is showing, the WebView's "current" URL is error.html, and
            // reload() would just reload that instead of retrying the app.
            view.loadUrl(targetUrl);
        }, RETRY_DELAY_MS);
    }

    @Override
    public void onPageFinished(WebView view, String url) {
        super.onPageFinished(view, url);

        // A page (the real app, not our local error page) finished loading
        // successfully — the connection is healthy again.
        if (url != null && url.startsWith(targetUrl)) {
            retryCount = 0;
            retryExhaustedNotified = false;
            showingFriendlyError = false;
        }
    }

    private void showFriendlyErrorPage(WebView view) {
        if (showingFriendlyError) {
            return;
        }
        showingFriendlyError = true;

        String errorPageUrl = "file:///android_asset/error.html?target=" + Uri.encode(targetUrl);
        view.loadUrl(errorPageUrl);
    }

    /**
     * Resets the retry budget and loads the real app again — called when
     * connectivity returns while the local error page is showing.
     */
    public void resetAndReload(WebView view) {
        retryCount = 0;
        retryExhaustedNotified = false;
        showingFriendlyError = false;
        view.loadUrl(targetUrl);
    }
}
