import { AnimatePresence, motion } from 'framer-motion'
import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import { useNavigate } from 'react-router-dom'
import { Button, Card, EmptyState, ScoreRing, StatusPill, Tag } from '../components/ui'
import { useApp } from '../context/AppContext'
import { useSpeech } from '../hooks/useSpeech'
import { planApi } from '../services/api'

function SpeakerIcon() {
  return (
    <svg width="15" height="15" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
      <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
      <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
    </svg>
  )
}

function PauseIcon() {
  return (
    <svg width="15" height="15" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <rect x="6" y="4" width="4" height="16"/>
      <rect x="14" y="4" width="4" height="16"/>
    </svg>
  )
}

const AXE_CONFIG = {
  emploi:   { label: 'Emploi & Formation', color: 'var(--blue-mid)', bg: 'var(--blue-light)',  icon: '💼', num: 1 },
  logement: { label: 'Logement',           color: 'var(--green)',     bg: 'var(--green-light)', icon: '🏠', num: 2 },
  finance:  { label: 'Soutien financier',  color: 'var(--ocre)',      bg: 'var(--ocre-light)',  icon: '💰', num: 3 },
  sante:    { label: 'Santé',              color: '#e53935',          bg: '#fdecea',            icon: '❤️', num: 4 },
}

const LOADING_STEPS = [
  { text: 'Analyse du profil...', delay: 0 },
  { text: 'Matching des opportunités locales...', delay: 800 },
  { text: 'Construction du prompt IA...', delay: 1600 },
  { text: 'Génération par Claude Sonnet...', delay: 2500 },
  { text: 'Structuration du plan en 4 axes...', delay: 4000 },
]

// Compose le texte intégral du plan pour la lecture TTS
function buildPlanText(plan, axes) {
  const parts = []
  if (plan.resume_global) parts.push(plan.resume_global)
  Object.entries(axes).forEach(([, axe]) => {
    if (!axe?.items?.length) return
    parts.push(axe.label || '')
    axe.items.forEach(item => {
      parts.push(item.titre + '. ' + (item.description || ''))
    })
  })
  if (plan.prochaine_etape) parts.push('Prochaine étape. ' + plan.prochaine_etape)
  return parts.join('. ')
}

