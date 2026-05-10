import { useCallback, useEffect, useRef, useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { useNavigate } from 'react-router-dom'
import { interviewApi } from '../services/api'
import { useSpeech } from '../hooks/useSpeech'
import { useOfflineSync } from '../hooks/useOfflineSync'
import { offlineStore } from '../services/offlineStore'

const DEVICE_TOKEN = import.meta.env.VITE_KIOSK_TOKEN || 'DEMO_KIOSK_TOKEN'

const LANGS = [
  { code: 'fr',  label: 'Français',  flag: '🇫🇷', nativeTTS: true  },
  { code: 'en',  label: 'English',   flag: '🇬🇧', nativeTTS: true  },
  { code: 'ar',  label: 'العربية',   flag: '🇲🇦', nativeTTS: true  },
  { code: 'wo',  label: 'Wolof',     flag: '🇸🇳', nativeTTS: false },
  { code: 'bm',  label: 'Bambara',   flag: '🇲🇱', nativeTTS: false },
  { code: 'ha',  label: 'Hausa',     flag: '🇳🇪', nativeTTS: false },
  { code: 'ff',  label: 'Fulfuldé',  flag: '🌍',  nativeTTS: false },
  { code: 'tzm', label: 'Tamazight', flag: '🇩🇿', nativeTTS: false },
]

const FALLBACK_QUESTIONS = {
  fr: {
    BIENVENUE:    "Bonjour ! Je suis votre assistant HorizonAI. Parlez-moi de votre situation.",
    IDENTITE:     "Pouvez-vous me dire votre prénom et votre pays d'origine ?",
    VILLE:        "Dans quelle ville souhaitez-vous retourner ?",
    FAMILLE:      "Êtes-vous célibataire ou marié(e) ? Avez-vous des enfants ?",
    EDUCATION:    "Avez-vous été à l'école ? Si non, ce n'est pas un problème.",
    COMPETENCES:  "Qu'est-ce que vous savez faire ? Cultiver, vendre, coudre, conduire ?",
    OBJECTIFS:    "Quel est votre projet quand vous rentrez dans votre pays ?",
    SANTE:        "Comment va votre santé ? Avez-vous besoin d'une aide particulière ?",
    CONTACT:      "Avez-vous un numéro de téléphone où l'OIM peut vous contacter ?",
    RECAPITULATIF:"Merci pour vos réponses. Je génère votre plan de retour personnalisé.",
  },
  en: {
    BIENVENUE:    "Hello! I am your HorizonAI assistant. Tell me about your situation.",
    IDENTITE:     "Can you tell me your first name and country of origin?",
    VILLE:        "Which city do you plan to return to?",
    FAMILLE:      "Are you single or married? Do you have children?",
    EDUCATION:    "Did you go to school? If not, no problem, we can still help.",
    COMPETENCES:  "What can you do? Farming, selling, sewing, driving?",
    OBJECTIFS:    "What is your project when you return to your country?",
    SANTE:        "How is your health? Do you need any special help?",
    CONTACT:      "Do you have a phone number where IOM can contact you?",
    RECAPITULATIF:"Thank you! I am generating your personalized return plan.",
  },
  ar: {
    BIENVENUE:    "مرحباً! أنا مساعدك HorizonAI. حدثني عن وضعك.",
    IDENTITE:     "هل يمكنك إخباري باسمك وبلدك الأصلي؟",
    VILLE:        "إلى أي مدينة تريد العودة؟",
    FAMILLE:      "هل أنت أعزب أم متزوج؟ هل لديك أطفال؟",
    EDUCATION:    "هل ذهبت للمدرسة؟ إذا لم تذهب، لا تقلق.",
    COMPETENCES:  "ماذا تعرف أن تفعل؟ زراعة، بيع، خياطة، قيادة؟",
    OBJECTIFS:    "ما هو مشروعك عند عودتك لبلدك؟",
    SANTE:        "كيف صحتك؟ هل تحتاج مساعدة طبية؟",
    CONTACT:      "هل لديك رقم هاتف للتواصل؟",
    RECAPITULATIF:"شكراً! أقوم بإنشاء خطتك الآن.",
  },
  wo: {
    BIENVENUE:    "Salaam aleekum! Maa ngi HorizonAI. Waxal ma sa yoon.",
    IDENTITE:     "Lan mooy sa tuur ak sa dëkk?",
    VILLE:        "Fan la bëgg dem ci sa dëkk?",
    FAMILLE:      "Lan mooy sa situation familiale? Am nga dom?",
    EDUCATION:    "Daaw nga jàng? Bul ragal.",
    COMPETENCES:  "Lan nga xam def? Mbay, jënd, cosaan, kanam?",
    OBJECTIFS:    "Lan la bëgg def bu dem nga ci sa dëkk?",
    SANTE:        "Nanga wér? Am nga dara bu yees?",
    CONTACT:      "Am nga téléphone?",
    RECAPITULATIF:"Jërëjëf! Maa ngi soxor sa yoon bu bees.",
  },
  bm: {
    BIENVENUE:    "I ni ce! Ne ye HorizonAI ye. A fɔ i ko sen.",
    IDENTITE:     "I tɔgɔ ye mun ye, ani i ka jamana?",
    VILLE:        "I bɛ sɔrɔ min kɔfɛ?",
    FAMILLE:      "I den dɔnna wa?",
    EDUCATION:    "I tun bɛ kalankɛ wa? Ayi kɔrɔ, a ka se.",
    COMPETENCES:  "I bɛ se ka baara juman kɛ?",
    OBJECTIFS:    "I bɛ mun kɛ bɔ i bɛ segin?",
    SANTE:        "I ka kɛnɛya bɛ di?",
    CONTACT:      "I bɛ telefɔni dɔ sɔrɔ wa?",
    RECAPITULATIF:"I ni ce! N bɛna i ka seginkɛlɛ dilan.",
  },
  ha: {
    BIENVENUE:    "Sannu! Ni ne HorizonAI. Ka gaya mini labarka.",
    IDENTITE:     "Yaya sunanka da ƙasar da kake fitowa?",
    VILLE:        "Wane birni kake son komawa?",
    FAMILLE:      "Kana da aure? Kana da 'ya'ya?",
    EDUCATION:    "Ka je makaranta? Idan a'a, za mu iya taimaka.",
    COMPETENCES:  "Mene ne ka san yi? Noma, sayarwa, dinki?",
    OBJECTIFS:    "Me kake son yi lokacin da ka koma?",
    SANTE:        "Yaya lafiyarka?",
    CONTACT:      "Kana da lambar waya?",
    RECAPITULATIF:"Na gode! Zan shirya shirin dawowarka.",
  },
  ff: {
    BIENVENUE:    "Jam waali! Mi winndii HorizonAI. Haala mi haalannde maa.",
    IDENTITE:     "Ko holɗo togniral maa, e ko hol leydi maa?",
    VILLE:        "Wuro wanɗo kaa yiɗaa ruttude?",
    FAMILLE:      "A woodi ɓiɓɓe?",
    EDUCATION:    "Ndaarii-ɗaa e jangirde? Si alaa, ko wayaani.",
    COMPETENCES:  "Ko holɗo humpitii waɗaade?",
    OBJECTIFS:    "Ko holɗo yiɗaa waɗaade?",
    SANTE:        "Ko hol cellal maa?",
    CONTACT:      "A woodi nimero telefon?",
    RECAPITULATIF:"Jaari-ɗaa! Mi ñannoo piyanaa maa.",
  },
  tzm: {
    BIENVENUE:    "Azul! Nkk d HorizonAI. Aawi-yi-d s twuri-ik.",
    IDENTITE:     "Acu-t isem-ik, d acu-t tamurt-ik?",
    VILLE:        "Anida tebɣiḍ ad trjedjeḍ?",
    FAMILLE:      "Tesɛiḍ tarwa?",
    EDUCATION:    "Telliḍ s tɣiwant? Ur yelli, nezmer ad k-nεawen.",
    COMPETENCES:  "Acu tessen ad tgaḍ?",
    OBJECTIFS:    "Acu tebɣiḍ ad tgaḍ?",
    SANTE:        "Amek lḥal-ik s teɣzi?",
    CONTACT:      "Tesɛiḍ uṭṭun n tiliɣri?",
    RECAPITULATIF:"Tanmirt! Ad sbeddeɣ asenked-ik.",
  },
}

const ETAPES_ORDER = ['BIENVENUE','IDENTITE','VILLE','FAMILLE','EDUCATION','COMPETENCES','OBJECTIFS','SANTE','CONTACT','RECAPITULATIF']

// ── Phases : PRE_LAUNCH = écran agent avant de donner la borne au migrant
// THINKING | SPEAKING | LISTENING | DONE | ERROR

export default function KioskPage() {
  const navigate = useNavigate()
  const [lang, setLang]           = useState('fr')
  const [sessionId, setSessionId] = useState(null)
  const [phase, setPhase]         = useState('PRE_LAUNCH')
  const [currentQ, setCurrentQ]   = useState('')
  const [etape, setEtape]         = useState('BIENVENUE')
  const [stepNum, setStepNum]     = useState(0)
  const [stepTotal, setStepTotal] = useState(10)
  const [rdvInfo, setRdvInfo]     = useState(null)

  const speech              = useSpeech(lang)
  const { isOnline, syncNow } = useOfflineSync()
  const autoListenRef       = useRef(null)
  const silenceRef          = useRef(null)

  // Nettoyage timers au démontage
  useEffect(() => () => {
    speech.reset()
    clearTimeout(autoListenRef.current)
    clearTimeout(silenceRef.current)
  }, [])

  // Auto-écoute après fin du TTS
  useEffect(() => {
    if (phase === 'SPEAKING' && !speech.speaking) {
      autoListenRef.current = setTimeout(() => {
        setPhase('LISTENING')
        speech.startListening()
      }, 800)
    }
  }, [speech.speaking, phase])

  // Silence 3s → envoyer automatiquement
  useEffect(() => {
    if (phase !== 'LISTENING') return
    clearTimeout(silenceRef.current)
    if (speech.transcript) {
      silenceRef.current = setTimeout(() => handleSend(speech.transcript), 3000)
    }
  }, [speech.transcript, phase])

  // ── Démarrer une session (appelé par l'agent au clic)
  const startSession = useCallback(async () => {
    speech.reset()
    setSessionId(null)
    setCurrentQ('')
    setEtape('BIENVENUE')
    setStepNum(0)
    setRdvInfo(null)
    setPhase('THINKING')

    try {
      const res = await interviewApi.start(DEVICE_TOKEN, lang)
      if (res.ok) {
        const d = res.data.data
        setSessionId(d.session_id)
        setStepNum(d.step_num || 1)
        setStepTotal(d.step_total || 10)
        speakQuestion(d.question, 'BIENVENUE')
      } else throw new Error('API indisponible')
    } catch {
      // Mode offline : questions locales
      const q = FALLBACK_QUESTIONS[lang]?.BIENVENUE || FALLBACK_QUESTIONS.fr.BIENVENUE
      setSessionId('offline-' + Date.now())
      setStepNum(1)
      speakQuestion(q, 'BIENVENUE')
    }
  }, [lang, speech])

  const speakQuestion = (q, step) => {
    setCurrentQ(q)
    setEtape(step)
    setPhase('SPEAKING')
    speech.speak(q)
  }

  // ── Envoyer la réponse du migrant
  const handleSend = useCallback(async (text) => {
    if (!text?.trim() || phase !== 'LISTENING') return
    speech.stopListening()
    clearTimeout(silenceRef.current)
    setPhase('THINKING')

    const sid = sessionId

    try {
      if (isOnline && sid && !sid.startsWith('offline-')) {
        const res = await interviewApi.step(sid, text, DEVICE_TOKEN)
        if (res.ok) {
          const d = res.data.data
          setStepNum(d.step_num)
          if (d.completed) {
            setRdvInfo({ date: d.rdv_date, lieu: d.rdv_lieu })
            speakQuestion(d.question, 'RECAPITULATIF')
            setTimeout(() => setPhase('DONE'), 5000)
            return
          }
          speakQuestion(d.question, d.etape)
          return
        }
      }
      // Fallback offline
      const idx       = ETAPES_ORDER.indexOf(etape)
      const nextEtape = ETAPES_ORDER[idx + 1] || 'RECAPITULATIF'
      const q         = FALLBACK_QUESTIONS[lang]?.[nextEtape] || FALLBACK_QUESTIONS.fr[nextEtape] || '...'
      await offlineStore.queueRequest(`/interview/${sid}/step`, 'POST', { message: text })
      setStepNum(n => Math.min(n + 1, stepTotal))
      if (nextEtape === 'RECAPITULATIF') {
        speakQuestion(q, 'RECAPITULATIF')
        setTimeout(() => setPhase('DONE'), 5000)
      } else {
        speakQuestion(q, nextEtape)
      }
    } catch {
      setPhase('ERROR')
    }
  }, [phase, sessionId, etape, lang, isOnline, speech, stepTotal])

  const progress = stepTotal > 0 ? Math.round((stepNum / stepTotal) * 100) : 0
  const currentLang = LANGS.find(l => l.code === lang)

  // ════════════════════════════════════════════════════════
  // ÉCRAN PRE-LANCEMENT — visible par l'agent OIM seulement
  // ════════════════════════════════════════════════════════
  if (phase === 'PRE_LAUNCH') {
    return (
      <div style={{
        minHeight: '100vh',
        background: 'linear-gradient(160deg, #0a2040 0%, #0f3460 60%, #1a6fa8 100%)',
        display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
        padding: '40px 24px', fontFamily: 'var(--font-body)',
      }}>
        {/* Bouton retour discret */}
        <button
          onClick={() => navigate('/dashboard')}
          style={{
            position: 'fixed', top: 20, left: 24,
            background: 'rgba(255,255,255,.1)', border: '1px solid rgba(255,255,255,.2)',
            borderRadius: 8, padding: '7px 14px', color: 'rgba(255,255,255,.7)',
            fontSize: 12, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 6,
          }}>
          ← Dashboard
        </button>

        {/* Logo + titre */}
        <motion.div initial={{ opacity: 0, y: -16 }} animate={{ opacity: 1, y: 0 }}
          style={{ textAlign: 'center', marginBottom: 36 }}>
          <img src="/logo.svg" alt="HorizonAI" style={{ width: 56, height: 56, borderRadius: 14, marginBottom: 14 }} />
          <div style={{ fontSize: 11, fontWeight: 700, letterSpacing: '.16em', color: 'var(--ocre)', textTransform: 'uppercase', marginBottom: 6 }}>
            OIM Maroc × BAIC — Programme AVRR 2026
          </div>
          <h1 style={{ fontFamily: 'var(--font-display)', fontSize: 30, color: 'white', fontWeight: 700, margin: 0 }}>
            Kiosque HorizonAI
          </h1>
          <p style={{ fontSize: 13, color: 'rgba(255,255,255,.5)', marginTop: 6 }}>
            Entretien oral assisté par IA — Accueil migrant
          </p>
        </motion.div>

        {/* Encart instructions pour l'agent */}
        <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }}
          style={{
            background: 'rgba(255,255,255,.06)', border: '1px solid rgba(255,255,255,.12)',
            borderRadius: 18, padding: '24px 28px', maxWidth: 520, width: '100%', marginBottom: 28,
          }}>
          <div style={{ fontSize: 11, fontWeight: 700, letterSpacing: '.1em', color: 'var(--ocre)', textTransform: 'uppercase', marginBottom: 14 }}>
            Instructions agent OIM
          </div>
          {[
            ['1', 'Sélectionnez la langue du migrant ci-dessous'],
            ['2', 'Cliquez sur "Démarrer l\'entretien"'],
            ['3', 'Remettez l\'écran face au migrant'],
            ['4', 'Le migrant répond oralement — l\'IA transcrit et analyse'],
            ['5', 'À la fin : un RDV OIM est généré automatiquement'],
          ].map(([n, txt]) => (
            <div key={n} style={{ display: 'flex', alignItems: 'flex-start', gap: 12, marginBottom: 10 }}>
              <div style={{
                width: 24, height: 24, borderRadius: '50%', flexShrink: 0,
                background: 'var(--ocre)', color: 'white',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontSize: 11, fontWeight: 700,
              }}>{n}</div>
              <span style={{ fontSize: 13, color: 'rgba(255,255,255,.8)', lineHeight: 1.5, paddingTop: 3 }}>{txt}</span>
            </div>
          ))}
          {!speech.supported && (
            <div style={{ marginTop: 12, background: 'rgba(229,57,53,.15)', borderRadius: 8, padding: '8px 12px', fontSize: 11, color: '#ff8a80' }}>
              ⚠️ Ce navigateur ne supporte pas la voix. Utilisez Chrome ou Edge pour activer la reconnaissance vocale.
            </div>
          )}
        </motion.div>

        {/* Sélecteur de langue */}
        <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.2 }}
          style={{ maxWidth: 520, width: '100%', marginBottom: 28 }}>
          <div style={{ fontSize: 12, fontWeight: 600, color: 'rgba(255,255,255,.5)', marginBottom: 10, textTransform: 'uppercase', letterSpacing: '.08em' }}>
            Langue du migrant
          </div>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
            {LANGS.map(l => (
              <motion.button key={l.code} whileTap={{ scale: 0.93 }} onClick={() => setLang(l.code)}
                style={{
                  padding: '9px 16px', borderRadius: 10, fontSize: 13, fontWeight: 600, cursor: 'pointer',
                  border: `2px solid ${lang === l.code ? 'var(--ocre)' : 'rgba(255,255,255,.18)'}`,
                  background: lang === l.code ? 'var(--ocre)' : 'rgba(255,255,255,.06)',
                  color: 'white', display: 'flex', alignItems: 'center', gap: 6,
                }}>
                <span>{l.flag}</span>
                <span>{l.label}</span>
                {!l.nativeTTS && <span style={{ fontSize: 9, opacity: 0.6 }}>🔊FR</span>}
              </motion.button>
            ))}
          </div>
          {currentLang && !currentLang.nativeTTS && (
            <div style={{ marginTop: 8, fontSize: 11, color: 'rgba(196,122,53,.9)' }}>
              🔊 Le texte s'affichera en {currentLang.label} — l'audio sera lu en français
            </div>
          )}
        </motion.div>

        {/* Bouton démarrer */}
        <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.3 }}>
          <motion.button
            whileHover={{ scale: 1.03 }} whileTap={{ scale: 0.97 }}
            onClick={startSession}
            style={{
              padding: '18px 52px', borderRadius: 16, fontSize: 18, fontWeight: 700,
              cursor: 'pointer', border: 'none',
              background: 'linear-gradient(135deg, var(--ocre), #e8973f)',
              color: 'white', boxShadow: '0 8px 32px rgba(196,122,53,.4)',
              letterSpacing: '.02em',
            }}>
            ▶ Démarrer l'entretien
          </motion.button>
        </motion.div>

        <div style={{ marginTop: 32, fontSize: 10, color: 'rgba(255,255,255,.2)' }}>
          HorizonAI v1.1 · Données chiffrées · OIM · {isOnline ? '🟢 En ligne' : '🟠 Hors ligne'}
        </div>
      </div>
    )
  }

  // ════════════════════════════════════════════════════════
  // ÉCRAN ENTRETIEN (THINKING | SPEAKING | LISTENING | DONE | ERROR)
  // ════════════════════════════════════════════════════════
  return (
    <div style={{
      minHeight: '100vh',
      background: 'linear-gradient(160deg, #0a2040 0%, #0f3460 60%, #1a6fa8 100%)',
      display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
      padding: '40px 24px', fontFamily: 'var(--font-body)',
    }}>

      {/* Bouton retour — visible seulement quand DONE ou ERROR */}
      {(phase === 'DONE' || phase === 'ERROR') && (
        <button
          onClick={() => navigate('/dashboard')}
          style={{
            position: 'fixed', top: 20, left: 24,
            background: 'rgba(255,255,255,.1)', border: '1px solid rgba(255,255,255,.2)',
            borderRadius: 8, padding: '7px 14px', color: 'rgba(255,255,255,.7)',
            fontSize: 12, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 6,
          }}>
          ← Dashboard
        </button>
      )}

      {/* Badge offline */}
      <AnimatePresence>
        {!isOnline && (
          <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }}
            style={{
              position: 'fixed', top: 20, right: 24,
              background: 'rgba(196,122,53,.25)', border: '1px solid var(--ocre)',
              borderRadius: 8, padding: '6px 14px', fontSize: 11, color: 'var(--ocre)',
              display: 'flex', alignItems: 'center', gap: 6,
            }}>
            ⚡ Hors ligne — données sauvegardées
          </motion.div>
        )}
      </AnimatePresence>

      {/* Header */}
      <motion.div initial={{ opacity: 0, y: -16 }} animate={{ opacity: 1, y: 0 }}
        style={{ textAlign: 'center', marginBottom: 28 }}>
        <div style={{ fontSize: 10, fontWeight: 700, letterSpacing: '.16em', color: 'var(--ocre)', textTransform: 'uppercase', marginBottom: 6 }}>
          OIM Maroc × BAIC — Programme AVRR 2026
        </div>
        <h1 style={{ fontFamily: 'var(--font-display)', fontSize: 26, color: 'white', fontWeight: 700, margin: 0 }}>
          HorizonAI — Kiosque
        </h1>
      </motion.div>

      {/* Barre de progression */}
      {stepNum > 0 && phase !== 'DONE' && (
        <div style={{ width: '100%', maxWidth: 560, marginBottom: 24 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
            <span style={{ fontSize: 11, color: 'rgba(255,255,255,.5)' }}>Étape {stepNum} / {stepTotal}</span>
            <span style={{ fontSize: 11, color: 'rgba(255,255,255,.5)' }}>{progress}%</span>
          </div>
          <div style={{ height: 5, background: 'rgba(255,255,255,.12)', borderRadius: 4 }}>
            <motion.div animate={{ width: `${progress}%` }} transition={{ duration: 0.5 }}
              style={{ height: '100%', background: 'var(--ocre)', borderRadius: 4 }} />
          </div>
        </div>
      )}

      {/* Zone principale */}
      <motion.div initial={{ opacity: 0, scale: 0.97 }} animate={{ opacity: 1, scale: 1 }}
        style={{
          background: 'rgba(255,255,255,.06)', backdropFilter: 'blur(14px)',
          border: '1px solid rgba(255,255,255,.12)', borderRadius: 24,
          padding: '40px 44px', maxWidth: 580, width: '100%', textAlign: 'center',
        }}>

        {/* Onde sonore animée */}
        <WaveAnimation phase={phase} />

        {/* Badge TTS fallback */}
        {speech.usingFallback && phase !== 'DONE' && (
          <div style={{
            display: 'inline-flex', alignItems: 'center', gap: 6,
            background: 'rgba(196,122,53,.18)', border: '1px solid rgba(196,122,53,.4)',
            borderRadius: 20, padding: '4px 12px', fontSize: 11,
            color: 'var(--ocre)', marginBottom: 12,
          }}>
            🔊 Audio en français · Texte en {currentLang?.label}
          </div>
        )}

        {/* Statut */}
        <div style={{ fontSize: 13, color: 'rgba(255,255,255,.5)', marginBottom: 18, minHeight: 20 }}>
          {phase === 'SPEAKING'  && '🔊 En train de parler...'}
          {phase === 'LISTENING' && '🎤 En écoute — parlez maintenant'}
          {phase === 'THINKING'  && '⏳ Traitement en cours...'}
          {phase === 'DONE'      && '✅ Entretien terminé'}
          {phase === 'ERROR'     && '❌ Erreur — vérifiez la connexion'}
        </div>

        {/* Question courante */}
        {phase !== 'DONE' && phase !== 'ERROR' && (
          <AnimatePresence mode="wait">
            <motion.p key={currentQ}
              initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }}
              style={{
                fontSize: 22, fontWeight: 600, color: 'white', lineHeight: 1.5,
                margin: '0 0 28px', minHeight: 80,
              }}>
              {currentQ || '...'}
            </motion.p>
          </AnimatePresence>
        )}

        {/* Transcript en direct */}
        {phase === 'LISTENING' && speech.transcript && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }}
            style={{
              background: 'rgba(255,255,255,.08)', borderRadius: 12,
              padding: '12px 16px', marginBottom: 24,
              fontSize: 15, color: 'rgba(255,255,255,.85)', fontStyle: 'italic', lineHeight: 1.5,
            }}>
            « {speech.transcript} »
          </motion.div>
        )}

        {/* Écran FIN — RDV + résumé */}
        {phase === 'DONE' && (
          <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}>
            <div style={{ fontSize: 40, marginBottom: 16 }}>✅</div>
            <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 22, color: 'white', marginBottom: 20 }}>
              Entretien terminé avec succès
            </h2>
            {rdvInfo && (
              <div style={{
                background: 'rgba(14,159,110,.2)', border: '1px solid rgba(14,159,110,.4)',
                borderRadius: 14, padding: '20px 24px', marginBottom: 24,
              }}>
                <div style={{ fontSize: 13, color: 'rgba(255,255,255,.6)', marginBottom: 6 }}>Rendez-vous OIM</div>
                <div style={{ fontSize: 24, fontWeight: 700, color: 'var(--ocre)', marginBottom: 4 }}>
                  {rdvInfo.date}
                </div>
                <div style={{ fontSize: 13, color: 'rgba(255,255,255,.7)' }}>{rdvInfo.lieu}</div>
              </div>
            )}
            <p style={{ fontSize: 13, color: 'rgba(255,255,255,.6)', marginBottom: 28, lineHeight: 1.6 }}>
              Le dossier du migrant a été transmis aux agents OIM pour validation.
              Le plan de réintégration sera communiqué lors du rendez-vous.
            </p>
          </motion.div>
        )}

        {/* Erreur */}
        {phase === 'ERROR' && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
            <div style={{ fontSize: 38, marginBottom: 12 }}>⚠️</div>
            <p style={{ fontSize: 15, color: 'rgba(255,255,255,.7)', marginBottom: 24 }}>
              Une erreur est survenue. Vérifiez la connexion et réessayez.
            </p>
          </motion.div>
        )}

        {/* Boutons de contrôle */}
        <div style={{ display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap' }}>
          {phase === 'LISTENING' && (
            <KioskBtn onClick={() => handleSend(speech.transcript)} variant="primary">
              ✅ Envoyer
            </KioskBtn>
          )}
          {(phase === 'SPEAKING' || phase === 'LISTENING') && (
            <KioskBtn onClick={() => { speech.stopSpeaking(); setPhase('LISTENING'); speech.startListening() }} variant="ghost">
              🎤 Parler maintenant
            </KioskBtn>
          )}
          {(phase === 'SPEAKING' || phase === 'LISTENING') && (
            <KioskBtn onClick={() => { speech.stopSpeaking(); speech.speak(currentQ) }} variant="ghost">
              🔁 Répéter
            </KioskBtn>
          )}
          {(phase === 'DONE' || phase === 'ERROR') && (
            <KioskBtn onClick={() => setPhase('PRE_LAUNCH')} variant="primary">
              👤 Nouveau migrant
            </KioskBtn>
          )}
        </div>
      </motion.div>

      {/* Langue affichée */}
      {phase !== 'DONE' && (
        <div style={{ marginTop: 20, fontSize: 12, color: 'rgba(255,255,255,.3)' }}>
          {currentLang?.flag} {currentLang?.label}
        </div>
      )}
    </div>
  )
}

