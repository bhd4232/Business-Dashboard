import { App } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import { PushNotifications } from '@capacitor/push-notifications';

export const APP_UPDATE_PUSH_KIND = 'app-update';
export const APP_UPDATE_CHANNEL_ID = 'app-updates';
export const PUSH_INSTALLATION_STORAGE_KEY = 'zz-native-push-installation-id';

let initializationPromise = null;

export function isNativeAndroid(capacitor = Capacitor) {
    return capacitor?.isNativePlatform?.() === true
        && capacitor?.getPlatform?.() === 'android';
}

export function permissionAction(receive) {
    if (receive === 'granted') {
        return 'register';
    }

    if (receive === 'prompt' || receive === 'prompt-with-rationale') {
        return 'request';
    }

    return 'skip';
}

export function extractPushData(payload) {
    const data = payload?.notification?.data ?? payload?.data;

    return data && typeof data === 'object' && !Array.isArray(data)
        ? data
        : {};
}

export function isAppUpdatePush(payload) {
    return extractPushData(payload).kind === APP_UPDATE_PUSH_KIND;
}

export function installationId(
    storage = globalThis.localStorage,
    cryptoImplementation = globalThis.crypto,
) {
    try {
        const existing = storage?.getItem?.(PUSH_INSTALLATION_STORAGE_KEY)?.trim();

        if (existing && existing.length <= 191) {
            return existing;
        }

        const generated = cryptoImplementation?.randomUUID?.();

        if (typeof generated !== 'string' || !generated.trim()) {
            return null;
        }

        const value = `android:${generated.trim()}`;

        storage?.setItem?.(PUSH_INSTALLATION_STORAGE_KEY, value);

        return value;
    } catch {
        // Token hashing still gives the server an idempotent fallback when
        // WebView storage is unavailable or restricted.
        return null;
    }
}

export function nativeAppVersion(info) {
    const version = typeof info?.version === 'string'
        ? info.version.trim()
        : '';
    const build = typeof info?.build === 'string'
        ? info.build.trim()
        : '';

    if (!version) {
        return build || null;
    }

    return build ? `${version}+${build}` : version;
}

export function buildPushRegistrationPayload(
    token,
    platform = 'android',
    deviceId = null,
    appVersion = null,
) {
    const value = typeof token === 'string' ? token.trim() : '';
    const normalizedPlatform = typeof platform === 'string'
        ? platform.trim().toLowerCase()
        : '';

    if (!value || normalizedPlatform !== 'android') {
        return null;
    }

    const payload = {
        token: value,
        platform: normalizedPlatform,
    };

    const normalizedDeviceId = typeof deviceId === 'string'
        ? deviceId.trim()
        : '';
    const normalizedAppVersion = typeof appVersion === 'string'
        ? appVersion.trim()
        : '';

    if (normalizedDeviceId) {
        payload.device_id = normalizedDeviceId.slice(0, 191);
    }

    if (normalizedAppVersion) {
        payload.app_version = normalizedAppVersion.slice(0, 64);
    }

    return payload;
}

export function sameOriginUrl(value, origin) {
    if (!value || !origin) {
        return null;
    }

    try {
        const url = new URL(value, origin);

        return url.origin === origin ? url.toString() : null;
    } catch {
        return null;
    }
}

export function deliverAppUpdatePush(payload, targetWindow = globalThis.window) {
    const data = extractPushData(payload);

    if (data.kind !== APP_UPDATE_PUSH_KIND || !targetWindow) {
        return false;
    }

    if (typeof targetWindow.ZamZamAppUpdater?.receiveUpdateNotification === 'function') {
        targetWindow.ZamZamAppUpdater.receiveUpdateNotification(data);

        return true;
    }

    // A cold-start notification action can arrive before the updater module
    // has finished booting. Preserve the signal for that module and also
    // dispatch an event for the narrow race where its listener already exists.
    targetWindow.__zzPendingAppUpdateSignal = data;

    const CustomEventConstructor = targetWindow.CustomEvent;

    if (
        typeof CustomEventConstructor === 'function'
        && typeof targetWindow.dispatchEvent === 'function'
    ) {
        targetWindow.dispatchEvent(new CustomEventConstructor(
            'zz:app-update-available',
            { detail: data },
        ));
    }

    return true;
}

