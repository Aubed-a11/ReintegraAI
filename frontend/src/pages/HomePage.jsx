import { useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useApp } from '../context/AppContext'
import { Button, Card, Tag, ScoreRing } from '../components/ui'

const fadeUp = i => ({
  initial: { opacity: 0, y: 20 },
  animate: { opacity: 1, y: 0 },
  transition: { delay: i * 0.08, duration: 0.4, ease: 'easeOut' },
})

export default function HomePage() {
  const { profile, plan, planId, user } = useApp()
  const navigate = useNavigate()

  const score      = plan?.score || plan?.score_ia || 0
  const planStatus = plan?.statut
  const completion = profile?.completion_pct || 0

  const statusLabel = {
    PENDING:      'En attente de validation',
    UNDER_REVIEW: 'En cours de révision',
    VALIDATED:    '✓ Validé par l\'agent OIM',
    REJECTED:     'Plan refusé',
  }

  return (
    <div style={{ padding: 28 }}>
      {/* Hero */}
      <motion.div {...fadeUp(0)} style={{
        background: 'var(--blue)',
        borderRadius: 22, padding: '48px 52px 40px',
        position: 'relative', overflow: 'hidden', marginBottom: 24,
      }}>
        {/* Dégradés décoratifs */}
        <div style={{ position: 'absolute', inset: 0, background: 'radial-gradient(ellipse 60% 80% at 90% -10%, rgba(196,122,53,.35) 0%, transparent 60%), radial-gradient(ellipse 40% 60% at -10% 120%, rgba(14,159,110,.20) 0%, transparent 50%)', pointerEvents: 'none' }} />

        <div style={{ position: 'relative' }}>
          <div style={{ fontSize: 11, fontWeight: 600, letterSpacing: '.14em', textTransform: 'uppercase', color: 'var(--ocre)', marginBottom: 14 }}>
            OIM Maroc × BAIC — Programme AVRR 2026
          </div>
          <h1 style={{ fontFamily: 'var(--font-display)', fontSize: 40, lineHeight: 1.12, fontWeight: 700, color: '#fff', maxWidth: 520, marginBottom: 16 }}>
            Votre plan de{' '}
            <em style={{ color: 'var(--ocre)', fontStyle: 'normal' }}>réintégration</em>,
            généré par l'IA
          </h1>
          <p style={{ fontSize: 14, lineHeight: 1.7, color: 'rgba(255,255,255,.62)', maxWidth: 460, marginBottom: 32 }}>
            HorizonAI analyse votre profil et croise les opportunités disponibles dans votre pays pour construire un plan personnalisé, validé par un agent OIM.
          </p>
          <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
            <Button variant="primary"
              onClick={() => planId ? navigate('/plan') : navigate('/profil')}>
              {planId ? 'Voir mon plan →' : 'Créer mon profil →'}
            </Button>
            <Button variant="ghost" onClick={() => navigate('/chat')}>
              Parler à l'assistant
            </Button>
          </div>
        </div>
      </motion.div>

      {/* Stats */}
      <motion.div {...fadeUp(1)} style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, marginBottom: 24 }}>
        {/* Score */}
        <Card style={{ padding: '22px 24px', borderTop: '3px solid var(--blue-mid)' }} hover={false}>
          <div style={{ fontFamily: 'var(--font-display)', fontSize: 38, fontWeight: 700, color: 'var(--blue)', lineHeight: 1, marginBottom: 6 }}>
            {score > 0 ? score : '—'}
          </div>
          <div style={{ fontSize: 12, color: 'var(--muted)', fontWeight: 500 }}>Score de faisabilité</div>
          <div style={{ marginTop: 10, fontSize: 11, fontWeight: 600, color: 'var(--green)' }}>
            {score >= 75 ? '↑ Excellente faisabilité' : score >= 50 ? 'Bonne faisabilité' : score > 0 ? 'Faisabilité modérée' : 'Générez votre plan'}
          </div>
        </Card>

        {/* Complétion profil */}
        <Card style={{ padding: '22px 24px', borderTop: '3px solid var(--ocre)' }} hover={false}>
          <div style={{ fontFamily: 'var(--font-display)', fontSize: 38, fontWeight: 700, color: 'var(--ocre)', lineHeight: 1, marginBottom: 6 }}>
            {completion}%
          </div>
          <div style={{ fontSize: 12, color: 'var(--muted)', fontWeight: 500 }}>Complétion du profil</div>
          <div style={{ marginTop: 10, fontSize: 11, fontWeight: 600, color: 'var(--green)' }}>
            {completion >= 100 ? '✓ Profil complet' : `${100 - completion}% restant`}
          </div>
        </Card>

        {/* Statut plan */}
        <Card style={{ padding: '22px 24px', borderTop: '3px solid var(--green)' }} hover={false}>
          <div style={{ fontFamily: 'var(--font-display)', fontSize: 28, fontWeight: 700, color: planStatus === 'VALIDATED' ? 'var(--green)' : 'var(--blue)', lineHeight: 1.2, marginBottom: 6 }}>
            {planStatus ? (planStatus === 'VALIDATED' ? '✓' : '⏳') : '—'}
          </div>
          <div style={{ fontSize: 12, color: 'var(--muted)', fontWeight: 500 }}>Statut du plan</div>
          <div style={{ marginTop: 10, fontSize: 11, fontWeight: 600, color: planStatus === 'VALIDATED' ? 'var(--green)' : 'var(--muted)' }}>
            {planStatus ? statusLabel[planStatus] : 'Aucun plan généré'}
          </div>
        </Card>
      </motion.div>

      {/* Cards actions */}
      <motion.div {...fadeUp(2)} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18 }}>
        {/* Prochaine étape */}
        <Card>
          <div style={{ padding: '18px 22px 0', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--mid)', textTransform: 'uppercase', letterSpacing: '.06em' }}>Prochaine étape</span>
            <Tag variant="ocre">Action requise</Tag>
          </div>
          <div style={{ padding: '14px 22px 22px' }}>
            {!profile ? (
              <>
                <p style={{ fontSize: 14, fontWeight: 600, color: 'var(--blue)', marginBottom: 8 }}>Complétez votre profil</p>
                <p style={{ fontSize: 12, color: 'var(--muted)', lineHeight: 1.6, marginBottom: 16 }}>Renseignez vos compétences, pays de retour et situation pour générer votre plan IA.</p>
                <Button variant="outline" size="sm" onClick={() => navigate('/profil')}>Aller au profil →</Button>
              </>
            ) : !planId ? (
              <>
                <p style={{ fontSize: 14, fontWeight: 600, color: 'var(--blue)', marginBottom: 8 }}>Générer votre plan IA</p>
                <p style={{ fontSize: 12, color: 'var(--muted)', lineHeight: 1.6, marginBottom: 16 }}>Votre profil est prêt. L'IA peut maintenant générer votre plan de réintégration personnalisé.</p>
                <Button variant="primary" size="sm" onClick={() => navigate('/plan')}>Générer mon plan →</Button>
              </>
            ) : planStatus === 'VALIDATED' ? (
              <>
                <p style={{ fontSize: 14, fontWeight: 600, color: 'var(--blue)', marginBottom: 8 }}>Plan validé — Préparez votre départ</p>
                <p style={{ fontSize: 12, color: 'var(--muted)', lineHeight: 1.6, marginBottom: 16 }}>Votre plan est approuvé. Consultez-le pour les prochaines étapes.</p>
                <Button variant="outline" size="sm" onClick={() => navigate('/plan')}>Voir mon plan →</Button>
              </>
            ) : (
              <>
                <p style={{ fontSize: 14, fontWeight: 600, color: 'var(--blue)', marginBottom: 8 }}>Plan en cours de validation</p>
                <p style={{ fontSize: 12, color: 'var(--muted)', lineHeight: 1.6, marginBottom: 16 }}>Un agent OIM va réviser votre plan. Vous serez notifié dès qu'il est validé.</p>
                <Button variant="outline" size="sm" onClick={() => navigate('/plan')}>Voir mon plan</Button>
              </>
            )}
          </div>
        </Card>

        {/* Contact agent */}
        <Card>
          <div style={{ padding: '18px 22px 0', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--mid)', textTransform: 'uppercase', letterSpacing: '.06em' }}>Contact OIM</span>
            <Tag variant="green">Disponible</Tag>
          </div>
          <div style={{ padding: '14px 22px 22px' }}>
            <p style={{ fontSize: 14, fontWeight: 600, color: 'var(--blue)', marginBottom: 4 }}>Votre agent référent</p>
            <p style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 16 }}>Outreach & Information Associate — OIM Maroc</p>
            <Button variant="outline" size="sm" onClick={() => navigate('/chat')}>
              Envoyer un message
            </Button>
          </div>
        </Card>
      </motion.div>
    </div>
  )
}
