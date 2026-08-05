const CACHE_NAME = 'vestapp-shell-v3';
const APP_SHELL = ['/manifest.webmanifest', '/images/logo.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('push', (event) => {
    let notification = {};

    try {
        notification = event.data?.json() || {};
    } catch {
        notification = { body: event.data?.text() || 'Tienes una nueva tarea.' };
    }

    event.waitUntil(self.registration.showNotification(notification.title || 'Vestapp', {
        body: notification.body,
        icon: notification.icon || '/images/logo.png',
        badge: notification.badge || '/images/logo.png',
        tag: notification.tag,
        data: { url: notification.url || '/' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const destination = new URL(event.notification.data?.url || '/', self.location.origin).href;

    event.waitUntil(self.clients.openWindow(destination));
});
