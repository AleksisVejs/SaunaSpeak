// Self-destructing service worker, served as /sw.js ONLY on the legacy
// domain (see backend/public/.htaccess). Browsers that installed the PWA on
// saunaspeak.fraksis.com are pinned to the cached shell there - the old
// worker serves every navigation from cache, so they never see the 301 to
// saunaspeak.com, and the worker itself can't update because its update
// fetch now hits a cross-origin redirect (which browsers reject for worker
// scripts). This replacement unregisters, wipes the caches, and reloads the
// open tabs - the next navigation reaches the network and follows the 301.
self.addEventListener('install', () => self.skipWaiting())

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    await self.registration.unregister()
    for (const key of await caches.keys()) {
      await caches.delete(key)
    }
    const clients = await self.clients.matchAll({ type: 'window' })
    for (const client of clients) {
      client.navigate(client.url)
    }
  })())
})
