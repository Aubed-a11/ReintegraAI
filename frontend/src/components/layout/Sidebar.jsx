import { motion } from 'framer-motion'
import { useLocation, useNavigate } from 'react-router-dom'
import { useApp } from '../../context/AppContext'
import { ProgressSteps } from '../ui'

const NAV_ITEMS = [
  { path: '/',          label: 'Accueil',       icon: HomeIcon,     roles: ['MIGRANT','AGENT','SUPERVISEUR','ADMIN'] },
  { path: '/profil',    label: 'Mon Profil',    icon: UserIcon,     roles: ['MIGRANT'] },
  { path: '/plan',      label: 'Mon Plan',      icon: DocIcon,      roles: ['MIGRANT'], badge: 'NEW' },
  { path: '/chat',      label: 'Assistant IA',  icon: ChatIcon,     roles: ['MIGRANT','AGENT','SUPERVISEUR','ADMIN'] },
  { path: '/dashboard', label: 'Dashboard OIM', icon: GridIcon,     roles: ['AGENT','SUPERVISEUR','ADMIN'] },
  { path: '/kiosk',     label: 'Kiosque Oral',  icon: KioskIcon,    roles: ['AGENT','SUPERVISEUR','ADMIN'] },
  { path: '/devices',   label: 'Bornes IoT',    icon: DeviceIcon,   roles: ['ADMIN','SUPERVISEUR'] },
  { path: '/admin',     label: 'Admin',         icon: AdminIcon,    roles: ['ADMIN','SUPERVISEUR'] },
]

