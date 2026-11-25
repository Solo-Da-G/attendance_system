const CACHE_NAME = 'tds-attendance-v3';
const ASSETS = [
  '/',
  '/asset/css/style.css',
  '/asset/img/tds_logo.png',
  '/asset/img/icon-192.png',
  '/asset/img/icon-512.png',
  '/asset/img/icon-maskable-512.png',
  '/manifest.json'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS).catch(() => {});
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Network first with cache fallback for dynamic PHP app
  if (event.request.method !== 'GET') return;
  
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (response && response.status === 200 && response.type === 'basic') {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return response;
      })
      .catch(() => caches.match(event.request))
  );
});

// ── Web Push Notifications Listener (Background Reminders) ──
self.addEventListener('push', (event) => {
  let data = {
    title: '⏰ TDS Attendance Reminder',
    body: 'Please remember to verify your attendance for today.',
    icon: '/asset/img/icon-192.png',
    badge: '/asset/img/icon-192.png',
    url: '/api/dashboard.php'
  };

  if (event.data) {
    try {
      data = Object.assign(data, event.data.json());
    } catch (_) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: data.icon || '/asset/img/icon-192.png',
    badge: data.badge || '/asset/img/icon-192.png',
    vibrate: [300, 150, 300, 150, 500],
    data: { url: data.url || '/api/dashboard.php' },
    actions: [
      { action: 'open', title: '👆 Open Attendance' }
    ],
    requireInteraction: true
  };

  event.waitUntil(self.registration.showNotification(data.title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = (event.notification.data && event.notification.data.url) || '/api/dashboard.php';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (let client of clientList) {
        if (client.url.includes('/api/dashboard.php') && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
