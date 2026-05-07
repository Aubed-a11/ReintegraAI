import { createContext, useCallback, useContext, useEffect, useReducer } from 'react'
import toast from 'react-hot-toast'
import { authApi, planApi, profileApi, TokenStore } from '../services/api'

// ── État initial ──────────────────────────────────────────────
const initialState = {
  user:       null,
  profile:    null,
  plan:       null,
  planId:     null,
  loading:    { auth: true, profile: false, plan: false },
  notifCount: 0,
  lang:       localStorage.getItem('riai_lang') || 'fr',
}

// ── Reducer ───────────────────────────────────────────────────
function reducer(state, action) {
  switch (action.type) {
    case 'SET_USER':       return { ...state, user: action.payload }
    case 'SET_PROFILE':    return { ...state, profile: action.payload }
    case 'SET_PLAN':       return { ...state, plan: action.payload, planId: action.payload?.id || action.planId || state.planId }
    case 'SET_PLAN_ID':    return { ...state, planId: action.payload }
    case 'SET_LANG':       return { ...state, lang: action.payload }
    case 'SET_NOTIF':      return { ...state, notifCount: action.payload }
    case 'SET_LOADING':    return { ...state, loading: { ...state.loading, ...action.payload } }
    case 'LOGOUT':         return { ...initialState, loading: { auth: false, profile: false, plan: false } }
    default:               return state
  }
}

// ── Context ───────────────────────────────────────────────────
const AppContext = createContext(null)

export function AppProvider({ children }) {
  const [state, dispatch] = useReducer(reducer, initialState)

  // ── Init (charger user au démarrage) ───────────────────────
  useEffect(() => {
    if (!TokenStore.access) {
      dispatch({ type: 'SET_LOADING', payload: { auth: false } })
      return
    }
    loadMe()
  }, [])

  const loadMe = useCallback(async () => {
    dispatch({ type: 'SET_LOADING', payload: { auth: true } })
    const res = await authApi.me()
    if (res.ok) {
      dispatch({ type: 'SET_USER', payload: res.data.data.user })
      await loadProfile()
      await loadPlan()
    } else {
      TokenStore.clear()
    }
    dispatch({ type: 'SET_LOADING', payload: { auth: false } })
  }, [])

  const loadProfile = useCallback(async () => {
    const res = await profileApi.get()
    if (res.ok) dispatch({ type: 'SET_PROFILE', payload: res.data.data })
    return res
  }, [])

  const loadPlan = useCallback(async () => {
    const res = await planApi.getMine()
    if (res.ok) {
      dispatch({ type: 'SET_PLAN', payload: res.data.data })
    }
    return res
  }, [])

  // ── Actions ─────────────────────────────────────────────────
  const authenticate = useCallback(async (res, lang) => {
    if (res.ok) {
      TokenStore.access  = res.data.data.access_token
      TokenStore.refresh = res.data.data.refresh_token
      dispatch({ type: 'SET_USER', payload: res.data.data.user })
      dispatch({ type: 'SET_LANG', payload: lang })
      localStorage.setItem('riai_lang', lang)
      toast.success('Connexion réussie !')
      await loadProfile()
      await loadPlan()
    }
    return res
  }, [loadProfile, loadPlan])

  const login = useCallback(async (email, password, lang) => {
    const res = await authApi.login(email, password, lang)
    return authenticate(res, lang)
  }, [authenticate])

  const register = useCallback(async (data, lang) => {
    const res = await authApi.register(data, lang)
    return authenticate(res, lang)
  }, [authenticate])

  const logout = useCallback(async () => {
    await authApi.logout()
    TokenStore.clear()
    dispatch({ type: 'LOGOUT' })
    toast.success('Déconnecté')
  }, [])

  const saveProfile = useCallback(async (data) => {
    dispatch({ type: 'SET_LOADING', payload: { profile: true } })
    const exists = !!state.profile
    const res = exists ? await profileApi.update(data) : await profileApi.create(data)
    if (res.ok) {
      dispatch({ type: 'SET_PROFILE', payload: res.data.data })
      toast.success('Profil sauvegardé !')
    } else {
      toast.error(res.data?.message || 'Erreur de sauvegarde')
    }
    dispatch({ type: 'SET_LOADING', payload: { profile: false } })
    return res
  }, [state.profile])

  const generatePlan = useCallback(async () => {
    dispatch({ type: 'SET_LOADING', payload: { plan: true } })
    const res = await planApi.generate(state.lang)
    if (res.ok) {
      dispatch({ type: 'SET_PLAN', payload: res.data.data.plan })
      dispatch({ type: 'SET_PLAN_ID', payload: res.data.data.plan_id })
      toast.success('Plan généré ! Un agent OIM va le valider.')
    } else {
      toast.error(res.data?.message || 'Erreur génération plan')
    }
    dispatch({ type: 'SET_LOADING', payload: { plan: false } })
    return res
  }, [state.lang])

  const setLang = useCallback((lang) => {
    dispatch({ type: 'SET_LANG', payload: lang })
    localStorage.setItem('riai_lang', lang)
  }, [])

  const isAgent = state.user?.role && ['AGENT','SUPERVISEUR','ADMIN'].includes(state.user.role)
  const isAdmin = state.user?.role && ['ADMIN','SUPERVISEUR'].includes(state.user.role)
  const isLoggedIn = !!state.user

  return (
    <AppContext.Provider value={{
      ...state, isLoggedIn, isAgent, isAdmin,
      login, register, logout, loadMe, loadProfile, loadPlan,
      saveProfile, generatePlan, setLang,
      dispatch,
    }}>
      {children}
    </AppContext.Provider>
  )
}

export const useApp = () => {
  const ctx = useContext(AppContext)
  if (!ctx) throw new Error('useApp must be used within AppProvider')
  return ctx
}
