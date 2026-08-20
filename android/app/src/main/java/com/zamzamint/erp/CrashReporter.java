package com.zamzamint.erp;

import android.content.Context;
import android.content.SharedPreferences;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.os.Build;
import android.util.Log;
import java.io.PrintWriter;
import java.io.StringWriter;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;
import java.util.TimeZone;
import org.json.JSONException;
import org.json.JSONObject;

/**
 * Saves an uncaught exception to local storage before the process dies, then
 * uploads it to the server the next time the app launches with connectivity
 * -- this repo has no crash-reporting SDK (no Firebase Crashlytics/Sentry),
 * and diagnosing a device-only crash otherwise means guessing blind. See
 * MobileCrashReportController (server side) and MainActivity (wires this in).
 *
 * Deliberately does not swallow the crash: after saving, it always chains to
 * the previously-installed default handler so normal Android crash/ANR
 * behavior (the OS's own "keeps stopping" dialog, process death) is
 * unchanged -- this only adds a side effect, it doesn't recover from errors.
 */
public class CrashReporter {

    private static final String TAG = "CrashReporter";
    private static final String PREFS_NAME = "zz_crash_reporter";
    private static final String KEY_PENDING_REPORT = "pending_report_json";
    private static final int CONNECT_TIMEOUT_MS = 5000;
    private static final int READ_TIMEOUT_MS = 5000;

    private CrashReporter() {
    }

    public static void install(Context context) {
        Context appContext = context.getApplicationContext();
        Thread.UncaughtExceptionHandler previousHandler = Thread.getDefaultUncaughtExceptionHandler();

        Thread.setDefaultUncaughtExceptionHandler((thread, throwable) -> {
            try {
                saveReport(appContext, throwable);
            } catch (Throwable savingFailed) {
                // Never let crash-reporting itself block or alter the real
                // crash -- log and fall through to the previous handler
                // regardless of what went wrong here.
                Log.e(TAG, "Failed to save crash report", savingFailed);
            }

            if (previousHandler != null) {
                previousHandler.uncaughtException(thread, throwable);
            }
        });
    }

    private static void saveReport(Context context, Throwable throwable) throws JSONException {
        StringWriter stackTraceWriter = new StringWriter();
        throwable.printStackTrace(new PrintWriter(stackTraceWriter));

        JSONObject report = new JSONObject();
        report.put("exception_class", throwable.getClass().getName());
        report.put("message", throwable.getMessage());
        report.put("stack_trace", stackTraceWriter.toString());
        report.put("app_version_name", versionName(context));
        report.put("app_version_code", versionCode(context));
        report.put("os_version", Build.VERSION.RELEASE);
        report.put("device_manufacturer", Build.MANUFACTURER);
        report.put("device_model", Build.MODEL);
        report.put("occurred_at", isoTimestampNow());

        prefs(context).edit().putString(KEY_PENDING_REPORT, report.toString()).commit();
    }

    /**
     * Uploads a previously-saved crash report, if any, to targetUrl's origin
     * (same server the WebView loads). Runs entirely on a background thread
     * with native networking -- independent of the WebView/JS, since a
     * broken WebView is exactly the scenario this needs to survive. Clears
     * the saved report only after a successful (2xx) upload; leaves it in
     * place to retry next launch otherwise.
     */
    public static void uploadPendingReportIfAny(Context context, String targetUrl) {
        Context appContext = context.getApplicationContext();

        new Thread(() -> {
            SharedPreferences prefs = prefs(appContext);
            String pendingReportJson = prefs.getString(KEY_PENDING_REPORT, null);

            if (pendingReportJson == null) {
                return;
            }

            try {
                String uploadUrl = uploadUrl(targetUrl);

                if (uploadUrl == null) {
                    return;
                }

                if (upload(uploadUrl, pendingReportJson)) {
                    prefs.edit().remove(KEY_PENDING_REPORT).apply();
                }
            } catch (Exception e) {
                // No connectivity, server unreachable, etc. -- leave the
                // saved report in place and quietly try again next launch.
                Log.w(TAG, "Crash report upload failed, will retry next launch", e);
            }
        }).start();
    }

    private static boolean upload(String uploadUrl, String jsonBody) throws Exception {
        HttpURLConnection connection = (HttpURLConnection) new URL(uploadUrl).openConnection();

        try {
            connection.setRequestMethod("POST");
            connection.setDoOutput(true);
            connection.setConnectTimeout(CONNECT_TIMEOUT_MS);
            connection.setReadTimeout(READ_TIMEOUT_MS);
            connection.setRequestProperty("Content-Type", "application/json");
            connection.setRequestProperty("Accept", "application/json");

            byte[] body = jsonBody.getBytes(StandardCharsets.UTF_8);
            connection.getOutputStream().write(body);

            int responseCode = connection.getResponseCode();

            return responseCode >= 200 && responseCode < 300;
        } finally {
            connection.disconnect();
        }
    }

    private static String uploadUrl(String targetUrl) {
        if (targetUrl == null) {
            return null;
        }

        String trimmed = targetUrl.endsWith("/")
            ? targetUrl.substring(0, targetUrl.length() - 1)
            : targetUrl;

        return trimmed + "/webhooks/mobile-crash-reports";
    }

    private static SharedPreferences prefs(Context context) {
        return context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
    }

    private static String versionName(Context context) {
        try {
            PackageInfo info = context.getPackageManager().getPackageInfo(context.getPackageName(), 0);

            return info.versionName;
        } catch (PackageManager.NameNotFoundException e) {
            return null;
        }
    }

    private static Integer versionCode(Context context) {
        try {
            PackageInfo info = context.getPackageManager().getPackageInfo(context.getPackageName(), 0);

            return info.versionCode;
        } catch (PackageManager.NameNotFoundException e) {
            return null;
        }
    }

    private static String isoTimestampNow() {
        SimpleDateFormat format = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'", Locale.US);

        format.setTimeZone(TimeZone.getTimeZone("UTC"));

        return format.format(new Date());
    }
}
