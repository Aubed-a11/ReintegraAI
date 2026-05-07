// ================================================================
// HorizonAI — API Service (connecté au backend PHP)
// Toutes les communications avec /api
// ================================================================

const API_BASE = import.meta.env.VITE_API_URL || '/api'

// ── Token store ───────────────────────────────────────────────
export const TokenStore = {
  get access()  { return localStorage.getItem('riai_token') },
  get refresh() { return localStorage.getItem('riai_refresh') },
  set access(v)  { v ? localStorage.setItem('riai_token', v)   : localStorage.removeItem('riai_token') },
  set refresh(v) { v ? localStorage.setItem('riai_refresh', v) : localStorage.removeItem('riai_refresh') },
  clear() { this.access = null; this.refresh = null },
}

// ── Fetch core avec refresh JWT automatique ───────────────────
let _refreshing = null

async function req(method, path, body = null, retry = true) {
  const headers = { 'Content-Type': 'application/json' }
  if (TokenStore.access) headers['Authorization'] = `Bearer ${TokenStore.access}`

  const opts = { method, headers }
  if (body) opts.body = JSON.stringify(body)

  let res
  try {
    res = await fetch(API_BASE + path, opts)
  } catch {
    throw new Error('Serveur inaccessible. Vérifiez votre connexion.')
  }

  // 401 → refresh automatique (une seule tentative)
  if (res.status === 401 && retry && TokenStore.refresh) {
    if (!_refreshing) {
      _refreshing = fetch(API_BASE + '/auth/refresh', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: TokenStore.refresh }),
      })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            TokenStore.access  = d.data.access_token
            TokenStore.refresh = d.data.refresh_token
            return true
          }
          TokenStore.clear()
          return false
        })
        .catch(() => { TokenStore.clear(); return false })
        .finally(() => { _refreshing = null })
    }
    const ok = await _refreshing
    if (ok) return req(method, path, body, false)
    return { ok: false, status: 401, data: { error: 'session_expired' } }
  }

  const data = await res.json().catch(() => ({}))
  return { ok: res.ok, status: res.status, data }
}

// ── Helpers ───────────────────────────────────────────────────
const get    = (path)         => req('GET',    path)
const post   = (path, body)   => req('POST',   path, body)
const put    = (path, body)   => req('PUT',    path, body)
const del    = (path)         => req('DELETE', path)
const patch  = (path, body)   => req('PATCH',  path, body)

// ── Auth ──────────────────────────────────────────────────────
export const authApi = {
  sendOtp:   (phone)              => post('/auth/send-otp',   { phone }),
  verifyOtp: (phone, otp, lang)   => post('/auth/verify-otp', { phone, otp, lang }),
  register:  (data, lang = 'fr')  => post('/auth/register',   { ...data, lang }),
  login:     (email, password, lang) => post('/auth/login', { email, password, lang }),
  refresh:   (refresh_token)      => post('/auth/refresh',    { refresh_token }),
  logout:    ()                   => del('/auth/logout'),
  me:        ()                   => get('/auth/me'),
}

// ── Profil ────────────────────────────────────────────────────
export const profileApi = {
  get:    ()       => get('/profile'),
  create: (data)   => post('/profile',  data),
  update: (data)   => put('/profile',   data),
  delete: ()       => del('/profile'),
}

// ── Plan IA ───────────────────────────────────────────────────
export const planApi = {
  generate:    (lang = 'fr')            => post(`/plan/generate?lang=${lang}`),
  getMine:     ()                       => get('/plan'),
  getById:     (id)                     => get(`/plan/${id}`),
  listPending: (params = {})            => get('/plans/pending?' + new URLSearchParams(params)),
  validate:    (id, body)               => put(`/plan/${id}/validate`, body),
  reject:      (id, reason, notes = '') => put(`/plan/${id}/reject`, { reason, notes_agent: notes }),
  exportPdf:   (id, lang = 'fr')        => get(`/plan/${id}/pdf?lang=${lang}`),
}

// ── Chat IA ───────────────────────────────────────────────────
export const chatApi = {
  send: (message, history = [], lang = 'fr') =>
    post('/chat', { message, history: history.slice(-10), lang }),
}

// ── Opportunités ──────────────────────────────────────────────
export const opportunityApi = {
  list:      (params = {}) => get('/opportunities?' + new URLSearchParams(params)),
  countries: ()            => get('/opportunities/countries'),
  create:    (data)        => post('/opportunities',   data),
  update:    (id, data)    => put(`/opportunities/${id}`, data),
  delete:    (id)          => del(`/opportunities/${id}`),
}

// ── Stats ─────────────────────────────────────────────────────
export const statsApi = {
  global: () => get('/stats/global'),
  agent:  () => get('/stats/agent'),
}

// ── Notifications ─────────────────────────────────────────────
export const notifApi = {
  list:    () => get('/notifications'),
  readAll: () => put('/notifications/read-all'),
  read:    (id) => put(`/notifications/${id}/read`),
}

// ── Follow-up ─────────────────────────────────────────────────
export const followUpApi = {
  create:  (data)   => post('/follow-up', data),
  getList: (planId) => get(`/follow-up/${planId}`),
}

// ── Admin ──────────────────────────────────────────────────
export const adminApi = {
  dashboard: () => get('/admin/dashboard'),
  migrants:  () => get('/admin/migrants'),
  plans:     () => get('/admin/plans'),
}

// ── Interview kiosque ──────────────────────────────────────────
const kioskReq = (method, path, body, device_token) => {
  const headers = { 'Content-Type': 'application/json', 'X-Device-Token': device_token }
  const opts = { method, headers }
  if (body) opts.body = JSON.stringify(body)
  return fetch(API_BASE + path, opts)
    .then(r => r.json().then(d => ({ ok: r.ok, data: d })))
    .catch(() => ({ ok: false, data: {} }))
}

export const interviewApi = {
  start:   (device_token, lang = 'fr')          => kioskReq('POST', '/interview/start',            { lang },       device_token),
  step:    (id, message, device_token)           => kioskReq('POST', `/interview/${id}/step`,       { message },    device_token),
  get:     (id, device_token)                    => kioskReq('GET',  `/interview/${id}`,            null,           device_token),
  abandon: (id, device_token)                    => kioskReq('POST', `/interview/${id}/abandon`,    null,           device_token),
  sync:    (sessions, device_token)              => kioskReq('POST', '/interview/sync',             { sessions },   device_token),
}
