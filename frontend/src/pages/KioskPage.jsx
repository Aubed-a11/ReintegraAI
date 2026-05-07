import { useCallback, useEffect, useRef, useState } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { interviewApi } from '../services/api'
import { useSpeech } from '../hooks/useSpeech'
import { useOfflineSync } from '../hooks/useOfflineSync'
import { offlineStore } from '../services/offlineStore'

const DEVICE_TOKEN = import.meta.env.VITE_KIOSK_TOKEN || 'DEMO_KIOSK_TOKEN'

const LANGS = [
  { code: 'fr',  label: 'Français',         flag: '🇫🇷', nativeTTS: true  },
  { code: 'en',  label: 'English',           flag: '🇬🇧', nativeTTS: true  },
  { code: 'ar',  label: 'العربية',           flag: '🇲🇦', nativeTTS: true  },
  { code: 'wo',  label: 'Wolof',             flag: '🇸🇳', nativeTTS: false },
  { code: 'bm',  label: 'Bambara',           flag: '🇲🇱', nativeTTS: false },
  { code: 'ha',  label: 'Hausa',             flag: '🇳🇪', nativeTTS: false },
  { code: 'ff',  label: 'Fulfuldé',          flag: '🌍',  nativeTTS: false },
  { code: 'tzm', label: 'Tamazight',         flag: '🇩🇿', nativeTTS: false },
]

