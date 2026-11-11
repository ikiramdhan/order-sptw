// Service worker minimal: aplikasi bisa dipasang (PWA) tapi TIDAK ada transaksi offline.
// Saat tanpa koneksi, tampilkan halaman "tidak ada koneksi" (stok dan harga harus terbaru).
const CACHE = 'ck-shell-v36';
const SHELL = ['/offline.html', '/css/app.css', '/icons/logo.png'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }

  const url = new URL(req.url);
  if (url.origin === location.origin && (url.pathname.startsWith('/css/') || url.pathname.startsWith('/icons/') || url.pathname.startsWith('/js/'))) {
    event.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
  }
});
