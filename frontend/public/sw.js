// ================================================================
// HorizonAI — Service Worker (kiosque offline)
// Stratégie: CacheFirst pour assets, NetworkFirst pour API
// ================================================================

const CACHE_NAME    = 'horizonai-v1'
const SYNC_TAG      = 'interview-sync'

const STATIC_ASSETS = [
  '/',
  '/kiosk',
  '/index.html',
]

// Installation — précache des assets statiques
self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME).then(c => c.addAll(STATIC_ASSETS)).catch(() => {})
  )
  self.skipWaiting()
})

// Activation — nettoyer anciens caches
self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  )
  self.clients.claim()
})

// Fetch — stratégie hybride
self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url)

  // Requêtes API : NetworkFirst (pas de cache pour POST)
  if (url.pathname.startsWith('/api/')) {
    if (e.request.method !== 'GET') return // POST/PUT passent toujours
    e.respondWith(
      fetch(e.request).catch(() =>
        caches.match(e.request).then(r => r || new Response('{"error":"offline"}', {
          headers: { 'Content-Type': 'application/json' }
        }))
      )
    )
    return
  }

  // Assets statiques : CacheFirst
  e.respondWith(
    caches.match(e.request).then(cached => {
      if (cached) return cached
      return fetch(e.request).then(res => {
        if (res.ok && e.request.method === 'GET') {
          const clone = res.clone()
          caches.open(CACHE_NAME).then(c => c.put(e.request, clone))
        }
        return res
      }).catch(() => caches.match('/index.html'))
    })
  )
})

// Background Sync — traiter la file offline quand internet revient
self.addEventListener('sync', (e) => {
  if (e.tag === SYNC_TAG) {
    e.waitUntil(
      self.clients.matchAll().then(clients =>
        clients.forEach(c => c.postMessage({ type: 'SYNC_NOW' }))
      )
    )
  }
})