export default function Sidebar() {
  const { user, profile, plan, planId, isAgent, logout } = useApp()
  const navigate  = useNavigate()
  const location  = useLocation()

  const role = user?.role || 'MIGRANT'

  // Calculer les étapes de progression
  const profCompletion = profile?.completion_pct || 0
  const planStatus     = plan?.statut || (planId ? 'PENDING' : null)

  const steps = [
    { label: 'Inscription',   sublabel: 'Compte créé',         status: 'done' },
    { label: 'Profil',        sublabel: `${profCompletion}% complété`, status: profCompletion >= 60 ? 'done' : 'active' },
    { label: 'Plan IA',       sublabel: planStatus === 'VALIDATED' ? 'Validé ✓' : planStatus ? 'En attente' : 'À générer', status: planStatus === 'VALIDATED' ? 'done' : planStatus ? 'active' : 'todo' },
    { label: 'Retour',        sublabel: 'À planifier',          status: 'todo' },
  ]

  const visibleNav = NAV_ITEMS.filter(item => item.roles.includes(role))

  return (
    <motion.aside
      initial={{ x: -288 }}
      animate={{ x: 0 }}
      transition={{ type: 'spring', stiffness: 280, damping: 30 }}
      style={{
        width: 'var(--sidebar-w)', background: 'var(--blue)',
        display: 'flex', flexDirection: 'column',
        position: 'sticky', top: 0, height: '100vh',
        overflow: 'hidden', flexShrink: 0,
        zIndex: 50,
      }}
    >
      {/* Logo */}
      <div style={{ padding: '28px 24px 22px', borderBottom: '1px solid rgba(255,255,255,.08)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 22 }}>
          <img
            src="/logo.svg"
            alt="HorizonAI"
            style={{ width: 38, height: 38, borderRadius: 10, objectFit: 'contain', flexShrink: 0 }}
          />
          <div>
            <div style={{ fontFamily: 'var(--font-display)', fontSize: 18, fontWeight: 700, color: '#fff', lineHeight: 1.1 }}>
              Horizon <span style={{ color: '#6aaee8' }}>AI</span>
            </div>
            <div style={{ fontSize: 9.5, color: 'rgba(255,255,255,.35)', letterSpacing: '.05em', marginTop: 1 }}>
              Intelligence · Réintégration
            </div>
          </div>
        </div>

        {/* User card */}
        <div style={{
          background: 'rgba(255,255,255,.07)',
          border: '1px solid rgba(255,255,255,.10)',
          borderRadius: 'var(--r)', padding: '11px 13px',
          display: 'flex', alignItems: 'center', gap: 10,
        }}>
          <div style={{
            width: 36, height: 36, borderRadius: '50%',
            background: 'linear-gradient(135deg, var(--ocre), #e8973f)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: 13, fontWeight: 700, color: '#fff', flexShrink: 0,
          }}>
            {role[0]}
          </div>
          <div style={{ fontSize: 12, minWidth: 0 }}>
            <div style={{ fontWeight: 600, color: '#fff', marginBottom: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
              {role === 'MIGRANT' ? 'Mon espace' : 'Agent OIM'}
            </div>
            <div style={{ color: 'rgba(255,255,255,.45)', fontSize: 11 }}>
              {role}
            </div>
          </div>
        </div>
      </div>

      {/* Nav */}
      <nav style={{ padding: '18px 16px', flex: 1, overflow: 'auto' }}>
        <div style={{ fontSize: 10, fontWeight: 600, letterSpacing: '.12em', textTransform: 'uppercase', color: 'rgba(255,255,255,.3)', padding: '0 10px', marginBottom: 6 }}>
          Navigation
        </div>

        {visibleNav.map(item => {
          const active = location.pathname === item.path
          return (
            <motion.button
              key={item.path}
              onClick={() => navigate(item.path)}
              whileHover={{ x: 2 }}
              style={{
                display: 'flex', alignItems: 'center', gap: 10,
                padding: '10px 12px', borderRadius: 9, width: '100%',
                border: 'none', cursor: 'pointer', marginBottom: 2,
                background: active ? 'rgba(255,255,255,.13)' : 'transparent',
                color: active ? '#fff' : 'rgba(255,255,255,.55)',
                fontSize: 13, fontWeight: active ? 600 : 400,
                textAlign: 'left',
                transition: 'all .15s',
              }}
            >
              <span style={{ width: 17, height: 17, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0, color: active ? 'var(--ocre)' : 'rgba(255,255,255,.3)', transition: 'var(--t)' }}>
                <item.icon />
              </span>
              <span style={{ flex: 1 }}>{item.label}</span>
              {item.badge && plan && (
                <span style={{ background: 'var(--ocre)', color: '#fff', fontSize: 10, fontWeight: 700, padding: '1px 6px', borderRadius: 20 }}>
                  {item.badge}
                </span>
              )}
            </motion.button>
          )
        })}

        {/* Logout */}
        <div style={{ marginTop: 16, paddingTop: 16, borderTop: '1px solid rgba(255,255,255,.07)' }}>
          <motion.button
            onClick={logout}
            whileHover={{ x: 2 }}
            style={{
              display: 'flex', alignItems: 'center', gap: 10,
              padding: '10px 12px', borderRadius: 9, width: '100%',
              border: 'none', cursor: 'pointer',
              background: 'transparent', color: 'rgba(255,120,120,.65)',
              fontSize: 13, textAlign: 'left', transition: 'all .15s',
            }}
          >
            <span style={{ width: 17, height: 17, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
              <LogoutIcon />
            </span>
            Déconnexion
          </motion.button>
        </div>
      </nav>

      {/* Progress (migrant only) */}
      {role === 'MIGRANT' && (
        <div style={{ padding: '16px 20px 24px', borderTop: '1px solid rgba(255,255,255,.08)' }}>
          <div style={{ fontSize: 10, fontWeight: 600, letterSpacing: '.08em', textTransform: 'uppercase', color: 'rgba(255,255,255,.35)', marginBottom: 12 }}>
            Votre parcours
          </div>
          <ProgressSteps steps={steps} />
        </div>
      )}
    </motion.aside>
  )
}

// ── Icônes SVG inline ─────────────────────────────────────────
const s = { fill: 'none', stroke: 'currentColor', strokeWidth: 2, width: 17, height: 17, viewBox: '0 0 24 24' }

function HomeIcon()   { return <svg {...s}><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> }
function UserIcon()   { return <svg {...s}><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> }
function DocIcon()    { return <svg {...s}><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg> }
function ChatIcon()   { return <svg {...s}><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> }
function GridIcon()   { return <svg {...s}><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg> }
function AdminIcon()  { return <svg {...s}><path d="M12 1l3 6 6 3-6 3-3 6-3-6-6-3 6-3z"/><circle cx="12" cy="12" r="3"/></svg> }
function KioskIcon()  { return <svg {...s}><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/><circle cx="12" cy="10" r="2"/><path d="M7 10h1M16 10h1"/></svg> }
function DeviceIcon() { return <svg {...s}><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18"/><circle cx="12" cy="18" r="1" fill="currentColor"/><path d="M9 7h6M9 11h4"/></svg> }
function LogoutIcon() { return <svg {...s}><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> }
