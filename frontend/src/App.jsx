import { Toaster } from 'react-hot-toast'
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom'
import Sidebar from './components/layout/Sidebar'
import Topbar from './components/layout/Topbar'
import { Spinner } from './components/ui'
import { AppProvider, useApp } from './context/AppContext'
import AdminDashboard from './pages/AdminDashboard'
import DevicesPage from './pages/DevicesPage'
import KioskPage from './pages/KioskPage'
import ChatPage from './pages/ChatPage'
import DashboardPage from './pages/DashboardPage'
import HomePage from './pages/HomePage'
import LoginPage from './pages/LoginPage'
import PlanPage from './pages/PlanPage'
import ProfilePage from './pages/ProfilePage'

// ── Guard routes protégées ────────────────────────────────────
function RequireAuth({ children }) {
  const { isLoggedIn, loading } = useApp()
  if (loading.auth) {
    return (
      <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'var(--sand)' }}>
        <div style={{ textAlign: 'center' }}>
          <Spinner size={32} color="var(--blue)" />
          <p style={{ marginTop: 16, fontSize: 14, color: 'var(--muted)' }}>Chargement...</p>
        </div>
      </div>
    )
  }
  if (!isLoggedIn) return <Navigate to="/login" replace />
  return children
}

// ── Guard agents OIM ──────────────────────────────────────────
function RequireAgent({ children }) {
  const { isAgent } = useApp()
  if (!isAgent) return <Navigate to="/" replace />
  return children
}

// ── Guard admin / superviseur ─────────────────────────────────
function RequireAdmin({ children }) {
  const { isAdmin } = useApp()
  if (!isAdmin) return <Navigate to="/" replace />
  return children
}

// ── Layout principal (sidebar + topbar) ───────────────────────
function AppLayout() {
  const location = useLocation()
  return (
    <div style={{ display: 'flex', minHeight: '100vh' }}>
      <Sidebar />
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
        <Topbar pathname={location.pathname} />
        <main style={{ flex: 1 }}>
          <Routes>
            <Route path="/"          element={<HomePage />} />
            <Route path="/profil"    element={<ProfilePage />} />
            <Route path="/plan"      element={<PlanPage />} />
            <Route path="/chat"      element={<ChatPage />} />
            <Route path="/dashboard" element={<RequireAgent><DashboardPage /></RequireAgent>} />
            <Route path="/devices"   element={<RequireAdmin><DevicesPage /></RequireAdmin>} />
            <Route path="/admin"     element={<RequireAdmin><AdminDashboard /></RequireAdmin>} />
            <Route path="*"          element={<Navigate to="/" replace />} />
          </Routes>
        </main>
      </div>
    </div>
  )
}

// ── Root App ──────────────────────────────────────────────────
export default function App() {
  return (
    <BrowserRouter>
      <AppProvider>
        <Routes>
          <Route path="/login"  element={<PublicRoute><LoginPage /></PublicRoute>} />
          <Route path="/kiosk"  element={<KioskPage />} />
          <Route path="/*"      element={<RequireAuth><AppLayout /></RequireAuth>} />
        </Routes>

        <Toaster
          position="bottom-right"
          toastOptions={{
            style: {
              fontFamily: 'var(--font-body)',
              fontSize: 13,
              borderRadius: 10,
              boxShadow: '0 6px 24px rgba(0,0,0,.15)',
            },
            success: { style: { background: 'var(--green)', color: '#fff' } },
            error:   { style: { background: '#e53935', color: '#fff' } },
          }}
        />
      </AppProvider>
    </BrowserRouter>
  )
}

// Rediriger si déjà connecté
function PublicRoute({ children }) {
  const { isLoggedIn, loading } = useApp()
  if (loading.auth) return null
  if (isLoggedIn)   return <Navigate to="/" replace />
  return children
}
