package com.zamzamint.erp;

import android.content.Context;
import android.webkit.JavascriptInterface;
import com.google.firebase.FirebaseApp;

/**
 * Exposes one minimal, read-only check to the web app (window.ZzNativeBridge)
 * so it can avoid calling the Capacitor Push Notifications plugin's
 * requestPermissions()/register() when Firebase itself was never initialized
 * -- i.e. no valid google-services.json was present at build time.
 *
 * Why this exists: @capacitor/push-notifications' register() calls
 * FirebaseMessaging.getInstance() with no try/catch around it. When
 * FirebaseApp was never initialized, that throws IllegalStateException
 * synchronously and uncaught, which crashes the whole app -- reliably, on
 * every launch, the moment the user has granted (or already has) the
 * notification permission, since the JS layer calls register() right after.
 * See resources/js/push-notifications.js for the corresponding guard.
 *
 * Once a real google-services.json is added to android/app/ (owner-provided
 * Firebase project config -- this repo does not fabricate one), Firebase
 * auto-initializes at app startup and isPushNotificationsAvailable() starts
 * returning true with no further code changes needed here.
 */
public class PushAvailabilityBridge {

    private final Context context;

    public PushAvailabilityBridge(Context context) {
        this.context = context.getApplicationContext();
    }

    @JavascriptInterface
    public boolean isPushNotificationsAvailable() {
        return !FirebaseApp.getApps(context).isEmpty();
    }
}
