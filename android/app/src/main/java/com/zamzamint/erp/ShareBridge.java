package com.zamzamint.erp;

import android.app.Activity;
import android.content.ActivityNotFoundException;
import android.content.ClipData;
import android.content.ContentResolver;
import android.content.ContentValues;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;
import android.os.Parcelable;
import android.provider.MediaStore;
import android.util.Base64;
import android.webkit.JavascriptInterface;
import androidx.core.content.FileProvider;
import java.io.File;
import java.io.FileOutputStream;
import java.io.OutputStream;
import java.util.ArrayList;
import java.util.List;

/**
 * Sends an image made by the web app (the order summary card) straight to
 * WhatsApp, WeChat, Messenger or Telegram, and saves it to the phone's
 * gallery (window.ZzShareBridge).
 *
 * Why this exists: inside this app's WebView neither the browser share sheet
 * (navigator.share) nor blob downloads (<a download>) work, and a web link
 * can only ever carry text to those apps, never an image. Only a native
 * ACTION_SEND intent can hand an image file to a chosen app.
 *
 * Methods return a short status string the page shows a message for:
 * "ok", "saved", "not_installed" or "error".
 */
public class ShareBridge {

    private final Activity activity;

    public ShareBridge(Activity activity) {
        this.activity = activity;
    }

    @JavascriptInterface
    public boolean isAvailable() {
        return true;
    }

    /**
     * @param base64Png the PNG without the "data:image/png;base64," prefix
     * @param target    whatsapp | wechat | messenger | telegram, or "" for the
     *                  normal Android share sheet
     * @param phone     customer's number with country code (8801…); WhatsApp
     *                  opens that customer's chat directly. May be empty.
     */
    @JavascriptInterface
    public String shareImage(String base64Png, String fileName, String target, String phone) {
        try {
            Uri uri = writeToCache(base64Png, fileName);
            List<Intent> intents = new ArrayList<>();

            for (String packageName : packagesFor(target)) {
                if (isInstalled(packageName)) {
                    Intent intent = sendIntent(uri);
                    intent.setPackage(packageName);

                    if ("whatsapp".equals(target) && phone != null && !phone.trim().isEmpty()) {
                        intent.putExtra("jid", phone.replaceAll("\\D+", "") + "@s.whatsapp.net");
                    }

                    intents.add(intent);
                }
            }

            final Intent launch;

            if (target == null || target.isEmpty()) {
                launch = Intent.createChooser(sendIntent(uri), null);
            } else if (intents.isEmpty()) {
                return "not_installed";
            } else if (intents.size() == 1) {
                launch = intents.get(0);
            } else {
                // e.g. both WhatsApp and WhatsApp Business are installed:
                // let the user pick which one, showing only those two.
                launch = Intent.createChooser(intents.remove(0), null);
                launch.putExtra(Intent.EXTRA_INITIAL_INTENTS, intents.toArray(new Parcelable[0]));
            }

            activity.runOnUiThread(() -> {
                try {
                    activity.startActivity(launch);
                } catch (ActivityNotFoundException ignored) {
                    // The app vanished between the check and the launch.
                }
            });

            return "ok";
        } catch (Exception e) {
            return "error";
        }
    }

    /**
     * Saves the PNG into the gallery (Pictures/ZamZam). Android 9 and older
     * need a storage permission for that, so they get the share sheet
     * instead, where "Save to device" / Drive / Files are offered.
     */
    @JavascriptInterface
    public String saveImage(String base64Png, String fileName) {
        try {
            if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) {
                return shareImage(base64Png, fileName, "", "");
            }

            ContentResolver resolver = activity.getContentResolver();
            ContentValues values = new ContentValues();
            values.put(MediaStore.MediaColumns.DISPLAY_NAME, safeName(fileName));
            values.put(MediaStore.MediaColumns.MIME_TYPE, "image/png");
            values.put(MediaStore.MediaColumns.RELATIVE_PATH, Environment.DIRECTORY_PICTURES + "/ZamZam");
            values.put(MediaStore.MediaColumns.IS_PENDING, 1);

            Uri item = resolver.insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, values);

            if (item == null) {
                return "error";
            }

            try (OutputStream out = resolver.openOutputStream(item)) {
                if (out == null) {
                    return "error";
                }
                out.write(Base64.decode(base64Png, Base64.DEFAULT));
            }

            values.clear();
            values.put(MediaStore.MediaColumns.IS_PENDING, 0);
            resolver.update(item, values, null, null);

            return "saved";
        } catch (Exception e) {
            return "error";
        }
    }

    private Intent sendIntent(Uri uri) {
        Intent intent = new Intent(Intent.ACTION_SEND);
        intent.setType("image/png");
        intent.putExtra(Intent.EXTRA_STREAM, uri);
        intent.setClipData(ClipData.newRawUri("", uri));
        intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);

        return intent;
    }

    private Uri writeToCache(String base64Png, String fileName) throws Exception {
        File dir = new File(activity.getCacheDir(), "shared-images");

        if (!dir.exists() && !dir.mkdirs()) {
            throw new IllegalStateException("Cannot create share folder");
        }

        File file = new File(dir, safeName(fileName));

        try (FileOutputStream out = new FileOutputStream(file)) {
            out.write(Base64.decode(base64Png, Base64.DEFAULT));
        }

        return FileProvider.getUriForFile(activity, activity.getPackageName() + ".fileprovider", file);
    }

    private static String safeName(String fileName) {
        String name = fileName == null ? "" : fileName.replaceAll("[^A-Za-z0-9._-]", "-");

        if (name.isEmpty()) {
            name = "order-summary.png";
        }

        return name.toLowerCase().endsWith(".png") ? name : name + ".png";
    }

    private static String[] packagesFor(String target) {
        if (target == null) {
            return new String[0];
        }

        switch (target) {
            case "whatsapp":
                return new String[] { "com.whatsapp", "com.whatsapp.w4b" };
            case "wechat":
                return new String[] { "com.tencent.mm" };
            case "messenger":
                return new String[] { "com.facebook.orca", "com.facebook.mlite" };
            case "telegram":
                return new String[] { "org.telegram.messenger", "org.telegram.messenger.web" };
            default:
                return new String[0];
        }
    }

    private boolean isInstalled(String packageName) {
        try {
            activity.getPackageManager().getPackageInfo(packageName, 0);

            return true;
        } catch (PackageManager.NameNotFoundException e) {
            return false;
        }
    }
}
