const CACHE_NAME = "gestione-libreria-v1";

const URLS_TO_CACHE = [
  "/EsercitazioneDiSviluppoWeb/",
  "/EsercitazioneDiSviluppoWeb/login.php",
  "/EsercitazioneDiSviluppoWeb/register.php",
  "/EsercitazioneDiSviluppoWeb/index.php",
  "/EsercitazioneDiSviluppoWeb/assets/logo.png",
  "/EsercitazioneDiSviluppoWeb/assets/icon-192.png",
  "/EsercitazioneDiSviluppoWeb/assets/icon-512.png"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(URLS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
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

self.addEventListener("fetch", (event) => {
  if (event.request.method !== "GET") {
    return;
  }

  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request).then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }
        return caches.match("/EsercitazioneDiSviluppoWeb/login.php");
      });
    })
  );
});