export default function PlanPage() {
  const { plan, planId, loading, generatePlan, profile, lang } = useApp()
  const navigate   = useNavigate()
  const [expanded, setExpanded]     = useState({ emploi: true })
  const [loadStep,  setLoadStep]    = useState(0)
  const [generating, setGenerating] = useState(false)
  const [pdfLoading, setPdfLoading] = useState(false)

  const { speak, stopSpeaking, speaking, supported } = useSpeech(lang || 'fr')

  // Simuler les étapes de chargement
  useEffect(() => {
    if (!generating) return
    LOADING_STEPS.forEach((s, i) => {
      setTimeout(() => setLoadStep(i), s.delay)
    })
  }, [generating])

  const handleGenerate = async () => {
    if (!profile || profile.completion_pct < 60) {
      toast.error('Complétez votre profil à 60% minimum avant de générer un plan')
      navigate('/profil')
      return
    }
    setGenerating(true)
    setLoadStep(0)
    await generatePlan()
    setGenerating(false)
  }

  const handleExportPdf = async () => {
    if (!planId) return
    setPdfLoading(true)
    const res = await planApi.exportPdf(planId)
    setPdfLoading(false)

    if (res.ok && res.data?.data?.pdf_url) {
      const pdfUrl = res.data.data.pdf_url
      const anchor = document.createElement('a')
      anchor.href = pdfUrl
      anchor.target = '_blank'
      anchor.download = `plan_${planId}.pdf`
      document.body.appendChild(anchor)
      anchor.click()
      anchor.remove()
      toast.success('PDF généré et téléchargé')
      return
    }

    toast.error('Erreur export PDF')
  }

  const getAxes = () => {
    if (!plan) return {}
    if (plan.axes) return plan.axes
    return {
      emploi:   plan.axe_emploi   || { label: 'Emploi & Formation', items: [] },
      logement: plan.axe_logement  || { label: 'Logement',           items: [] },
      finance:  plan.axe_finance   || { label: 'Soutien financier',  items: [] },
      sante:    plan.axe_sante     || { label: 'Santé',              items: [] },
    }
  }

  const score  = plan?.score || plan?.score_ia || 0
  const statut = plan?.statut || 'PENDING'
  const axes   = getAxes()

  // ── Écran chargement génération ────────────────────────────
  if (generating || loading.plan) {
    return (
      <div style={{ padding: 28, display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: 'calc(100vh - 60px)' }}>
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} style={{ textAlign: 'center', maxWidth: 400 }}>
          {/* Animation IA */}
          <div style={{ position: 'relative', width: 80, height: 80, margin: '0 auto 28px' }}>
            <motion.div
              animate={{ rotate: 360 }}
              transition={{ duration: 2, repeat: Infinity, ease: 'linear' }}
              style={{
                width: 80, height: 80, borderRadius: '50%',
                border: '3px solid var(--blue-light)',
                borderTopColor: 'var(--blue)',
              }}
            />
            <div style={{
              position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontSize: 28,
            }}>🤖</div>
          </div>

          <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 22, color: 'var(--blue)', marginBottom: 10 }}>
            Génération en cours...
          </h3>

          <AnimatePresence mode="wait">
            <motion.p key={loadStep}
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -8 }}
              style={{ fontSize: 14, color: 'var(--muted)', marginBottom: 28 }}
            >
              {LOADING_STEPS[loadStep]?.text}
            </motion.p>
          </AnimatePresence>

          {/* Steps progress */}
          <div style={{ display: 'flex', justifyContent: 'center', gap: 8 }}>
            {LOADING_STEPS.map((s, i) => (
              <motion.div key={i}
                animate={{ background: i <= loadStep ? 'var(--blue)' : 'var(--border)' }}
                style={{ width: i <= loadStep ? 24 : 8, height: 8, borderRadius: 4, transition: 'all .4s' }}
              />
            ))}
          </div>
        </motion.div>
      </div>
    )
  }

  // ── Écran vide ─────────────────────────────────────────────
  if (!plan && !planId) {
    return (
      <div style={{ padding: 28 }}>
        <EmptyState
          icon="📋"
          title="Aucun plan généré"
          subtitle={profile ? 'Votre profil est prêt. Cliquez pour générer votre plan de réintégration personnalisé par l\'IA.' : 'Complétez d\'abord votre profil, puis revenez générer votre plan.'}
          action={
            <div style={{ display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap' }}>
              {profile ? (
                <Button variant="primary" onClick={handleGenerate}>
                  🤖 Générer mon plan IA
                </Button>
              ) : (
                <Button variant="primary" onClick={() => navigate('/profil')}>
                  Compléter mon profil →
                </Button>
              )}
            </div>
          }
        />
      </div>
    )
  }

  // ── Plan affiché ───────────────────────────────────────────
  return (
    <div style={{ padding: 28 }}>
      {/* En-tête plan */}
      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        style={{
          background: 'white', border: '1px solid var(--border)',
          borderRadius: 'var(--r-xl)', padding: '24px 28px',
          display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between',
          gap: 20, marginBottom: 22,
        }}
      >
        <div style={{ flex: 1 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 7, marginBottom: 12 }}>
            <StatusPill status={statut} />
          </div>
          <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 24, fontWeight: 700, color: 'var(--blue)', marginBottom: 6 }}>
            Plan de réintégration
          </h2>
          <p style={{ fontSize: 12, color: 'var(--muted)' }}>
            {plan?.created_at && `Généré le ${new Date(plan.created_at).toLocaleDateString('fr-FR')} · `}
            {plan?.pays_origine && `Pays : ${plan.pays_origine}`}
            {plan?.model_version && ` · Modèle : ${plan.model_version}`}
          </p>
          {plan?.resume_global && (
            <p style={{ marginTop: 12, fontSize: 13, color: 'var(--mid)', lineHeight: 1.6, maxWidth: 560, padding: '10px 14px', background: 'var(--sand)', borderRadius: 9, borderLeft: '3px solid var(--blue-mid)' }}>
              {plan.resume_global}
            </p>
          )}

          {/* Bouton écouter */}
          {supported && (
            <div style={{ marginTop: 14 }}>
              {speaking ? (
                <button
                  onClick={stopSpeaking}
                  style={{
                    display: 'inline-flex', alignItems: 'center', gap: 7,
                    padding: '8px 16px', borderRadius: 20,
                    border: '1.5px solid #e53935', background: '#fdecea',
                    color: '#c62828', fontSize: 13, fontWeight: 600, cursor: 'pointer',
                  }}
                >
                  <PauseIcon />
                  Arrêter la lecture
                </button>
              ) : (
                <button
                  onClick={() => speak(buildPlanText(plan, axes))}
                  style={{
                    display: 'inline-flex', alignItems: 'center', gap: 7,
                    padding: '8px 16px', borderRadius: 20,
                    border: '1.5px solid var(--blue-mid)', background: 'var(--blue-light)',
                    color: 'var(--blue)', fontSize: 13, fontWeight: 600, cursor: 'pointer',
                  }}
                >
                  <SpeakerIcon />
                  Écouter mon plan
                </button>
              )}
            </div>
          )}
        </div>
        <ScoreRing score={score} size={96} />
      </motion.div>

      {/* Axes accordéon */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 22 }}>
        {Object.entries(axes).map(([key, axe], i) => {
          const cfg   = AXE_CONFIG[key] || AXE_CONFIG.emploi
          const items = axe.items || []
          const open  = expanded[key]

          return (
            <motion.div
              key={key}
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.1 }}
              style={{
                background: 'white',
                border: `1px solid ${open ? cfg.color + '44' : 'var(--border)'}`,
                borderRadius: 'var(--r)', overflow: 'hidden',
                cursor: 'pointer',
                boxShadow: open ? `0 4px 16px ${cfg.color}18` : 'none',
                transition: 'border-color .25s, box-shadow .25s',
              }}
              onClick={() => setExpanded(e => ({ ...e, [key]: !e[key] }))}
            >
              {/* Header axe */}
              <div style={{ padding: '16px 18px', display: 'flex', alignItems: 'center', gap: 12 }}>
                <div style={{
                  width: 40, height: 40, borderRadius: 10,
                  background: cfg.bg, display: 'flex', alignItems: 'center', justifyContent: 'center',
                  fontSize: 18, flexShrink: 0,
                }}>
                  {cfg.icon}
                </div>
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 10, fontWeight: 600, letterSpacing: '.1em', textTransform: 'uppercase', color: 'var(--muted)', marginBottom: 1 }}>
                    Axe {cfg.num}
                  </div>
                  <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--dark)' }}>
                    {axe.label || cfg.label}
                  </div>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <Tag variant="gray" style={{ fontSize: 10 }}>{items.length} action{items.length > 1 ? 's' : ''}</Tag>
                  <svg
                    width="16" height="16" fill="none" stroke="var(--muted)" strokeWidth="2" viewBox="0 0 24 24"
                    style={{ transform: open ? 'rotate(180deg)' : 'none', transition: 'transform .3s', flexShrink: 0 }}
                  >
                    <polyline points="6 9 12 15 18 9"/>
                  </svg>
                </div>
              </div>

              {/* Corps axe */}
              <AnimatePresence initial={false}>
                {open && (
                  <motion.div
                    initial={{ height: 0 }}
                    animate={{ height: 'auto' }}
                    exit={{ height: 0 }}
                    style={{ overflow: 'hidden' }}
                    transition={{ duration: 0.3, ease: 'easeInOut' }}
                  >
                    <div style={{ padding: '0 18px 18px', borderTop: '1px solid var(--border)' }}>
                      {items.length === 0 ? (
                        <p style={{ fontSize: 12, color: 'var(--muted)', padding: '12px 0' }}>Aucune recommandation pour cet axe.</p>
                      ) : items.map((item, j) => (
                        <div key={j} style={{
                          display: 'flex', alignItems: 'flex-start', gap: 10,
                          padding: '10px 0',
                          borderBottom: j < items.length - 1 ? '1px solid var(--border-light)' : 'none',
                        }}>
                          <div style={{ width: 6, height: 6, borderRadius: '50%', background: cfg.color, flexShrink: 0, marginTop: 6 }} />
                          <div>
                            <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--dark)', marginBottom: 2 }}>{item.titre}</div>
                            <div style={{ fontSize: 12, color: 'var(--mid)', lineHeight: 1.5 }}>{item.description}</div>
                            {(item.cout_estime || item.duree || item.organisme) && (
                              <div style={{ display: 'flex', gap: 8, marginTop: 6, flexWrap: 'wrap' }}>
                                {item.organisme  && <Tag variant="gray" style={{ fontSize: 10 }}>{item.organisme}</Tag>}
                                {item.cout_estime && <Tag variant="blue" style={{ fontSize: 10 }}>{item.cout_estime}</Tag>}
                                {item.duree      && <Tag variant="ocre" style={{ fontSize: 10 }}>{item.duree}</Tag>}
                              </div>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  </motion.div>
                )}
              </AnimatePresence>
            </motion.div>
          )
        })}
      </div>

      {/* Alerte vulnérabilité */}
      {plan?.alerte_vulnerabilite && (
        <motion.div
          initial={{ opacity: 0 }} animate={{ opacity: 1 }}
          style={{
            background: '#fff8e1', border: '1px solid #f9a825',
            borderRadius: 'var(--r)', padding: '14px 18px',
            marginBottom: 22, fontSize: 13, color: '#8a5e00',
            display: 'flex', gap: 10, alignItems: 'flex-start',
          }}
        >
          <span style={{ flexShrink: 0, fontSize: 16 }}>⚠️</span>
          <p>{plan.alerte_vulnerabilite}</p>
        </motion.div>
      )}

      {/* Prochaine étape */}
      {plan?.prochaine_etape && (
        <Card style={{ padding: '18px 22px', marginBottom: 22, borderLeft: '3px solid var(--ocre)' }} hover={false}>
          <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--ocre)', textTransform: 'uppercase', letterSpacing: '.06em', marginBottom: 8 }}>
            Prochaine étape (72h)
          </div>
          <p style={{ fontSize: 13, color: 'var(--mid)', lineHeight: 1.6 }}>{plan.prochaine_etape}</p>
        </Card>
      )}

      {/* Bannière export PDF */}
      <motion.div
        initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }}
        style={{
          background: 'linear-gradient(135deg, var(--blue) 0%, var(--blue-dark) 100%)',
          borderRadius: 'var(--r)', padding: '20px 24px',
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          color: 'white',
        }}
      >
        <div>
          <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 16, fontWeight: 700, marginBottom: 4 }}>
            Télécharger votre plan complet
          </h3>
          <p style={{ fontSize: 12, color: 'rgba(255,255,255,.55)' }}>
            Document PDF officiel OIM — à conserver pour vos démarches
          </p>
        </div>
        <Button variant="primary" size="sm" loading={pdfLoading} onClick={handleExportPdf}>
          📄 Exporter PDF
        </Button>
      </motion.div>
    </div>
  )
}
