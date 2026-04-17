// ============================================================
// TuDu Service Worker - v2.0
// Solo activo en PRODUCCIÓN. En desarrollo se auto-destruye.
// ============================================================

const CACHE_NAME = 'TuDu-v2.0';
const IS_DEV = self.location.port === '5173';

// Si estamos en el servidor de Vite (desarrollo), nos auto-eliminamos
if (IS_DEV) {
  self.addEventListener('install', () => {
    self.skipWaiting();
  });
  self.addEventListener('activate', event => {
    event.waitUntil(
      // Limpiar todas las cachés antiguas
      caches.keys().then(keys =>
        Promise.all(keys.map(k => caches.delete(k)))
      ).then(() => {
        // Auto-desregistrarse
        return self.registration.unregister();
      }).then(() => {
        console.log('[SW] Auto-desregistrado en entorno de desarrollo.');
        return self.clients.claim();
      })
    );
  });
  // En desarrollo, no interceptar nada
} else {
  // ---- PRODUCCIÓN ----

  self.addEventListener('install', event => {
    console.log('[SW] Instalando Service Worker v2.0...');
    self.skipWaiting();
  });

  self.addEventListener('activate', event => {
    console.log('[SW] Activando Service Worker v2.0...');
    event.waitUntil(
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames
            .filter(name => name !== CACHE_NAME)
            .map(name => {
              console.log('[SW] Eliminando caché antigua:', name);
              return caches.delete(name);
            })
        );
      }).then(() => self.clients.claim())
    );
  });

  self.addEventListener('fetch', event => {
    // Solo cachear peticiones GET
    if (event.request.method !== 'GET') return;

    // No interceptar API calls ni peticiones cross-origin
    const url = new URL(event.request.url);
    if (url.pathname.startsWith('/api/') || url.origin !== self.location.origin) return;

    event.respondWith(
      caches.match(event.request).then(cached => {
        if (cached) return cached;

        return fetch(event.request).then(response => {
          // Solo cachear respuestas válidas de recursos estáticos
          if (response.ok && (
            url.pathname.match(/\.(js|css|png|jpg|svg|ico|woff2?)$/)
          )) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
          }
          return response;
        }).catch(() => {
          // Sin fallback offline por ahora
        });
      })
    );
  });

  // ── Push Notifications ──────────────────────────────────────────────────────

  self.addEventListener('push', event => {
    event.waitUntil(
      // Fetch notification data from server (uses session cookie)
      fetch('/tudu_development/api/get_pending_notifications.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
          const notifications = (data.notifications && data.notifications.length > 0)
            ? data.notifications
            : [{ title: 'TuDu', body: 'Tienes actualizaciones pendientes.', url: '/' }];

          const n = notifications[0];
          return self.registration.showNotification(n.title, {
            body: n.body,
            icon: '/tudu_development/icons/icon-192x192.png',
            badge: '/tudu_development/icons/badge-72x72.png',
            tag: 'tudu-notification',
            renotify: true,
            data: { url: n.url || '/' },
            actions: [
              { action: 'open', title: '📋 Ver tarea' },
              { action: 'close', title: 'Cerrar' },
            ],
          });
        })
        .catch(() => {
          // Fallback if server is unreachable
          return self.registration.showNotification('TuDu', {
            body: 'Tienes actualizaciones pendientes.',
            icon: '/tudu_development/icons/icon-192x192.png',
            data: { url: '/' },
          });
        })
    );
  });

  self.addEventListener('notificationclick', event => {
    event.notification.close();

    if (event.action === 'close') return;

    const targetUrl = event.notification.data?.url || '/';

    event.waitUntil(
      self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
        // Focus existing window if already open
        for (const client of windowClients) {
          if (client.url.includes(self.location.origin)) {
            client.focus();
            client.navigate(self.location.origin + '/tudu_development' + targetUrl);
            return;
          }
        }
        // Open new window
        return self.clients.openWindow('/tudu_development' + targetUrl);
      })
    );
  });
}