// ── Onde sonore animée ─────────────────────────────────────────
function WaveAnimation({ phase }) {
  const isActive = phase === 'SPEAKING' || phase === 'LISTENING'
  const bars = 11
  return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 5, height: 68, marginBottom: 20 }}>
      {Array.from({ length: bars }).map((_, i) => (
        <motion.div key={i}
          animate={isActive ? {
            height: [10, 28 + Math.abs(Math.sin(i * 0.8)) * 28, 10],
            opacity: [0.4, 1, 0.4],
          } : { height: 6, opacity: 0.2 }}
          transition={isActive ? {
            duration: 0.7 + i * 0.07,
            repeat: Infinity,
            ease: 'easeInOut',
            delay: i * 0.05,
          } : { duration: 0.3 }}
          style={{
            width: 5, borderRadius: 4,
            background: phase === 'LISTENING' ? 'var(--ocre)' : 'rgba(255,255,255,.75)',
          }}
        />
      ))}
    </div>
  )
}

// ── Bouton kiosque ─────────────────────────────────────────────
function KioskBtn({ children, onClick, variant = 'ghost' }) {
  return (
    <motion.button whileTap={{ scale: 0.94 }} onClick={onClick}
      style={{
        padding: '13px 26px', borderRadius: 12, fontSize: 15, fontWeight: 700, cursor: 'pointer',
        border: variant === 'primary' ? '2px solid var(--ocre)' : '1.5px solid rgba(255,255,255,.25)',
        background: variant === 'primary' ? 'var(--ocre)' : 'rgba(255,255,255,.07)',
        color: 'white',
      }}>
      {children}
    </motion.button>
  )
}
