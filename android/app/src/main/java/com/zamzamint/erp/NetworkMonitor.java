package com.zamzamint.erp;

import android.content.Context;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.NetworkRequest;
import android.os.Handler;
import android.os.Looper;

/**
 * Notifies the caller when internet connectivity becomes available again —
 * used to auto-reload the WebView after a Wi-Fi/mobile-data switch instead
 * of leaving the user stuck on the friendly error page.
 */
public class NetworkMonitor {

    public interface Listener {
        void onNetworkAvailable();
    }

    private final ConnectivityManager connectivityManager;
    private final Listener listener;
    private final Handler mainHandler = new Handler(Looper.getMainLooper());
    private boolean registered = false;

    private final ConnectivityManager.NetworkCallback networkCallback = new ConnectivityManager.NetworkCallback() {
        @Override
        public void onAvailable(Network network) {
            mainHandler.post(listener::onNetworkAvailable);
        }
    };

    public NetworkMonitor(Context context, Listener listener) {
        this.connectivityManager = (ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
        this.listener = listener;
    }

    public void register() {
        if (registered || connectivityManager == null) {
            return;
        }

        NetworkRequest request = new NetworkRequest.Builder()
            .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
            .build();

        connectivityManager.registerNetworkCallback(request, networkCallback);
        registered = true;
    }

    public void unregister() {
        if (!registered || connectivityManager == null) {
            return;
        }

        connectivityManager.unregisterNetworkCallback(networkCallback);
        registered = false;
    }
}
