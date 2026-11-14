// NEXUS-L service worker: shows push notifications while the site is closed, and opens the right page when one is clicked.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
  let d = {};
  try { d = event.data ? event.data.json() : {}; } catch (e) { d = { title: 'NEXUS-L', body: event.data ? event.data.text() : '' }; }
  const title = d.title || 'NEXUS-L';
  event.waitUntil(self.registration.showNotification(title, {
    body: d.body || '',
    icon: self.registration.scope + 'favicon.png',
    badge: self.registration.scope + 'favicon.png',
    tag: d.tag || undefined,
    renotify: !!d.tag,
    data: { url: d.url || '' },
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = new URL((event.notification.data && event.notification.data.url) || '', self.registration.scope).href;
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
    for (const c of list) {
      if (c.url.startsWith(self.registration.scope) && 'focus' in c) { c.navigate(target); return c.focus(); }
    }
    return self.clients.openWindow(target);
  }));
});
