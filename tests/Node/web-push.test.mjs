import assert from 'node:assert/strict';
import test from 'node:test';
import {
    canAttemptWebPush,
    readFirebaseWebConfig,
    webInstallationId,
    WEB_PUSH_INSTALLATION_STORAGE_KEY,
} from '../../resources/js/web-push.js';

const androidCapacitor = {
    isNativePlatform: () => true,
    getPlatform: () => 'android',
};

const webCapacitor = {
    isNativePlatform: () => false,
    getPlatform: () => 'web',
};

function documentWithDataset(dataset) {
    return {
        querySelector: (selector) => (selector === '#zz-app-updater-config' ? { dataset } : null),
    };
}

test('readFirebaseWebConfig reads every field from the shared config element', () => {
    const documentObject = documentWithDataset({
        firebaseWebConfigured: 'true',
        firebaseVapidKey: 'vapid-key',
        serviceWorkerUrl: '/firebase-messaging-sw.js',
        pushRegisterUrl: '/admin/push-devices',
        firebaseApiKey: 'api-key',
        firebaseAuthDomain: 'app.firebaseapp.com',
        firebaseProjectId: 'zamzam-erp-app',
        firebaseStorageBucket: 'zamzam-erp-app.firebasestorage.app',
        firebaseMessagingSenderId: '569311109559',
        firebaseAppId: '1:569311109559:web:abc',
    });

    assert.deepEqual(readFirebaseWebConfig(documentObject), {
        configured: true,
        vapidKey: 'vapid-key',
        serviceWorkerUrl: '/firebase-messaging-sw.js',
        pushRegisterUrl: '/admin/push-devices',
        config: {
            apiKey: 'api-key',
            authDomain: 'app.firebaseapp.com',
            projectId: 'zamzam-erp-app',
            storageBucket: 'zamzam-erp-app.firebasestorage.app',
            messagingSenderId: '569311109559',
            appId: '1:569311109559:web:abc',
        },
    });
});

test('readFirebaseWebConfig defaults safely when the config element is missing', () => {
    const result = readFirebaseWebConfig({ querySelector: () => null });

    assert.equal(result.configured, false);
    assert.equal(result.vapidKey, '');
    assert.equal(result.serviceWorkerUrl, '/firebase-messaging-sw.js');
});

test('webInstallationId is stable across calls and namespaced separately from the Android id', () => {
    const values = new Map();
    const storage = {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
    };
    const cryptoImplementation = { randomUUID: () => '7fcfa20f-3a3d-4b52-8d90-5ca0eb86f7f2' };

    const id = webInstallationId(storage, cryptoImplementation);

    assert.equal(id, 'web:7fcfa20f-3a3d-4b52-8d90-5ca0eb86f7f2');
    assert.equal(values.get(WEB_PUSH_INSTALLATION_STORAGE_KEY), id);
    assert.equal(webInstallationId(storage, { randomUUID: () => 'should-not-replace' }), id);
});

test('canAttemptWebPush refuses to run inside the native Android WebView', () => {
    const documentObject = documentWithDataset({
        firebaseWebConfigured: 'true',
        firebaseVapidKey: 'vapid-key',
        pushRegisterUrl: '/admin/push-devices',
    });

    assert.equal(canAttemptWebPush({
        capacitor: androidCapacitor,
        navigatorObject: { serviceWorker: {} },
        notificationApi: { permission: 'default' },
        documentObject,
    }), false);
});

test('canAttemptWebPush requires browser support, permission not denied, and full server config', () => {
    const configuredDocument = documentWithDataset({
        firebaseWebConfigured: 'true',
        firebaseVapidKey: 'vapid-key',
        pushRegisterUrl: '/admin/push-devices',
    });
    const base = {
        capacitor: webCapacitor,
        navigatorObject: { serviceWorker: {} },
        notificationApi: { permission: 'default' },
        documentObject: configuredDocument,
    };

    assert.equal(canAttemptWebPush(base), true);

    // No service worker support in this browser.
    assert.equal(canAttemptWebPush({ ...base, navigatorObject: {} }), false);

    // No Notification API at all.
    assert.equal(canAttemptWebPush({ ...base, notificationApi: undefined }), false);

    // Permission already permanently denied -- never re-prompt.
    assert.equal(canAttemptWebPush({ ...base, notificationApi: { permission: 'denied' } }), false);

    // Server-side push not configured yet (Settings page incomplete).
    assert.equal(canAttemptWebPush({
        ...base,
        documentObject: documentWithDataset({ firebaseWebConfigured: 'false' }),
    }), false);
});