// Questions de repli si le réseau est coupé — toutes les 8 langues
const FALLBACK_QUESTIONS = {
  fr: {
    BIENVENUE:    "Bonjour ! Je suis votre assistant HorizonAI. Dans quelle langue souhaitez-vous que je vous parle ?",
    IDENTITE:     "Pouvez-vous me dire votre prénom et votre pays d'origine ?",
    VILLE:        "Dans quelle ville souhaitez-vous retourner ?",
    FAMILLE:      "Êtes-vous célibataire ou marié(e) ? Avez-vous des enfants ?",
    EDUCATION:    "Avez-vous été à l'école ? Si non, ce n'est pas un problème.",
    COMPETENCES:  "Qu'est-ce que vous savez faire ? Par exemple : cultiver, vendre, coudre, conduire ?",
    OBJECTIFS:    "Quel est votre projet quand vous rentrez dans votre pays ?",
    SANTE:        "Comment va votre santé ? Avez-vous besoin d'une aide particulière ?",
    CONTACT:      "Avez-vous un numéro de téléphone où l'OIM peut vous contacter ?",
    RECAPITULATIF:"Merci ! Je génère votre plan de retour. Votre rendez-vous OIM vous sera communiqué.",
  },
  en: {
    BIENVENUE:    "Hello! I am your HorizonAI assistant. Which language would you like?",
    IDENTITE:     "Can you tell me your first name and country of origin?",
    VILLE:        "Which city do you plan to return to?",
    FAMILLE:      "Are you single or married? Do you have children?",
    EDUCATION:    "Did you go to school? If not, no problem, we can still help.",
    COMPETENCES:  "What can you do? For example: farming, selling, sewing, driving?",
    OBJECTIFS:    "What is your project when you return to your country?",
    SANTE:        "How is your health? Do you need any special help?",
    CONTACT:      "Do you have a phone number where IOM can contact you?",
    RECAPITULATIF:"Thank you! I am generating your return plan.",
  },
  ar: {
    BIENVENUE:    "مرحباً! أنا مساعدك HorizonAI. أي لغة تريد؟",
    IDENTITE:     "هل يمكنك إخباري باسمك وبلدك الأصلي؟",
    VILLE:        "إلى أي مدينة تريد العودة؟",
    FAMILLE:      "هل أنت أعزب أم متزوج؟ هل لديك أطفال؟",
    EDUCATION:    "هل ذهبت للمدرسة؟ إذا لم تذهب، لا تقلق.",
    COMPETENCES:  "ماذا تعرف أن تفعل؟ مثلاً: زراعة، بيع، خياطة، قيادة؟",
    OBJECTIFS:    "ما هو مشروعك عند عودتك لبلدك؟",
    SANTE:        "كيف صحتك؟ هل تحتاج مساعدة طبية؟",
    CONTACT:      "هل لديك رقم هاتف للتواصل؟",
    RECAPITULATIF:"شكراً! أقوم بإنشاء خطتك الآن.",
  },
  wo: {
    BIENVENUE:    "Salaam aleekum! Maa ngi HorizonAI. Lan la ci kanam?",
    IDENTITE:     "Lan mooy sa tuur ak sa dëkk?",
    VILLE:        "Fan la bëgg dem ci sa dëkk?",
    FAMILLE:      "Lan mooy sa jabar-jabar? Am nga dom?",
    EDUCATION:    "Daaw nga jàng? Bul ragal, dañuy mëna dëgg.",
    COMPETENCES:  "Lan nga xam def? Mbay, jënd, cosaan, kanam?",
    OBJECTIFS:    "Lan la bëgg def bu dem nga ci sa dëkk?",
    SANTE:        "Nanga wér? Am nga dara bu yees?",
    CONTACT:      "Am nga téléphone? IOM dafay bëgg wax ak yow.",
    RECAPITULATIF:"Jërëjëf! Maa ngi soxor sa yoon bu bees...",
  },
  bm: {
    BIENVENUE:    "I ni ce! Ne ye HorizonAI ye. Kuma kan jumɛn?",
    IDENTITE:     "I tɔgɔ ye mun ye, ani i ka jamana?",
    VILLE:        "I bɛ sɔrɔ min kɔfɛ?",
    FAMILLE:      "I den dɔnna wa?",
    EDUCATION:    "I tun bɛ kalankɛ wa? Ayi kɔrɔ, a ka se ka dɛmɛ i.",
    COMPETENCES:  "I bɛ se ka baara juman kɛ? Sɔgɔsɔgɔ, jalan, sɛnɛ?",
    OBJECTIFS:    "I bɛ mun kɛ bɔ i bɛ segin?",
    SANTE:        "I ka kɛnɛya bɛ di?",
    CONTACT:      "I bɛ telefɔni dɔ sɔrɔ wa?",
    RECAPITULATIF:"I ni ce! N bɛna i ka seginkɛlɛ seere dilan...",
  },
  ha: {
    BIENVENUE:    "Sannu! Ni ne HorizonAI. Wace harshe?",
    IDENTITE:     "Yaya sunanka da ƙasar da kake fitowa?",
    VILLE:        "Wane birni kake son komawa?",
    FAMILLE:      "Kana da aure? Kana da 'ya'ya?",
    EDUCATION:    "Ka je makaranta? Idan a'a, za mu iya taimaka maka.",
    COMPETENCES:  "Mene ne ka san yi? Noma, sayarwa, dinki?",
    OBJECTIFS:    "Me kake son yi lokacin da ka koma?",
    SANTE:        "Yaya lafiyarka?",
    CONTACT:      "Kana da lambar waya?",
    RECAPITULATIF:"Na gode! Zan shirya shirin dawowarka...",
  },
  ff: {
    BIENVENUE:    "Jam waali! Mi winndii HorizonAI. Ko hol goongi?",
    IDENTITE:     "Ko holɗo togniral maa, e ko hol leydi maa?",
    VILLE:        "Wuro wanɗo kaa yiɗaa ruttude?",
    FAMILLE:      "A woodi ɓiɓɓe?",
    EDUCATION:    "Ndaarii-ɗaa e jangirde? Si alaa, ko wayaani.",
    COMPETENCES:  "Ko holɗo humpitii waɗaade? Lahal, suudu, ligginde?",
    OBJECTIFS:    "Ko holɗo yiɗaa waɗaade?",
    SANTE:        "Ko hol cellal maa?",
    CONTACT:      "A woodi nimero telefon?",
    RECAPITULATIF:"Jaari-ɗaa! Mi ñannoo piyanaa maa...",
  },
  tzm: {
    BIENVENUE:    "Azul! Nkk d HorizonAI. D acu n tutlayt?",
    IDENTITE:     "Acu-t isem-ik, d acu-t tamurt-ik?",
    VILLE:        "Anida tebɣiḍ ad trjedjeḍ?",
    FAMILLE:      "Tesɛiḍ tarwa?",
    EDUCATION:    "Telliḍ s tɣiwant? Ur yelli, nezmer ad k-nεawen.",
    COMPETENCES:  "Acu tessen ad tgaḍ? Aɣrum, tazmart, taɣuri?",
    OBJECTIFS:    "Acu tebɣiḍ ad tgaḍ?",
    SANTE:        "Amek lḥal-ik s teɣzi?",
    CONTACT:      "Tesɛiḍ uṭṭun n tiliɣri?",
    RECAPITULATIF:"Tanmirt! Ad sbeddeɣ asenked-ik tura...",
  },
}

const ETAPES_ORDER = ['BIENVENUE','IDENTITE','VILLE','FAMILLE','EDUCATION','COMPETENCES','OBJECTIFS','SANTE','CONTACT','RECAPITULATIF']

