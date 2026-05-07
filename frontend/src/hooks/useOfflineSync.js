import { useCallback, useEffect, useRef, useState } from 'react'
import { offlineStore } from '../services/offlineStore'

const API_BASE     = import.meta.env.VITE_API_URL || '/api'
const DEVICE_TOKEN = import.meta.env.VITE_KIOSK_TOKEN || 'DEMO_KIOSK_TOKEN'

export function useOfflineSync() {
  const [isOnline, setIsOnline]       = useState(navigator.onLine)
  const [pendingCount, setPending]    = useState(0)
  const [isSyncing, setIsSyncing]     = useState(false)
  const [lastSyncAt, setLastSyncAt]   = useState(null)
  const syncingRef                    = useRef(false)

  const refreshPending = useCallback(async () => {
    try {
      const items = await offlineStore.getAllQueued()
      const sessions = await offlineStore.getPendingSessions()
      setPending(items.length + sessions.length)
    } catch { setPending(0) }
  }, [])

  const syncNow = useCallback(async () => {
    if (syncingRef.current || !navigator.onLine) return
    syncingRef.current = true
    setIsSyncing(true)
    try {
      const result = await offlineStore.processSyncQueue(API_BASE, DEVICE_TOKEN)
      setLastSyncAt(new Date())
      await refreshPending()
      return result
    } finally {
      syncingRef.current = false
      setIsSyncing(false)
    }
  }, [refreshPending])

  // Écouter les changements de connexion
  useEffect(() => {
    const onOnline  = () => { setIsOnline(true);  syncNow() }
    const onOffline = () => { setIsOnline(false) }
    window.addEventListener('online',  onOnline)
    window.addEventListener('offline', onOffline)
    refreshPending()
    return () => {
      window.removeEventListener('online',  onOnline)
      window.removeEventListener('offline', onOffline)
    }
  }, [syncNow, refreshPending])

  return { isOnline, pendingCount, isSyncing, lastSyncAt, syncNow }
}
