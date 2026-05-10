import { useCallback, useEffect, useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { devicesApi } from '../services/api'
import { Button, Card, Tag } from '../components/ui'
import toast from 'react-hot-toast'

export default function DevicesPage() {
  const [devices, setDevices]   = useState([])
  const [stats,   setStats]     = useState(null)
  const [loading, setLoading]   = useState(true)
  const [creating, setCreating] = useState(false)
  const [newForm,  setNewForm]  = useState({ nom: '', lieu: '' })
  const [showForm, setShowForm] = useState(false)
  const [selected, setSelected] = useState(null)
  const [detail,   setDetail]   = useState(null)
  const [copiedId, setCopied]   = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    const [devRes, statRes] = await Promise.all([devicesApi.list(), devicesApi.stats()])
    if (devRes.ok)  setDevices(devRes.data.data || [])
    if (statRes.ok) setStats(statRes.data.data)
    setLoading(false)
  }, [])

  useEffect(() => { load() }, [load])

  const selectDevice = async (d) => {
    setSelected(d)
    const res = await devicesApi.get(d.id)
    if (res.ok) setDetail(res.data.data)
  }

  const handleCreate = async () => {
    if (!newForm.nom.trim()) { toast.error('Nom de la borne requis'); return }
    setCreating(true)
    const res = await devicesApi.create(newForm)
    setCreating(false)
    if (res.ok) {
      toast.success('Borne créée — conservez le token en lieu sûr')
      setShowForm(false)
      setNewForm({ nom: '', lieu: '' })
      load()
      setSelected(res.data.data)
      setDetail({ device: res.data.data, sessions: [] })
    } else {
      toast.error(res.data?.message || 'Erreur création')
    }
  }

  const handleToggle = async (device) => {
    const res = await devicesApi.update(device.id, { is_active: !device.is_active })
    if (res.ok) { toast.success(device.is_active ? 'Borne désactivée' : 'Borne réactivée'); load() }
  }

  const handleRotate = async (id) => {
    if (!window.confirm('Régénérer le token ? L\'ancienne configuration de la borne sera invalide.')) return
    const res = await devicesApi.rotateToken(id)
    if (res.ok) {
      toast.success('Nouveau token généré')
      load()
      if (selected?.id === id) selectDevice({ ...selected })
    }
  }

  const copyToken = (token, id) => {
    navigator.clipboard.writeText(token)
    setCopied(id)
    toast.success('Token copié')
    setTimeout(() => setCopied(null), 2000)
  }

  const copyUrl = (token) => {
    const url = `${window.location.origin}/kiosk?token=${token}`
    navigator.clipboard.writeText(url)
    toast.success('URL copiée')
  }

  const onlineCount  = devices.filter(d => d.is_online).length
  const offlineCount = devices.filter(d => d.is_active && !d.is_online).length

  return (
    <div style={{ padding: 28 }}>
      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', marginBottom: 24 }}>
        <div>
          <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 26, fontWeight: 700, color: 'var(--blue)' }}>
            Bornes Kiosque
          </h2>
          <p style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>
            Gestion des objets connectés HorizonAI déployés dans les bureaux OIM
          </p>
        </div>
        <Button variant="primary" onClick={() => setShowForm(v => !v)}>
          + Enregistrer une borne
        </Button>
      </div>

      {/* Stats globales */}
      {stats && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 14, marginBottom: 24 }}>
          {[
            { label: 'Bornes total',    value: stats.global?.bornes_total    ?? 0, color: 'var(--blue)' },
            { label: 'En ligne',        value: onlineCount,                        color: 'var(--green)' },
            { label: 'Hors ligne',      value: offlineCount,                       color: 'var(--muted)' },
            { label: 'Entretiens',      value: stats.global?.sessions_total  ?? 0, color: 'var(--blue-mid)' },
            { label: 'Sync en attente', value: stats.global?.sessions_pending_sync ?? 0, color: 'var(--ocre)' },
          ].map((k, i) => (
            <Card key={i} style={{ padding: '16px 18px' }} hover={false}>
              <div style={{ fontFamily: 'var(--font-display)', fontSize: 28, fontWeight: 700, color: k.color, lineHeight: 1, marginBottom: 4 }}>
                {k.value}
              </div>
              <div style={{ fontSize: 11, color: 'var(--muted)', fontWeight: 500 }}>{k.label}</div>
            </Card>
          ))}
        </div>
      )}

      {/* Formulaire création */}
      <AnimatePresence>
        {showForm && (
          <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} exit={{ opacity: 0, height: 0 }}
            style={{ overflow: 'hidden', marginBottom: 20 }}>
            <Card style={{ padding: 24, border: '2px solid var(--blue-mid)' }} hover={false}>
              <div style={{ fontSize: 13, fontWeight: 700, color: 'var(--blue)', marginBottom: 16 }}>
                Nouvelle borne kiosque
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
                <div>
                  <label style={labelStyle}>Nom de la borne *</label>
                  <input style={inputStyle} placeholder="Ex: Kiosque Accueil Rabat"
                    value={newForm.nom} onChange={e => setNewForm(f => ({ ...f, nom: e.target.value }))} />
                </div>
                <div>
                  <label style={labelStyle}>Lieu / Bureau</label>
                  <input style={inputStyle} placeholder="Ex: OIM Rabat — Salle d'accueil"
                    value={newForm.lieu} onChange={e => setNewForm(f => ({ ...f, lieu: e.target.value }))} />
                </div>
              </div>
              <div style={{ background: 'var(--ocre-light)', border: '1px solid var(--ocre)', borderRadius: 8, padding: '10px 14px', fontSize: 12, color: 'var(--ocre-dark)', marginBottom: 16 }}>
                ⚠️ Un token unique sera généré. Copiez-le immédiatement — il ne sera plus affiché en clair ensuite.
              </div>
              <div style={{ display: 'flex', gap: 10 }}>
                <Button variant="primary" loading={creating} onClick={handleCreate}>Créer la borne</Button>
                <Button variant="ghost" onClick={() => setShowForm(false)}>Annuler</Button>
              </div>
            </Card>
          </motion.div>
        )}
      </AnimatePresence>

      <div style={{ display: 'grid', gridTemplateColumns: selected ? '1fr 380px' : '1fr', gap: 20 }}>
        {/* Liste des bornes */}
        <Card style={{ overflow: 'hidden' }} hover={false}>
          <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--border)', fontSize: 13, fontWeight: 600, color: 'var(--dark)' }}>
            {devices.length} borne{devices.length !== 1 ? 's' : ''} enregistrée{devices.length !== 1 ? 's' : ''}
          </div>

          {loading ? (
            <div style={{ padding: 40, textAlign: 'center', color: 'var(--muted)', fontSize: 13 }}>Chargement...</div>
          ) : devices.length === 0 ? (
            <div style={{ padding: 40, textAlign: 'center' }}>
              <div style={{ fontSize: 36, marginBottom: 12 }}>📡</div>
              <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--dark)', marginBottom: 6 }}>Aucune borne enregistrée</div>
              <div style={{ fontSize: 12, color: 'var(--muted)' }}>Cliquez sur "Enregistrer une borne" pour commencer.</div>
            </div>
          ) : (
            <div>
              {devices.map(d => (
                <motion.div key={d.id} whileHover={{ background: 'var(--sand)' }}
                  onClick={() => selectDevice(d)}
                  style={{
                    padding: '14px 18px', borderBottom: '1px solid var(--border-light)',
                    cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 14,
                    background: selected?.id === d.id ? 'var(--blue-light)' : 'white',
                  }}>
                  {/* Indicateur online */}
                  <div style={{
                    width: 10, height: 10, borderRadius: '50%', flexShrink: 0,
                    background: d.is_online ? 'var(--green)' : d.is_active ? 'var(--muted)' : 'var(--red)',
                    boxShadow: d.is_online ? '0 0 6px var(--green)' : 'none',
                  }} />
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 600, fontSize: 13, color: 'var(--dark)', marginBottom: 2 }}>
                      {d.nom}
                    </div>
                    <div style={{ fontSize: 11, color: 'var(--muted)', display: 'flex', gap: 12 }}>
                      {d.lieu && <span>📍 {d.lieu}</span>}
                      <span>🎙 {d.sessions_total ?? 0} entretiens</span>
                      {d.last_ping && (
                        <span title={d.last_ping}>
                          ⏱ {d.is_online ? 'En ligne' : `Vu il y a ${timeSince(d.last_ping)}`}
                        </span>
                      )}
                    </div>
                  </div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    {d.sessions_active > 0 && (
                      <Tag variant="green">Entretien en cours</Tag>
                    )}
                    <Tag variant={d.is_online ? 'green' : d.is_active ? 'gray' : 'red'}>
                      {d.is_online ? 'En ligne' : d.is_active ? 'Hors ligne' : 'Désactivée'}
                    </Tag>
                  </div>
                </motion.div>
              ))}
            </div>
          )}
        </Card>

        {/* Panneau détail */}
        <AnimatePresence>
          {selected && (
            <motion.div initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: 20 }}>
              <Card style={{ padding: 22, position: 'sticky', top: 80 }} hover={false}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 18 }}>
                  <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--blue)' }}>{selected.nom}</div>
                  <button onClick={() => { setSelected(null); setDetail(null) }}
                    style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--muted)', fontSize: 18 }}>✕</button>
                </div>

                {/* Statut */}
                <div style={{ display: 'flex', gap: 8, marginBottom: 18, flexWrap: 'wrap' }}>
                  <Tag variant={detail?.device?.is_online ? 'green' : 'gray'}>
                    {detail?.device?.is_online ? '🟢 En ligne' : '⚫ Hors ligne'}
                  </Tag>
                  <Tag variant={selected.is_active ? 'blue' : 'red'}>
                    {selected.is_active ? 'Active' : 'Désactivée'}
                  </Tag>
                </div>

                {/* Infos */}
                <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 16 }}>
                  {selected.lieu && <div style={{ marginBottom: 4 }}>📍 {selected.lieu}</div>}
                  <div style={{ marginBottom: 4 }}>🎙 {detail?.device?.sessions_total ?? '—'} entretiens au total</div>
                  <div style={{ marginBottom: 4 }}>✅ {detail?.device?.sessions_completed ?? '—'} complétés</div>
                  {selected.last_ping && <div>⏱ Dernier ping : {new Date(selected.last_ping).toLocaleString('fr-FR')}</div>}
                  {detail?.device?.last_session_at && <div>📋 Dernier entretien : {new Date(detail.device.last_session_at).toLocaleString('fr-FR')}</div>}
                </div>

                {/* Token (masqué sauf création) */}
                <div style={{ background: 'var(--sand)', borderRadius: 8, padding: '10px 14px', marginBottom: 16 }}>
                  <div style={{ fontSize: 10, fontWeight: 700, color: 'var(--muted)', textTransform: 'uppercase', letterSpacing: '.06em', marginBottom: 6 }}>
                    Token de la borne
                  </div>
                  <div style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--dark)', wordBreak: 'break-all', marginBottom: 8 }}>
                    {detail?.device?.device_token || selected.device_token || '••••••••••••••••••••••••••'}
                  </div>
                  <div style={{ display: 'flex', gap: 6 }}>
                    {(detail?.device?.device_token || selected.device_token) && (
                      <button onClick={() => copyToken(detail?.device?.device_token || selected.device_token, selected.id)}
                        style={btnSmStyle}>
                        {copiedId === selected.id ? '✓ Copié' : '📋 Copier token'}
                      </button>
                    )}
                    <button onClick={() => copyUrl(detail?.device?.device_token || selected.device_token)}
                      style={btnSmStyle}>
                      🔗 Copier URL
                    </button>
                  </div>
                </div>

                {/* URL de configuration */}
                <div style={{ background: 'var(--blue-light)', borderRadius: 8, padding: '10px 14px', marginBottom: 16 }}>
                  <div style={{ fontSize: 10, fontWeight: 700, color: 'var(--blue)', textTransform: 'uppercase', letterSpacing: '.06em', marginBottom: 4 }}>
                    URL à configurer sur la borne
                  </div>
                  <code style={{ fontSize: 10, color: 'var(--blue)', wordBreak: 'break-all' }}>
                    {window.location.origin}/kiosk?token={detail?.device?.device_token || selected.device_token || '<TOKEN>'}
                  </code>
                </div>

                {/* Actions */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                  <button onClick={() => handleToggle(detail?.device || selected)}
                    style={{ ...btnSmStyle, width: '100%', padding: '9px', textAlign: 'center' }}>
                    {(detail?.device || selected).is_active ? '⏸ Désactiver la borne' : '▶ Réactiver la borne'}
                  </button>
                  <button onClick={() => handleRotate(selected.id)}
                    style={{ ...btnSmStyle, width: '100%', padding: '9px', textAlign: 'center', color: 'var(--ocre)' }}>
                    🔄 Régénérer le token
                  </button>
                </div>

                {/* Dernières sessions */}
                {detail?.sessions?.length > 0 && (
                  <div style={{ marginTop: 20 }}>
                    <div style={{ fontSize: 11, fontWeight: 700, color: 'var(--mid)', textTransform: 'uppercase', letterSpacing: '.06em', marginBottom: 10 }}>
                      Derniers entretiens
                    </div>
                    {detail.sessions.slice(0, 8).map(s => (
                      <div key={s.id} style={{
                        padding: '8px 0', borderBottom: '1px solid var(--border-light)',
                        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                        fontSize: 11,
                      }}>
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                          <span>{LANG_FLAGS[s.lang] || '🌍'}</span>
                          <span style={{ color: 'var(--mid)' }}>{new Date(s.created_at).toLocaleDateString('fr-FR')}</span>
                          {s.rdv_date && <span style={{ color: 'var(--green)' }}>RDV {s.rdv_date}</span>}
                        </div>
                        <Tag variant={s.statut === 'COMPLETED' ? 'green' : s.statut === 'ABANDONED' ? 'red' : 'gray'}>
                          {s.statut === 'COMPLETED' ? 'Terminé' : s.statut === 'ABANDONED' ? 'Abandonné' : 'En cours'}
                        </Tag>
                      </div>
                    ))}
                  </div>
                )}
              </Card>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </div>
  )
}

const LANG_FLAGS = { fr:'🇫🇷', en:'🇬🇧', ar:'🇲🇦', wo:'🇸🇳', bm:'🇲🇱', ha:'🇳🇪', ff:'🌍', tzm:'🇩🇿' }

function timeSince(dateStr) {
  const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000)
  if (diff < 60)   return `${diff}s`
  if (diff < 3600) return `${Math.floor(diff/60)}min`
  if (diff < 86400) return `${Math.floor(diff/3600)}h`
  return `${Math.floor(diff/86400)}j`
}

const labelStyle = { fontSize: 11, fontWeight: 600, color: 'var(--mid)', textTransform: 'uppercase', letterSpacing: '.06em', display: 'block', marginBottom: 5 }
const inputStyle = { padding: '10px 12px', border: '1.5px solid var(--border)', borderRadius: 8, fontSize: 13, color: 'var(--dark)', background: 'var(--sand)', outline: 'none', width: '100%', fontFamily: 'var(--font-body)' }
const btnSmStyle = { padding: '6px 12px', borderRadius: 6, fontSize: 11, fontWeight: 600, cursor: 'pointer', border: '1px solid var(--border)', background: 'white', color: 'var(--mid)' }