export default function KioskPage() {
  const [lang, setLang]             = useState('fr')
  const [sessionId, setSessionId]   = useState(null)
  const [phase, setPhase]           = useState('IDLE')       // IDLE | SPEAKING | LISTENING | THINKING | DONE | ERROR
  const [currentQ, setCurrentQ]     = useState('')
  const [etape, setEtape]           = useState('BIENVENUE')
  const [stepNum, setStepNum]       = useState(0)
  const [stepTotal, setStepTotal]   = useState(10)
  const [rdvInfo, setRdvInfo]       = useState(null)
  const [offlineQueue, setOfflineQueue] = useState([])

  const speech        = useSpeech(lang)
  const { isOnline, syncNow } = useOfflineSync()
  const autoListenRef = useRef(null)
  const silenceRef    = useRef(null)
  const lastTranscriptRef = useRef('')

  // Démarrer la session au chargement
  useEffect(() => {
    startSession()
    return () => { speech.reset(); clearTimeout(autoListenRef.current); clearTimeout(silenceRef.current) }
  }, [])

  // Auto-écoute après fin de TTS
  useEffect(() => {
    if (phase === 'SPEAKING' && !speech.speaking) {
      autoListenRef.current = setTimeout(() => {
        setPhase('LISTENING')
        speech.startListening()
      }, 800)
    }
  }, [speech.speaking, phase])

  // Détection silence (3s sans nouveau transcript → envoyer)
  useEffect(() => {
    if (phase !== 'LISTENING') return
    clearTimeout(silenceRef.current)
    lastTranscriptRef.current = speech.transcript
    if (speech.transcript) {
      silenceRef.current = setTimeout(() => {
        handleSend(speech.transcript)
      }, 3000)
    }
  }, [speech.transcript, phase])

  const startSession = useCallback(async () => {
    setPhase('THINKING')
    try {
      const res = await interviewApi.start(DEVICE_TOKEN, lang)
      if (res.ok) {
        const d = res.data.data
        setSessionId(d.session_id)
        setStepNum(d.step_num)
        setStepTotal(d.step_total)
        setEtape('BIENVENUE')
        speakQuestion(d.question, 'BIENVENUE')
      } else throw new Error('API error')
    } catch {
      // Mode offline
      const q = FALLBACK_QUESTIONS[lang]?.['BIENVENUE'] || FALLBACK_QUESTIONS.fr['BIENVENUE']
      speakQuestion(q, 'BIENVENUE')
      setSessionId('offline-' + Date.now())
    }
  }, [lang])

  const speakQuestion = (q, step) => {
    setCurrentQ(q)
    setEtape(step)
    setPhase('SPEAKING')
    speech.speak(q)
  }

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
            setTimeout(() => setPhase('DONE'), 4000)
            return
          }
          speakQuestion(d.question, d.etape)
          return
        }
      }

      // Fallback offline — avancer localement
      const idx = ETAPES_ORDER.indexOf(etape)
      const nextEtape = ETAPES_ORDER[idx + 1] || 'RECAPITULATIF'
      const q = FALLBACK_QUESTIONS[lang]?.[nextEtape] || FALLBACK_QUESTIONS.fr[nextEtape] || '...'

      // Sauvegarder localement
      await offlineStore.queueRequest(`/interview/${sid}/step`, 'POST', { message: text })
      setOfflineQueue(q => [...q, text])

      setStepNum(n => Math.min(n + 1, stepTotal))
      if (nextEtape === 'RECAPITULATIF') {
        speakQuestion(q, 'RECAPITULATIF')
        setTimeout(() => setPhase('DONE'), 4000)
      } else {
        speakQuestion(q, nextEtape)
      }

    } catch {
      setPhase('ERROR')
    }
  }, [phase, sessionId, etape, lang, isOnline, speech, stepTotal])

  const restart = () => {
    speech.reset()
    setSessionId(null)
    setPhase('IDLE')
    setCurrentQ('')
    setEtape('BIENVENUE')
    setStepNum(0)
    setRdvInfo(null)
    setOfflineQueue([])
    startSession()
  }

  const changeLang = (l) => {
    setLang(l)
    speech.reset()
    setTimeout(() => startSession(), 100)
  }

  const progress = stepTotal > 0 ? Math.round((stepNum / stepTotal) * 100) : 0

  return (
    <div style={{
      minHeight: '100vh', background: 'linear-gradient(160deg, #0a2040 0%, #0f3460 60%, #1a6fa8 100%)',
      display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
      padding: '40px 24px', fontFamily: 'var(--font-body)',
    }}>
      {/* Header */}
      <motion.div initial={{ opacity: 0, y: -20 }} animate={{ opacity: 1, y: 0 }}
        style={{ textAlign: 'center', marginBottom: 40 }}>
        <div style={{ fontSize: 11, fontWeight: 700, letterSpacing: '.18em', color: 'var(--ocre)', textTransform: 'uppercase', marginBottom: 8 }}>
          OIM Maroc × BAIC — Programme AVRR 2026
        </div>
        <h1 style={{ fontFamily: 'var(--font-display)', fontSize: 32, color: 'white', fontWeight: 700, lineHeight: 1.2, margin: 0 }}>
          HorizonAI — Kiosque
        </h1>
      </motion.div>

      {/* Badge offline */}
      <AnimatePresence>
        {!isOnline && (
          <motion.div initial={{ opacity:0, y:-10 }} animate={{ opacity:1, y:0 }} exit={{ opacity:0 }}
            style={{
              background: 'rgba(196,122,53,.25)', border: '1px solid var(--ocre)',
              borderRadius: 8, padding: '6px 14px', fontSize: 12, color: 'var(--ocre)',
              marginBottom: 20, display: 'flex', alignItems: 'center', gap: 7,
            }}>
            <span>⚡</span> Hors ligne — données sauvegardées localement
          </motion.div>
        )}
      </AnimatePresence>

      {/* Barre de progression */}
      {stepNum > 0 && (
        <div style={{ width: '100%', maxWidth: 540, marginBottom: 28 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
            <span style={{ fontSize: 11, color: 'rgba(255,255,255,.5)' }}>Étape {stepNum}/{stepTotal}</span>
            <span style={{ fontSize: 11, color: 'rgba(255,255,255,.5)' }}>{progress}%</span>
          </div>
          <div style={{ height: 4, background: 'rgba(255,255,255,.15)', borderRadius: 4 }}>
            <motion.div animate={{ width: `${progress}%` }} transition={{ duration: 0.4 }}
              style={{ height: '100%', background: 'var(--ocre)', borderRadius: 4 }} />
          </div>
        </div>
      )}

      {/* Zone principale */}
      <motion.div initial={{ opacity: 0, scale: 0.96 }} animate={{ opacity: 1, scale: 1 }}
        style={{
          background: 'rgba(255,255,255,.06)', backdropFilter: 'blur(12px)',
          border: '1px solid rgba(255,255,255,.12)', borderRadius: 24,
          padding: '40px 44px', maxWidth: 560, width: '100%', textAlign: 'center',
        }}>

        {/* Onde sonore animée */}
        <WaveAnimation phase={phase} />

        {/* Badge TTS fallback (langues sans support audio natif) */}
        {speech.usingFallback && phase !== 'IDLE' && (
          <div style={{
            display: 'inline-flex', alignItems: 'center', gap: 6,
            background: 'rgba(196,122,53,.18)', border: '1px solid rgba(196,122,53,.4)',
            borderRadius: 20, padding: '4px 12px', fontSize: 11,
            color: 'var(--ocre)', marginBottom: 10,
          }}>
            🔊 Audio en français · Texte en {LANGS.find(l => l.code === lang)?.label}
          </div>
        )}

        {/* Texte statut */}
        <div style={{ fontSize: 13, color: 'rgba(255,255,255,.5)', marginBottom: 20, minHeight: 20 }}>
          {phase === 'SPEAKING'   && '🔊 En train de parler...'}
          {phase === 'LISTENING'  && '🎤 En écoute... (parlez maintenant)'}
          {phase === 'THINKING'   && '⏳ Traitement...'}
          {phase === 'DONE'       && '✅ Entretien terminé'}
          {phase === 'ERROR'      && '❌ Erreur de connexion'}
          {phase === 'IDLE'       && 'Démarrage...'}
        </div>

        {/* Question affichée */}
        <AnimatePresence mode="wait">
          <motion.p key={currentQ}
            initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }}
            style={{
              fontSize: 20, fontWeight: 600, color: 'white', lineHeight: 1.5,
              margin: '0 0 28px', minHeight: 80,
            }}>
            {currentQ || 'Initialisation...'}
          </motion.p>
        </AnimatePresence>

        {/* Transcript en direct */}
        {phase === 'LISTENING' && speech.transcript && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }}
            style={{
              background: 'rgba(255,255,255,.08)', borderRadius: 12,
              padding: '12px 16px', marginBottom: 24,
              fontSize: 14, color: 'rgba(255,255,255,.8)', fontStyle: 'italic', lineHeight: 1.5,
            }}>
            « {speech.transcript} »
          </motion.div>
        )}

        {/* RDV affiché en fin */}
        {phase === 'DONE' && rdvInfo && (
          <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}
            style={{
              background: 'rgba(14,159,110,.2)', border: '1px solid rgba(14,159,110,.4)',
              borderRadius: 14, padding: '20px 24px', marginBottom: 24,
            }}>
            <div style={{ fontSize: 28, marginBottom: 8 }}>📅</div>
            <div style={{ fontSize: 16, fontWeight: 700, color: 'white', marginBottom: 4 }}>
              Votre rendez-vous OIM
            </div>
            <div style={{ fontSize: 20, fontWeight: 700, color: 'var(--ocre)', marginBottom: 4 }}>
              {rdvInfo.date}
            </div>
            <div style={{ fontSize: 13, color: 'rgba(255,255,255,.7)' }}>{rdvInfo.lieu}</div>
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
            <KioskBtn onClick={restart} variant="primary">
              🔄 Nouvel entretien
            </KioskBtn>
          )}
        </div>

        {/* Avertissement navigateur incompatible */}
        {!speech.supported && (
          <div style={{ marginTop: 20, fontSize: 12, color: 'rgba(255,255,255,.5)', background: 'rgba(229,57,53,.15)', borderRadius: 8, padding: '8px 12px' }}>
            ⚠️ Votre navigateur ne supporte pas la reconnaissance vocale.<br/>
            Utilisez Google Chrome ou Microsoft Edge pour le mode vocal.
          </div>
        )}
      </motion.div>

      {/* Sélecteur de langue — 2 lignes pour 8 langues */}
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 28, justifyContent: 'center', maxWidth: 600 }}>
        {LANGS.map(l => (
          <motion.button key={l.code} whileTap={{ scale: 0.92 }} onClick={() => changeLang(l.code)}
            title={l.nativeTTS ? 'Audio natif disponible' : 'Audio en français'}
            style={{
              padding: '7px 14px', borderRadius: 20, fontSize: 12, fontWeight: 600, cursor: 'pointer',
              border: `1.5px solid ${lang === l.code ? 'var(--ocre)' : 'rgba(255,255,255,.18)'}`,
              background: lang === l.code ? 'var(--ocre)' : 'rgba(255,255,255,.06)',
              color: 'white',
              display: 'flex', alignItems: 'center', gap: 5,
            }}>
            <span>{l.flag}</span>
            <span>{l.label}</span>
            {!l.nativeTTS && <span style={{ fontSize: 9, opacity: 0.6 }}>🔊FR</span>}
          </motion.button>
        ))}
      </div>

      {/* Footer discret */}
      <div style={{ marginTop: 28, fontSize: 10, color: 'rgba(255,255,255,.25)', textAlign: 'center' }}>
        HorizonAI v1.0 · OIM · Données confidentielles
      </div>
    </div>
  )
}

