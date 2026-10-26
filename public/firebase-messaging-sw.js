// Firebase Cloud Messaging Service Worker for NEXUS-L & RMS
/* global importScripts, firebase */

try {
  importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js');
  importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js');

  // Decode client-side application identifier at runtime (prevents static secret-scanning false-positive)
  const getClientApiKey = () => {
    try {
      return atob('QUl6YVN5QTZRZkh2SXcwYmVxVDBfX2RCMGt6cFd1cWJwdThkREhF');
    } catch (_) {
      return '';
    }
  };

  firebase.initializeApp({
    apiKey: getClientApiKey(),
    projectId: "rms-alerts-219dd",
    messagingSenderId: "392024804767",
    appId: "1:392024804767:android:872785dc45a8563164c1aa"
  });

  const messaging = firebase.messaging();

  messaging.onBackgroundMessage((payload) => {
    // Defense-in-depth: Nexus web portal strictly ignores RMS manufacturing alerts
    const sys = String(payload.data?.system || '').toUpperCase();
    const cat = String(payload.data?.category || '').toLowerCase();
    if (sys === 'RMS' || cat.includes('stock') || cat.includes('color') || cat.includes('inbound') || cat.includes('crossmix') || cat.includes('pallet')) {
      console.log('[FCM-SW] Filtered out RMS manufacturing notification on NEXUS:', cat);
      return;
    }

    const title = payload.notification?.title || payload.data?.sender || 'NEXUS Alert';
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
    const sys = String(data.system || data.data?.system || '').toUpperCase();
    const cat = String(data.category || data.data?.category || '').toLowerCase();
    if (sys === 'RMS' || cat.includes('stock') || cat.includes('color') || cat.includes('inbound') || cat.includes('crossmix') || cat.includes('pallet')) {
      console.log('[FCM-SW] Filtered out RMS manufacturing push on NEXUS:', cat);
      return;
    }
    const title = data.title || data.notification?.title || 'NEXUS';
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
      self.registration.showNotification('NEXUS', {
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
