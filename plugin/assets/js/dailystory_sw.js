'use strict';

self.addEventListener('push', function (event) {
    let json = event.data.json();

    const notificationPromise = self.registration.showNotification(json.title, json.options);
    event.waitUntil(notificationPromise);
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    if (!event.notification.data)
        return;

    event.waitUntil(
        clients.openWindow(event.notification.data)
    );
});