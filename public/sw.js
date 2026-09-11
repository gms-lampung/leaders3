const CACHE_NAME = 'leaders3-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys
        .filter((k) => k.startsWith('leaders3-') && k !== CACHE_NAME)
        .map((k) => caches.delete(k)))
      )
      .then(() => self.clients.claim())
  );
});

// Network-first dengan fallback cache saat offline.
// Halaman PHP tidak di-cache agresif agar data admin selalu segar.
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET' || !req.url.startsWith(self.location.origin)) return;
  const url = new URL(req.url);
  const isAsset = url.pathname.startsWith('/css/') ||
                  url.pathname.startsWith('/icons/') ||
                  url.pathname === '/manifest.json';
  if (!isAsset) return; // halaman dinamis: biarkan lewat jaringan saja
  e.respondWith(
    fetch(req)
      .then((res) => {
        if (res.ok) {
          const copy = res.clone();
          caches.open(CACHE_NAME).then((c) => c.put(req, copy)).catch(() => {});
        }
        return res;
      })
      .catch(() => caches.match(req).then((m) => m || Response.error()))
  );
});
