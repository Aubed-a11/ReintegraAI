import { useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { useApp } from '../../context/AppContext'
import { notifApi } from '../../services/api'
import toast from 'react-hot-toast'

const PAGE_TITLES = {
  '/':          'Tableau de bord',
  '/profil':    'Mon Profil',
  '/plan':      'Mon Plan de Réintégration',
  '/chat':      'Assistant IA',
  '/dashboard': 'Dashboard Agent OIM',
  '/kiosk':     'Kiosque Oral — Accueil Migrant',
  '/admin':     'Administration',
}

const LANGS = [
  { code: 'fr',  label: 'FR'  },
  { code: 'en',  label: 'EN'  },
  { code: 'ar',  label: 'AR'  },
  { code: 'wo',  label: 'WO'  },
  { code: 'bm',  label: 'BM'  },
  { code: 'ha',  label: 'HA'  },
  { code: 'ff',  label: 'FF'  },
  { code: 'tzm', label: 'TZM' },
]

export default function Topbar({ pathname }) {
  const { lang, setLang, notifCount, dispatch } = useApp()
  const [showNotifs, setShowNotifs] = useState(false)
  const [notifs, setNotifs]         = useState([])

  const title = PAGE_TITLES[pathname] || 'HorizonAI'

  const loadNotifs = async () => {
    const res = await notifApi.list()
    if (res.ok) {
      setNotifs(res.data.data.notifications || [])
      dispatch({ type: 'SET_NOTIF', payload: 0 })
      notifApi.readAll()
    }
    setShowNotifs(v => !v)
  }

  return (
    <header style={{
      height: 'var(--topbar-h)', background: 'rgba(246,241,233,.94)',
      backdropFilter: 'blur(12px)',
      borderBottom: '1px solid var(--border)',
      display: 'flex', alignItems: 'center',
      padding: '0 28px', gap: 14,
      position: 'sticky', top: 0, zIndex: 100,
    }}>
      {/* Titre */}
      <h1 style={{ fontFamily: 'var(--font-display)', fontSize: 16, fontWeight: 600, color: 'var(--blue)', flex: 1 }}>
        {title}
      </h1>

      {/* Lang switcher */}
      <div style={{ display: 'flex', gap: 3 }}>
        {LANGS.map(l => (
          <button key={l.code}
            onClick={() => setLang(l.code)}
            style={{
              padding: '4px 9px', borderRadius: 6,
              fontSize: 11, fontWeight: 600,
              border: `1px solid ${lang === l.code ? 'var(--blue)' : 'var(--border)'}`,
              background: lang === l.code ? 'var(--blue)' : 'white',
              color: lang === l.code ? 'white' : 'var(--mid)',
              cursor: 'pointer', transition: 'var(--t)',
            }}
          >
            {l.label}
          </button>
        ))}
      </div>

      {/* Notifications */}
      <div style={{ position: 'relative' }}>
        <motion.button
          whileHover={{ scale: 1.05 }}
          whileTap={{ scale: 0.95 }}
          onClick={loadNotifs}
          style={{
            width: 34, height: 34, borderRadius: 8,
            background: 'white', border: '1px solid var(--border)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            cursor: 'pointer', position: 'relative',
          }}
        >
          <svg width="15" height="15" fill="none" stroke="var(--mid)" strokeWidth="2" viewBox="0 0 24 24">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          {notifCount > 0 && (
            <motion.span
              initial={{ scale: 0 }} animate={{ scale: 1 }}
              style={{
                position: 'absolute', top: 5, right: 5,
                width: 8, height: 8, borderRadius: '50%',
                background: 'var(--ocre)', border: '2px solid var(--sand)',
              }}
            />
          )}
        </motion.button>

        {/* Dropdown notifs */}
        <AnimatePresence>
          {showNotifs && (
            <motion.div
              initial={{ opacity: 0, y: 8, scale: .96 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={{ opacity: 0, y: 8, scale: .96 }}
              style={{
                position: 'absolute', top: '110%', right: 0,
                width: 320, background: 'white',
                border: '1px solid var(--border)', borderRadius: 'var(--r)',
                boxShadow: 'var(--shadow-lg)', zIndex: 200,
                overflow: 'hidden',
              }}
            >
              <div style={{ padding: '12px 16px', borderBottom: '1px solid var(--border)', fontWeight: 600, fontSize: 13 }}>
                Notifications {notifs.length > 0 && `(${notifs.length})`}
              </div>
              {notifs.length === 0 ? (
                <div style={{ padding: '28px 16px', textAlign: 'center', color: 'var(--muted)', fontSize: 13 }}>
                  Aucune notification
                </div>
              ) : (
                notifs.slice(0, 5).map(n => (
                  <div key={n.id} style={{ padding: '11px 16px', borderBottom: '1px solid var(--border-light)', fontSize: 12 }}>
                    <div style={{ fontWeight: 600, marginBottom: 2 }}>{n.title}</div>
                    <div style={{ color: 'var(--muted)' }}>{n.body}</div>
                  </div>
                ))
              )}
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </header>
  )
}
