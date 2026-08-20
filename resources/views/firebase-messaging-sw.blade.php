@if ($configured)
importScripts('https://www.gstatic.com/firebasejs/12.18.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/12.18.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: {{ Js::from($config['apiKey']) }},
    authDomain: {{ Js::from($config['authDomain']) }},
    projectId: {{ Js::from($config['projectId']) }},
    storageBucket: {{ Js::from($config['storageBucket']) }},
    messagingSenderId: {{ Js::from($config['messagingSenderId']) }},
    appId: {{ Js::from($config['appId']) }},
});

const messaging = firebase.messaging();

// FCM auto-displays a message that carries a top-level `notification`
// field (every push this app sends does); onBackgroundMessage only fires
// for pure data messages. Kept as a documented fallback so nothing is
// silently dropped if that ever changes.
messaging.onBackgroundMessage((payload) => {
    const data = payload.data || {};
    const title = data.title || (payload.notification && payload.notification.title) || 'ZamZam ERP';
    const body = data.body || (payload.notification && payload.notification.body) || '';

    self.registration.showNotification(title, {
        body,
        icon: '/favicon.ico',
        data,
    });
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const actionUrl = (event.notification.data && event.notification.data.action_url) || '/admin';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if ('focus' in client) {
                    client.navigate(actionUrl);
                    return client.focus();
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(actionUrl);
            }
        }),
    );
});
@else
// Firebase push notifications are not configured yet (Settings -> Push
// Notification Settings). This worker intentionally does nothing until
// they are -- registering it is harmless either way.
@endif