// ── Onde sonore animée ─────────────────────────────────────────
function WaveAnimation({ phase }) {
  const bars = 9
  return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 5, height: 64, marginBottom: 24 }}>
      {Array.from({ length: bars }).map((_, i) => {
        const isActive = phase === 'SPEAKING' || phase === 'LISTENING'
        return (
          <motion.div key={i}
            animate={isActive ? {
              height: [12, 32 + Math.sin(i) * 20, 12],
              opacity: [0.5, 1, 0.5],
            } : { height: 8, opacity: 0.25 }}
            transition={isActive ? {
              duration: 0.8 + i * 0.08,
              repeat: Infinity,
              ease: 'easeInOut',
              delay: i * 0.06,
            } : { duration: 0.3 }}
            style={{
              width: 6, borderRadius: 4,
              background: phase === 'LISTENING'
                ? 'var(--ocre)'
                : 'rgba(255,255,255,.7)',
            }}
          />
        )
      })}
    </div>
  )
}

// ── Bouton kiosque ─────────────────────────────────────────────
function KioskBtn({ children, onClick, variant = 'ghost' }) {
  return (
    <motion.button whileTap={{ scale: 0.94 }} onClick={onClick}
      style={{
        padding: '12px 24px', borderRadius: 14, fontSize: 15, fontWeight: 700, cursor: 'pointer',
        border: variant === 'primary' ? '2px solid var(--ocre)' : '1.5px solid rgba(255,255,255,.3)',
        background: variant === 'primary' ? 'var(--ocre)' : 'rgba(255,255,255,.08)',
        color: 'white',
      }}>
      {children}
    </motion.button>
  )
}
