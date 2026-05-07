import { useState, useEffect, useCallback } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { planApi, statsApi } from '../services/api'
import { useApp } from '../context/AppContext'
import { Button, Card, StatusPill, Tag, ScoreRing, Skeleton, EmptyState } from '../components/ui'
import toast from 'react-hot-toast'

export default function DashboardPage() {
  const { user } = useApp()
  const [plans,    setPlans]    = useState([])
  const [stats,    setStats]    = useState(null)
  const [selected, setSelected] = useState(null)  // plan sélectionné
  const [detail,   setDetail]   = useState(null)  // détail complet
  const [loading,  setLoading]  = useState(true)
  const [notes,    setNotes]    = useState('')
  const [filter,   setFilter]   = useState('PENDING')
  const [search,   setSearch]   = useState('')
  const [validating, setValidating] = useState(false)

  const loadData = useCallback(async () => {
    setLoading(true)
    const [plansRes, statsRes] = await Promise.all([
      planApi.listPending({ statut: filter, limit: 30 }),
      statsApi.agent(),
    ])
    if (plansRes.ok) setPlans(plansRes.data.data.plans || [])
    if (statsRes.ok) setStats(statsRes.data.data)
    setLoading(false)
  }, [filter])

  useEffect(() => { loadData() }, [loadData])

  const selectPlan = async (plan) => {
    setSelected(plan)
    setNotes('')
    setDetail(null)
    const res = await planApi.getById(plan.id)
    if (res.ok) setDetail(res.data.data)
  }

  const handleValidate = async () => {
    if (!selected) return
    setValidating(true)
    const res = await planApi.validate(selected.id, { notes_agent: notes })
    setValidating(false)
    if (res.ok) {
      toast.success('Plan validé ! Le migrant a été notifié.')
      setSelected(null); setDetail(null)
      loadData()
    } else {
      toast.error(res.data?.message || 'Erreur validation')
    }
  }

  const handleReject = async () => {
    if (!selected) return
    const reason = window.prompt('Motif de refus (obligatoire):')
    if (!reason) return
    const res = await planApi.reject(selected.id, reason, notes)
    if (res.ok) {
      toast.success('Plan refusé.')
      setSelected(null); setDetail(null)
      loadData()
    } else {
      toast.error('Erreur refus')
    }
  }

  const filtered = plans.filter(p =>
    !search ||
    (p.pays_origine || '').toLowerCase().includes(search.toLowerCase()) ||
    (p.tranche_age || '').toLowerCase().includes(search.toLowerCase()) ||
    p.id.toLowerCase().includes(search.toLowerCase())
  )

  const scoreColor = s => s >= 75 ? 'var(--green)' : s >= 50 ? 'var(--ocre)' : 'var(--red)'

  const getAxesText = d => {
    if (!d) return '—'
    const axes = d.axes || {
      emploi:   { items: d.axe_emploi   || [] },
      logement: { items: d.axe_logement  || [] },
      finance:  { items: d.axe_finance   || [] },
      sante:    { items: d.axe_sante     || [] },
    }
    return Object.entries(axes)
      .map(([k, a]) => `${k.toUpperCase()}: ${(a.items||[]).slice(0,1).map(i=>i.titre).join(', ') || '—'}`)
      .join(' · ')
  }

  return (
    <div style={{ padding: 28 }}>
      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', marginBottom: 22 }}>
        <div>
          <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 26, fontWeight: 700, color: 'var(--blue)' }}>
            Dashboard Agent OIM
          </h2>
          <p style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>
            {new Date().toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
          </p>
        </div>
        <Button variant="blue" size="sm" onClick={loadData}>
          ↻ Actualiser
        </Button>
      </div>

      {/* KPIs */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16, marginBottom: 22 }}>
        {[
          { label: 'Plans générés', value: stats?.total || plans.length, color: 'var(--blue)' },
          { label: 'À valider',     value: plans.filter(p=>p.statut==='PENDING').length, color: 'var(--ocre)' },
          { label: 'Validés',       value: stats?.validated || 0, color: 'var(--green)' },
          { label: 'Score moyen',   value: stats?.avg_score ? `${stats.avg_score}/100` : '—', color: 'var(--blue-mid)' },
        ].map((k, i) => (
          <motion.div key={i} initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * .06 }}>
            <Card style={{ padding: '18px 20px' }} hover={false}>
              <div style={{ fontFamily: 'var(--font-display)', fontSize: 30, fontWeight: 700, color: k.color, lineHeight: 1, marginBottom: 4 }}>
                {loading ? <Skeleton height={30} width={60}/> : k.value}
              </div>
              <div style={{ fontSize: 11, color: 'var(--muted)', fontWeight: 500 }}>{k.label}</div>
            </Card>
          </motion.div>
        ))}
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: selected ? '1fr 380px' : '1fr', gap: 20 }}>
        {/* Table plans */}
        <div>
          <Card style={{ overflow: 'hidden' }} hover={false}>
            {/* Toolbar */}
            <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--border)', display: 'flex', gap: 10, alignItems: 'center' }}>
              <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--dark)', flex: 1 }}>
                Plans {filter === 'ALL' ? 'tous' : `— ${filter}`}
              </div>

              {/* Filtre statut */}
              <div style={{ display: 'flex', gap: 4 }}>
                {['PENDING','VALIDATED','ALL'].map(s => (
                  <button key={s} onClick={() => setFilter(s)} style={{
                    padding: '4px 10px', borderRadius: 6, fontSize: 11, fontWeight: 600,
                    border: `1px solid ${filter === s ? 'var(--blue)' : 'var(--border)'}`,
                    background: filter === s ? 'var(--blue)' : 'white',
                    color: filter === s ? 'white' : 'var(--mid)',
                    cursor: 'pointer',
                  }}>
                    {s === 'ALL' ? 'Tous' : s}
                  </button>
                ))}
              </div>

              <input value={search} onChange={e => setSearch(e.target.value)}
                placeholder="Rechercher..."
                style={{
                  padding: '6px 12px', borderRadius: 7, border: '1.5px solid var(--border)',
                  fontSize: 12, background: 'var(--sand)', outline: 'none', width: 180,
                  fontFamily: 'var(--font-body)',
                }} />

              <Tag variant={filter === 'PENDING' ? 'ocre' : 'gray'}>{filtered.length} plan{filtered.length > 1 ? 's' : ''}</Tag>
            </div>

            {/* Tableau */}
            {loading ? (
              <div style={{ padding: 20, display: 'flex', flexDirection: 'column', gap: 12 }}>
                {[...Array(5)].map((_, i) => <Skeleton key={i} height={44} />)}
              </div>
            ) : filtered.length === 0 ? (
              <EmptyState icon="📋" title="Aucun plan" subtitle="Aucun plan correspondant aux critères." />
            ) : (
              <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                  <thead>
                    <tr style={{ background: 'var(--sand)' }}>
                      {['ID', 'Profil', 'Pays', 'Score', 'Date', 'Statut', 'Action'].map(h => (
                        <th key={h} style={{ padding: '9px 14px', textAlign: 'left', fontSize: 10, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '.07em', color: 'var(--muted)', borderBottom: '1px solid var(--border)' }}>
                          {h}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {filtered.map(plan => (
                      <motion.tr key={plan.id}
                        initial={{ opacity: 0 }} animate={{ opacity: 1 }}
                        style={{
                          background: selected?.id === plan.id ? 'var(--blue-light)' : 'white',
                          cursor: 'pointer', transition: 'background .15s',
                        }}
                        onClick={() => selectPlan(plan)}
                      >
                        <td style={{ padding: '11px 14px', borderBottom: '1px solid var(--border-light)', fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--blue-mid)' }}>
                          #{plan.id.slice(0, 8)}
                        </td>
                        <td style={{ padding: '11px 14px', borderBottom: '1px solid var(--border-light)', fontSize: 12 }}>
                          {plan.tranche_age} · {plan.pays_origine}
                        </td>
                        <td style={{ padding: '11px 14px', borderBottom: '1px solid var(--border-light)', fontSize: 12, color: 'var(--mid)' }}>
                          {plan.ville_retour || plan.pays_origine}
                        </td>
                        <td style={{ padding: '11px 14px', borderBottom: '1px solid var(--border-light)' }}>
                          <strong style={{ color: scoreColor(plan.score_ia || 0), fontSize: 13 }}>
                            {plan.score_ia || 0}/100
                          </strong>
                        </td>
                        <td style={{ padding: '11px 14px', borderBottom: '1px solid var(--border-light)', fontSize: 11, color: 'var(--muted)' }}>
                          {plan.created_at ? new Date(plan.created_at).toLocaleDateString('fr-FR') : '—'}
                        </td>
                        <td style={{ padding: '11px 14px', borderBottom: '1px solid var(--border-light)' }}>
                          <StatusPill status={plan.statut} />
                        </td>
                        <td style={{ padding: '11px 14px', borderBottom: '1px solid var(--border-light)' }}>
                          <Button variant={selected?.id === plan.id ? 'blue' : 'outline'} size="sm"
                            onClick={e => { e.stopPropagation(); selectPlan(plan) }}>
                            {selected?.id === plan.id ? '← Ouvert' : 'Réviser →'}
                          </Button>
                        </td>
                      </motion.tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Card>
        </div>

        {/* Panneau détail */}
        <AnimatePresence>
          {selected && (
            <motion.div
              initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: 20 }}
            >
              <Card style={{ padding: 22, position: 'sticky', top: 'var(--topbar-h)' }} hover={false}>
                {/* Header panneau */}
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 16, paddingBottom: 14, borderBottom: '1px solid var(--border)' }}>
                  <div>
                    <div style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--muted)', marginBottom: 4 }}>
                      #{selected.id.slice(0, 8)}
                    </div>
                    <div style={{ fontSize: 15, fontWeight: 600, color: 'var(--blue)', marginBottom: 6 }}>
                      {selected.tranche_age} · {selected.pays_origine}
                    </div>
                    <StatusPill status={selected.statut} />
                  </div>
                  <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                    <ScoreRing score={selected.score_ia || 0} size={72} />
                  </div>
                </div>

                {/* Aperçu plan */}
                <div style={{ background: 'var(--sand)', borderRadius: 'var(--r)', padding: 14, marginBottom: 16, fontSize: 12, lineHeight: 1.7, color: 'var(--mid)', maxHeight: 200, overflow: 'auto' }}>
                  {detail ? (
                    <>
                      {detail.resume_global && <p style={{ marginBottom: 8, color: 'var(--dark)', fontStyle: 'italic' }}>{detail.resume_global}</p>}
                      <p>{getAxesText(detail)}</p>
                    </>
                  ) : (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                      <Skeleton height={12}/><Skeleton height={12}/><Skeleton height={12} width="70%"/>
                    </div>
                  )}
                </div>

                {/* Notes agent */}
                <div style={{ marginBottom: 16 }}>
                  <label style={{ fontSize: 11, fontWeight: 600, color: 'var(--mid)', textTransform: 'uppercase', letterSpacing: '.06em', display: 'block', marginBottom: 5 }}>
                    Notes agent (optionnel)
                  </label>
                  <textarea value={notes} onChange={e => setNotes(e.target.value)}
                    placeholder="Ajouter une note ou modification..."
                    rows={3}
                    style={{
                      width: '100%', padding: '10px 12px',
                      border: '1.5px solid var(--border)', borderRadius: 9,
                      fontSize: 12, resize: 'vertical', outline: 'none',
                      background: 'var(--sand)', fontFamily: 'var(--font-body)',
                      minHeight: 72,
                    }}
                    onFocus={e => { e.target.style.borderColor = 'var(--blue-mid)'; e.target.style.background = 'white' }}
                    onBlur={e => { e.target.style.borderColor = 'var(--border)'; e.target.style.background = 'var(--sand)' }}
                  />
                </div>

                {/* Actions */}
                {selected.statut === 'PENDING' || selected.statut === 'UNDER_REVIEW' ? (
                  <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                    <Button variant="primary" style={{ width: '100%', justifyContent: 'center' }}
                      loading={validating} onClick={handleValidate}>
                      ✓ Valider et envoyer au migrant
                    </Button>
                    <div style={{ display: 'flex', gap: 8 }}>
                      <Button variant="outline" size="sm" style={{ flex: 1, justifyContent: 'center' }}
                        onClick={() => toast('Modification en cours de développement', { icon: '🔧' })}>
                        ✏️ Modifier
                      </Button>
                      <Button variant="danger" size="sm" style={{ flex: 1, justifyContent: 'center' }}
                        onClick={handleReject}>
                        ✗ Refuser
                      </Button>
                    </div>
                  </div>
                ) : (
                  <div style={{ textAlign: 'center', fontSize: 13, color: 'var(--muted)', padding: '12px 0' }}>
                    Ce plan a déjà été traité — <StatusPill status={selected.statut} />
                  </div>
                )}

                {/* Fermer */}
                <button onClick={() => { setSelected(null); setDetail(null) }}
                  style={{ width: '100%', marginTop: 12, padding: '8px', background: 'none', border: 'none', cursor: 'pointer', color: 'var(--muted)', fontSize: 12, fontFamily: 'var(--font-body)' }}>
                  ← Fermer le panneau
                </button>
              </Card>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </div>
  )
}
