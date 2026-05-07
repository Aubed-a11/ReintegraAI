// ================================================================
// offlineStore — IndexedDB pour sessions kiosque offline
// DB: horizonai_offline  |  version: 1
// Stores: interview_sessions, sync_queue
// ================================================================

const DB_NAME    = 'horizonai_offline'
const DB_VERSION = 1

function openDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION)
    req.onupgradeneeded = (e) => {
      const db = e.target.result
      if (!db.objectStoreNames.contains('interview_sessions')) {
        const store = db.createObjectStore('interview_sessions', { keyPath: 'id' })
        store.createIndex('synced', 'synced', { unique: false })
      }
      if (!db.objectStoreNames.contains('sync_queue')) {
        db.createObjectStore('sync_queue', { keyPath: 'id', autoIncrement: true })
      }
    }
    req.onsuccess = () => resolve(req.result)
    req.onerror   = () => reject(req.error)
  })
}

function tx(db, storeName, mode, fn) {
  return new Promise((resolve, reject) => {
    const t     = db.transaction(storeName, mode)
    const store = t.objectStore(storeName)
    const req   = fn(store)
    if (req) {
      req.onsuccess = () => resolve(req.result)
      req.onerror   = () => reject(req.error)
    } else {
      t.oncomplete = () => resolve()
      t.onerror    = () => reject(t.error)
    }
  })
}

export const offlineStore = {
  async saveSession(session) {
    const db = await openDB()
    await tx(db, 'interview_sessions', 'readwrite', s => s.put({ ...session, synced: false }))
  },

  async getSession(id) {
    const db = await openDB()
    return tx(db, 'interview_sessions', 'readonly', s => s.get(id))
  },

  async getPendingSessions() {
    const db = await openDB()
    return new Promise((resolve, reject) => {
      const t      = db.transaction('interview_sessions', 'readonly')
      const store  = t.objectStore('interview_sessions')
      const idx    = store.index('synced')
      const req    = idx.getAll(IDBKeyRange.only(false))
      req.onsuccess = () => resolve(req.result)
      req.onerror   = () => reject(req.error)
    })
  },

  async markSynced(id) {
    const db      = await openDB()
    const session = await this.getSession(id)
    if (session) await tx(db, 'interview_sessions', 'readwrite', s => s.put({ ...session, synced: true }))
  },

  async queueRequest(endpoint, method, body) {
    const db = await openDB()
    await tx(db, 'sync_queue', 'readwrite', s => s.add({
      endpoint, method, body: JSON.stringify(body), created_at: new Date().toISOString(),
    }))
  },

  async getAllQueued() {
    const db = await openDB()
    return new Promise((resolve, reject) => {
      const t   = db.transaction('sync_queue', 'readonly')
      const req = t.objectStore('sync_queue').getAll()
      req.onsuccess = () => resolve(req.result)
      req.onerror   = () => reject(req.error)
    })
  },

  async clearQueued(id) {
    const db = await openDB()
    await tx(db, 'sync_queue', 'readwrite', s => s.delete(id))
  },

  async processSyncQueue(apiBase, deviceToken) {
    const items = await this.getAllQueued()
    let synced = 0, failed = 0

    for (const item of items) {
      try {
        const res = await fetch(apiBase + item.endpoint, {
          method: item.method,
          headers: { 'Content-Type': 'application/json', 'X-Device-Token': deviceToken },
          body: item.body,
        })
        if (res.ok) {
          await this.clearQueued(item.id)
          synced++
        } else {
          failed++
        }
      } catch {
        failed++
      }
    }

    // Sync sessions en attente (batch)
    const pending = await this.getPendingSessions()
    if (pending.length > 0) {
      try {
        const res = await fetch(apiBase + '/interview/sync', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Device-Token': deviceToken },
          body: JSON.stringify({ sessions: pending }),
        })
        if (res.ok) {
          for (const s of pending) await this.markSynced(s.id)
          synced += pending.length
        }
      } catch { failed++ }
    }

    return { synced, failed }
  },
}