async function registerToken({
    token,
    platform,
    deviceId,
    appVersion,
    registerUrl,
    csrfToken,
    fetchImplementation,
}) {
    const payload = buildPushRegistrationPayload(
        token,
        platform,
        deviceId,
        appVersion,
    );

    if (!payload || !registerUrl || !csrfToken) {
        return false;
    }

    const response = await fetchImplementation(registerUrl, {
        method: 'POST',
        cache: 'no-store',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        throw new Error(`Push token registration failed with HTTP ${response.status}.`);
    }

    return true;
}

export async function initializeNativePushNotifications({
    capacitor = Capacitor,
    app = App,
    pushNotifications = PushNotifications,
    documentObject = globalThis.document,
    windowObject = globalThis.window,
    fetchImplementation = globalThis.fetch,
    storage = globalThis.localStorage,
    cryptoImplementation = globalThis.crypto,
} = {}) {
    if (
        !isNativeAndroid(capacitor)
        || !documentObject
        || !windowObject
        || typeof fetchImplementation !== 'function'
    ) {
        return false;
    }

    const config = documentObject.querySelector('#zz-app-updater-config');
    const registerUrl = sameOriginUrl(
        config?.dataset.pushRegisterUrl,
        windowObject.location?.origin,
    );
    const csrfToken = documentObject.querySelector('meta[name="csrf-token"]')?.content;

    if (!registerUrl || !csrfToken) {
        return false;
    }

    const deviceId = installationId(storage, cryptoImplementation);
    let appVersion = null;

    try {
        appVersion = nativeAppVersion(await app.getInfo());
    } catch {
        // Native package metadata is useful diagnostics, not a prerequisite
        // for receiving update notifications.
    }

    let pendingToken = null;
    let registeredToken = null;
    let registrationInFlight = null;

    const attemptRegistration = () => {
        if (!pendingToken || pendingToken === registeredToken) {
            return Promise.resolve(Boolean(registeredToken));
        }

        if (registrationInFlight) {
            return registrationInFlight;
        }

        const tokenBeingRegistered = pendingToken;
        let completedSuccessfully = false;

        registrationInFlight = registerToken({
            token: tokenBeingRegistered,
            platform: capacitor.getPlatform(),
            deviceId,
            appVersion,
            registerUrl,
            csrfToken,
            fetchImplementation,
        })
            .then(() => {
                completedSuccessfully = true;
                registeredToken = tokenBeingRegistered;

                return true;
            })
            .catch(() => {
                console.warn('Native push token registration could not be completed.');

                return false;
            })
            .finally(() => {
                registrationInFlight = null;

                // FCM can rotate the token while an older token is being
                // posted. Immediately persist the newest value afterwards.
                if (
                    completedSuccessfully
                    && pendingToken
                    && pendingToken !== registeredToken
                ) {
                    void attemptRegistration();
                }
            });

        return registrationInFlight;
    };

    await pushNotifications.addListener('registration', ({ value }) => {
        const token = typeof value === 'string' ? value.trim() : '';

        if (!token) {
            return;
        }

        pendingToken = token;
        void attemptRegistration();
    });

    await pushNotifications.addListener('registrationError', () => {
        console.warn('Native push registration failed.');
    });

    await pushNotifications.addListener('pushNotificationReceived', (notification) => {
        deliverAppUpdatePush(notification, windowObject);
    });

    await pushNotifications.addListener('pushNotificationActionPerformed', (action) => {
        deliverAppUpdatePush(action, windowObject);
    });

    windowObject.addEventListener('online', () => void attemptRegistration());
    windowObject.addEventListener('focus', () => void attemptRegistration());

    await pushNotifications.createChannel({
        id: APP_UPDATE_CHANNEL_ID,
        name: 'App updates',
        description: 'New ZamZam ERP versions that are ready to install',
        importance: 4,
        visibility: 1,
        sound: 'default',
        vibration: true,
    });

    let permission = await pushNotifications.checkPermissions();
    let action = permissionAction(permission.receive);

    if (action === 'request') {
        permission = await pushNotifications.requestPermissions();
        action = permissionAction(permission.receive);
    }

    if (action !== 'register') {
        return false;
    }

    await pushNotifications.register();

    return true;
}

function startNativePushNotifications() {
    initializationPromise ??= initializeNativePushNotifications()
        .catch(() => {
            console.warn('Native push notifications could not be initialized.');

            return false;
        });

    return initializationPromise;
}

if (typeof window !== 'undefined' && typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            () => void startNativePushNotifications(),
            { once: true },
        );
    } else {
        void startNativePushNotifications();
    }
}
