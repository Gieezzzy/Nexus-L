// Firebase Cloud Messaging Service Worker for NEXUS-L & RMS
/* global importScripts, firebase */

try {
  importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js');
  importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js');

  firebase.initializeApp({
    apiKey: "AIzaSyA6QfHvIw0beqT0__dB0kzpWuqbpu8dDHE",
    projectId: "rms-alerts-219dd",
    messagingSenderId: "392024804767",
    appId: "1:392024804767:android:872785dc45a8563164c1aa"
  });

  const messaging = firebase.messaging();

  messaging.onBackgroundMessage((payload) => {
    const title = payload.notification?.title || payload.data?.sender || 'RMS / NEXUS-L Alert';
    const body  = payload.notification?.body  || payload.data?.message || 'You received a new message';
    const icon  = '/NEXUS-L/public/assets/favicon.ico';
    const tag   = payload.data?.conversation_id || 'nexus-inbox-msg';

    self.registration.showNotification(title, {
      body: body,
      icon: icon,
      tag: tag,
      data: payload.data
    });
  });
} catch (e) {
  console.warn('[FCM-SW] Firebase scripts failed to load or initialize:', e);
}

// Fallback for native push events
self.addEventListener('push', (event) => {
  if (!event.data) return;
  try {
    const data = event.data.json();
    const title = data.title || data.notification?.title || 'RMS / NEXUS-L';
    const body  = data.body  || data.notification?.body  || 'New message received';
    event.waitUntil(
      self.registration.showNotification(title, {
        body: body,
        icon: '/NEXUS-L/public/assets/favicon.ico',
        tag: data.tag || 'nexus-push-alert',
        data: data
      })
    );
  } catch (_) {
    event.waitUntil(
      self.registration.showNotification('RMS / NEXUS-L', {
        body: event.data.text(),
        icon: '/NEXUS-L/public/assets/favicon.ico'
      })
    );
  }
});

// Notification click event: focus active window or open inbox
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      for (const client of windowClients) {
        if (client.url.includes('/inbox') && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow('/NEXUS-L/public/inbox');
      }
    })
  );
});
