package com.zamzamint.erp;

import android.app.Application;

/**
 * Installs the crash reporter as early as possible -- before any Activity
 * is created, so a crash during Capacitor/WebView bootstrap is captured
 * too, not just crashes that happen once MainActivity is already running.
 */
public class ZamZamApplication extends Application {

    @Override
    public void onCreate() {
        super.onCreate();

        CrashReporter.install(this);
    }
}
