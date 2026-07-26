import assert from 'node:assert/strict';
import test from 'node:test';
import {
    APP_UPDATE_CHANNEL_ID,
    PUSH_INSTALLATION_STORAGE_KEY,
    buildPushRegistrationPayload,
    deliverAppUpdatePush,
    extractPushData,
    initializeNativePushNotifications,
    installationId,
    isAppUpdatePush,
    isNativeAndroid,
    nativeAppVersion,
    permissionAction,
    sameOriginUrl,
} from '../../resources/js/push-notifications.js';

test('native push initializes only for the Android Capacitor runtime', () => {
    assert.equal(isNativeAndroid({
        isNativePlatform: () => true,
        getPlatform: () => 'android',
    }), true);
    assert.equal(isNativeAndroid({
        isNativePlatform: () => false,
        getPlatform: () => 'android',
    }), false);
    assert.equal(isNativeAndroid({
        isNativePlatform: () => true,
        getPlatform: () => 'web',
    }), false);
});

test('permission states request once and register only when granted', () => {
    assert.equal(permissionAction('granted'), 'register');
    assert.equal(permissionAction('prompt'), 'request');
    assert.equal(permissionAction('prompt-with-rationale'), 'request');
    assert.equal(permissionAction('denied'), 'skip');
});

test('registration payload accepts a non-empty Android FCM token only', () => {
    assert.deepEqual(buildPushRegistrationPayload(
        ' token-123 ',
        'android',
        ' android:installation-1 ',
        ' 1.22.0+22 ',
    ), {
        token: 'token-123',
        platform: 'android',
        device_id: 'android:installation-1',
        app_version: '1.22.0+22',
    });
    assert.equal(buildPushRegistrationPayload(''), null);
    assert.equal(buildPushRegistrationPayload('token-123', 'web'), null);
});

test('installation id is stable and app metadata includes the native build', () => {
    const values = new Map();
    const storage = {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
    };
    const cryptoImplementation = {
        randomUUID: () => '7fcfa20f-3a3d-4b52-8d90-5ca0eb86f7f2',
    };

    assert.equal(
        installationId(storage, cryptoImplementation),
        'android:7fcfa20f-3a3d-4b52-8d90-5ca0eb86f7f2',
    );
    assert.equal(
        installationId(storage, { randomUUID: () => 'should-not-replace' }),
        'android:7fcfa20f-3a3d-4b52-8d90-5ca0eb86f7f2',
    );
    assert.equal(
        values.get(PUSH_INSTALLATION_STORAGE_KEY),
        'android:7fcfa20f-3a3d-4b52-8d90-5ca0eb86f7f2',
    );
    assert.equal(nativeAppVersion({ version: '1.22.0', build: '22' }), '1.22.0+22');
    assert.equal(nativeAppVersion({ version: '1.22.0' }), '1.22.0');
});

test('registration endpoint must remain on the current origin', () => {
    assert.equal(
        sameOriginUrl('/admin/push-devices', 'https://app.example.com'),
        'https://app.example.com/admin/push-devices',
    );
    assert.equal(
        sameOriginUrl('https://evil.example/push', 'https://app.example.com'),
        null,
    );
});

test('app-update data is extracted from receive and tap payload shapes', () => {
    const received = {
        data: {
            kind: 'app-update',
            deployment_id: 'deploy-new',
        },
    };
    const tapped = {
        notification: {
            data: {
                kind: 'app-update',
                deployment_id: 'deploy-new',
            },
        },
    };

    assert.deepEqual(extractPushData(received), received.data);
    assert.deepEqual(extractPushData(tapped), tapped.notification.data);
    assert.equal(isAppUpdatePush(received), true);
    assert.equal(isAppUpdatePush({ data: { kind: 'business-alert' } }), false);
});

test('app-update push delegates to the updater without reloading or acknowledging', () => {
    const calls = [];
    const targetWindow = {
        ZamZamAppUpdater: {
            receiveUpdateNotification: (data) => calls.push(data),
        },
    };
    const data = {
        kind: 'app-update',
        deployment_id: 'deploy-new',
    };

    assert.equal(deliverAppUpdatePush({ data }, targetWindow), true);
    assert.deepEqual(calls, [data]);
    assert.equal(deliverAppUpdatePush({
        data: { kind: 'business-alert' },
    }, targetWindow), false);
    assert.equal(APP_UPDATE_CHANNEL_ID, 'app-updates');
    assert.equal('location' in targetWindow, false);
});

test('a cold-start update action is queued until the updater has booted', () => {
    const events = [];

    class TestCustomEvent {
        constructor(type, options) {
            this.type = type;
            this.detail = options.detail;
        }
    }

    const targetWindow = {
        CustomEvent: TestCustomEvent,
        dispatchEvent: (event) => events.push(event),
    };
    const data = {
        kind: 'app-update',
        deployment_id: 'deploy-cold-start',
    };

    assert.equal(deliverAppUpdatePush({
        notification: { data },
    }, targetWindow), true);
    assert.deepEqual(targetWindow.__zzPendingAppUpdateSignal, data);
    assert.equal(events[0].type, 'zz:app-update-available');
    assert.deepEqual(events[0].detail, data);
    assert.equal('location' in targetWindow, false);
});

test('native initialization registers a stable installation and app version', async () => {
    const listeners = new Map();
    const windowListeners = new Map();
    const requests = [];
    const pushNotifications = {
        addListener: async (name, listener) => {
            listeners.set(name, listener);
        },
        createChannel: async (channel) => {
            assert.equal(channel.id, APP_UPDATE_CHANNEL_ID);
        },
        checkPermissions: async () => ({ receive: 'granted' }),
        register: async () => {
            listeners.get('registration')({ value: ' fcm-token ' });
        },
    };
    const documentObject = {
        querySelector: (selector) => {
            if (selector === '#zz-app-updater-config') {
                return {
                    dataset: {
                        pushRegisterUrl: '/admin/push-devices',
                    },
                };
            }

            if (selector === 'meta[name="csrf-token"]') {
                return { content: 'csrf-token' };
            }

            return null;
        },
    };
    const windowObject = {
        location: {
            origin: 'https://app.example.com',
        },
        addEventListener: (name, listener) => {
            windowListeners.set(name, listener);
        },
    };
    const storageValues = new Map();

    assert.equal(await initializeNativePushNotifications({
        capacitor: {
            isNativePlatform: () => true,
            getPlatform: () => 'android',
        },
        app: {
            getInfo: async () => ({
                version: '1.22.0',
                build: '22',
            }),
        },
        pushNotifications,
        documentObject,
        windowObject,
        fetchImplementation: async (url, options) => {
            requests.push({ url, options });

            return {
                ok: true,
                status: 201,
            };
        },
        storage: {
            getItem: (key) => storageValues.get(key) ?? null,
            setItem: (key, value) => storageValues.set(key, value),
        },
        cryptoImplementation: {
            randomUUID: () => '7fcfa20f-3a3d-4b52-8d90-5ca0eb86f7f2',
        },
    }), true);

    await new Promise((resolve) => setTimeout(resolve, 0));

    assert.equal(requests.length, 1);
    assert.equal(
        requests[0].url,
        'https://app.example.com/admin/push-devices',
    );
    assert.deepEqual(JSON.parse(requests[0].options.body), {
        token: 'fcm-token',
        platform: 'android',
        device_id: 'android:7fcfa20f-3a3d-4b52-8d90-5ca0eb86f7f2',
        app_version: '1.22.0+22',
    });
    assert.equal(requests[0].options.headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.equal(windowListeners.has('online'), true);
    assert.equal(windowListeners.has('focus'), true);
});
