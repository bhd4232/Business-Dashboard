import {
    buildPushRegistrationPayload,
    isNativeAndroid,
    sameOriginUrl,
} from './push-notifications.js';

export const WEB_PUSH_INSTALLATION_STORAGE_KEY = 'zz-web-push-installation-id';

/**
 * Reads the Firebase Web SDK config rendered by
 * resources/views/filament/partials/app-updater.blade.php (the same
 * `#zz-app-updater-config` element push-notifications.js already reads
 * `data-push-register-url` from) -- avoids a second config element and a
 * second round-trip.
 */
export function readFirebaseWebConfig(documentObject = globalThis.document) {
    const dataset = documentObject?.querySelector?.('#zz-app-updater-config')?.dataset ?? {};

    return {
        configured: dataset.firebaseWebConfigured === 'true',
        vapidKey: dataset.firebaseVapidKey || '',
        serviceWorkerUrl: dataset.serviceWorkerUrl || '/firebase-messaging-sw.js',
        pushRegisterUrl: dataset.pushRegisterUrl || '',
        config: {
            apiKey: dataset.firebaseApiKey || '',
            authDomain: dataset.firebaseAuthDomain || '',
            projectId: dataset.firebaseProjectId || '',
            storageBucket: dataset.firebaseStorageBucket || '',
            messagingSenderId: dataset.firebaseMessagingSenderId || '',
            appId: dataset.firebaseAppId || '',
        },
    };
}

export function webInstallationId(
    storage = globalThis.localStorage,
    cryptoImplementation = globalThis.crypto,
) {
    try {
        const existing = storage?.getItem?.(WEB_PUSH_INSTALLATION_STORAGE_KEY)?.trim();

        if (existing && existing.length <= 191) {
            return existing;
        }

        const generated = cryptoImplementation?.randomUUID?.();

        if (typeof generated !== 'string' || !generated.trim()) {
            return null;
        }

        const value = `web:${generated.trim()}`;

        storage?.setItem?.(WEB_PUSH_INSTALLATION_STORAGE_KEY, value);

        return value;
    } catch {
        return null;
    }
}

export function canAttemptWebPush({
    capacitor,
    navigatorObject = globalThis.navigator,
    notificationApi = globalThis.Notification,
    documentObject = globalThis.document,
} = {}) {
    if (isNativeAndroid(capacitor)) {
        // The native Android app registers via FCM's Android SDK directly
        // (push-notifications.js); running the Web SDK inside that same
        // WebView would double-register the same user on two token types.
        return false;
    }

    if (!navigatorObject?.serviceWorker || !notificationApi) {
        return false;
    }

    if (notificationApi.permission === 'denied') {
        return false;
    }

    const { configured, vapidKey, pushRegisterUrl } = readFirebaseWebConfig(documentObject);

    return configured && Boolean(vapidKey) && Boolean(pushRegisterUrl);
}

export async function registerWebPushToken({
    documentObject = globalThis.document,
    windowObject = globalThis.window,
    fetchImplementation = globalThis.fetch,
    storage = globalThis.localStorage,
    cryptoImplementation = globalThis.crypto,
    navigatorObject = globalThis.navigator,
    notificationApi = globalThis.Notification,
    capacitor,
} = {}) {
    if (!canAttemptWebPush({ capacitor, navigatorObject, notificationApi, documentObject })) {
        return false;
    }

    const { vapidKey, serviceWorkerUrl, pushRegisterUrl, config } = readFirebaseWebConfig(documentObject);

    // Loaded on demand -- the Firebase Web SDK is only fetched when a real
    // browser tab is actually attempting web push, never inside the
    // Android WebView (canAttemptWebPush() already returned false there).
    const [{ initializeApp }, { getMessaging, getToken, onMessage, isSupported }] = await Promise.all([
        import('firebase/app'),
        import('firebase/messaging'),
    ]);

    if (!(await isSupported().catch(() => false))) {
        return false;
    }

    if (notificationApi.permission !== 'granted') {
        const permission = await notificationApi.requestPermission();

        if (permission !== 'granted') {
            return false;
        }
    }

    const registration = await navigatorObject.serviceWorker.register(serviceWorkerUrl);
    const app = initializeApp(config);
    const messaging = getMessaging(app);
    const token = await getToken(messaging, { vapidKey, serviceWorkerRegistration: registration });

    if (!token) {
        return false;
    }

    const registerUrl = sameOriginUrl(pushRegisterUrl, windowObject.location?.origin);
    const csrfToken = documentObject.querySelector('meta[name="csrf-token"]')?.content;
    const payload = buildPushRegistrationPayload(
        token,
        'web',
        webInstallationId(storage, cryptoImplementation),
        null,
    );

    if (!registerUrl || !csrfToken || !payload) {
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
        return false;
    }

    // Only background messages are auto-displayed by FCM; a foreground tab
    // has to show its own notification via the same registered worker so
    // behavior is consistent regardless of tab focus.
    onMessage(messaging, (message) => {
        const data = message.data || {};
        const title = data.title || message.notification?.title || 'ZamZam ERP';
        const body = data.body || message.notification?.body || '';

        void registration.showNotification(title, { body, icon: '/favicon.ico', data });
    });

    return true;
}

function startWebPush() {
    void registerWebPushToken().catch(() => {
        console.warn('Web push notifications could not be initialized.');
    });
}

if (typeof window !== 'undefined' && typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startWebPush, { once: true });
    } else {
        startWebPush();
    }
}
