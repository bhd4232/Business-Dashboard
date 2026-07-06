# ERP Mobile App (Android, Capacitor)

The Android app is a thin native shell (Capacitor) that loads the live admin
panel at the URL configured in `capacitor.config.json` (`server.url`,
currently `https://app.zamzamint.com`). There is no separate mobile codebase
to maintain — any change deployed to the web admin panel shows up in the app
immediately, since it's just a WebView pointed at the production site. Login,
sessions, and permissions all work exactly as they do in a browser.

## Building an APK without Android Studio (recommended)

A GitHub Actions job (`build-android` in `.github/workflows/deploy.yml`)
builds a debug APK in the cloud — no local Android Studio/JDK needed:

1. Push to `main`, or open the repo's **Actions** tab and manually run the
   "CI" workflow (`workflow_dispatch`).
2. Once it finishes, open the workflow run and download the
   `business-dashboard-debug-apk` artifact (a zip containing `app-debug.apk`).
3. Copy the APK to an Android phone and install it (the phone will need
   "install unknown apps" allowed for the file manager/browser used).

This is a debug build (fine for internal testing/sideloading). A Play Store
release needs a signed release build — see below.

## One-time setup (per developer machine, only needed for local builds)

1. Install [Android Studio](https://developer.android.com/studio) (includes
   the JDK and Android SDK it needs).
2. Open this repo's `android/` folder in Android Studio, or run:
   ```
   npm run mobile:open
   ```
3. Let Gradle sync finish (first time can take a few minutes).

## Building

- Debug APK (for sideloading/testing on a device):
  ```
  npm run mobile:build
  ```
  Output: `android/app/build/outputs/apk/debug/app-debug.apk`
- Or use Android Studio's Run button with a device/emulator connected.
- For a Play Store release build (signed AAB), use Android Studio's
  Build > Generate Signed Bundle/APK — a release keystore will need to be
  created and kept safe (losing it means the app can never be updated again
  under the same listing).

## Changing the target URL

Edit `capacitor.config.json` at the project root (`server.url`), then run
`npm run mobile:sync` before rebuilding.

## Known issue on this dev machine

`npm run mobile:sync` / `npx cap sync android` can fail with `EPERM` errors
while copying files into `android/`. This happens on machines where an
antivirus (Windows Defender real-time protection) is actively scanning the
project folder and briefly locks newly written files. It does not affect the
app's behavior — `capacitor.config.json` (which controls what URL the app
loads) is already correctly generated inside `android/app/src/main/assets/`.
If it blocks a future sync, either retry the command, or add this project
folder to the antivirus exclusion list.
