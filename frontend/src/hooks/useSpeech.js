import { useCallback, useEffect, useRef, useState } from 'react'

// Langues avec support TTS natif dans Chrome/Edge/Safari
const NATIVE_TTS_LANGS = {
  fr:  'fr-FR',
  en:  'en-US',
  ar:  'ar-MA',   // Arabe marocain
}

// Pour les langues sans TTS natif : afficher texte local + lire en français
// wo=Wolof, bm=Bambara, ha=Hausa, ff=Fula, tzm=Tamazight
const FALLBACK_TTS = {
  wo:  'fr-FR',
  bm:  'fr-FR',
  ha:  'fr-FR',
  ff:  'fr-FR',
  tzm: 'fr-FR',
}

// Code STT pour la reconnaissance vocale (ce que le navigateur comprend)
const STT_LANG = {
  fr:  'fr-FR',
  en:  'en-US',
  ar:  'ar-MA',
  wo:  'fr-SN',   // Wolof → reconnaissance en sénégalais (fr-SN, approche courante)
  bm:  'fr-FR',   // Bambara → reconnaître en français
  ha:  'fr-FR',   // Hausa → reconnaître en français
  ff:  'fr-FR',   // Fula → reconnaître en français
  tzm: 'fr-FR',   // Tamazight → reconnaître en français
}

export function useSpeech(lang = 'fr') {
  const [listening, setListening]     = useState(false)
  const [speaking, setSpeaking]       = useState(false)
  const [transcript, setTranscript]   = useState('')
  const [supported, setSupported]     = useState(false)
  const [usingFallback, setFallback]  = useState(false)

  const recognitionRef = useRef(null)
  const langCode       = NATIVE_TTS_LANGS[lang] ?? null
  const fallbackCode   = FALLBACK_TTS[lang] ?? 'fr-FR'
  const sttCode        = STT_LANG[lang] ?? 'fr-FR'

  // true si la langue n'a pas de TTS natif
  const needsFallback = !langCode

  useEffect(() => {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition
    setSupported(!!SR && !!window.speechSynthesis)
    setFallback(needsFallback)
  }, [needsFallback])

  // ── TTS : lit un texte à voix haute ──────────────────────────
  // frText = traduction française (optionnel, utilisée si fallback actif)
  const speak = useCallback((text, frText = null) => {
    if (!window.speechSynthesis || !text) return
    window.speechSynthesis.cancel()

    const ttsLang   = langCode ?? fallbackCode
    const ttsText   = (needsFallback && frText) ? frText : text

    const utt = new SpeechSynthesisUtterance(ttsText)
    utt.lang  = ttsLang
    utt.rate  = 0.9
    utt.pitch = 1.05

    // Choisir la meilleure voix disponible
    const voices    = window.speechSynthesis.getVoices()
    const baseLang  = ttsLang.split('-')[0]
    const preferred = voices.find(v => v.lang === ttsLang)
      || voices.find(v => v.lang.startsWith(baseLang))
      || voices[0]
    if (preferred) utt.voice = preferred

    utt.onstart = () => setSpeaking(true)
    utt.onend   = () => setSpeaking(false)
    utt.onerror = () => setSpeaking(false)

    window.speechSynthesis.speak(utt)
  }, [langCode, fallbackCode, needsFallback])

  const stopSpeaking = useCallback(() => {
    window.speechSynthesis?.cancel()
    setSpeaking(false)
  }, [])

  // ── STT : reconnaissance vocale ───────────────────────────────
  const startListening = useCallback(() => {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition
    if (!SR) return
    stopSpeaking()

    try { recognitionRef.current?.stop() } catch (_) {}

    const recognition         = new SR()
    recognition.lang          = sttCode
    recognition.continuous    = false
    recognition.interimResults = true
    recognition.maxAlternatives = 1

    recognition.onstart  = () => { setListening(true); setTranscript('') }
    recognition.onend    = () => setListening(false)
    recognition.onerror  = () => setListening(false)
    recognition.onresult = (e) => {
      let interim = '', final = ''
      for (let i = e.resultIndex; i < e.results.length; i++) {
        const t = e.results[i][0].transcript
        if (e.results[i].isFinal) final += t
        else interim += t
      }
      setTranscript(final || interim)
    }

    recognitionRef.current = recognition
    recognition.start()
  }, [sttCode, stopSpeaking])

  const stopListening = useCallback(() => {
    try { recognitionRef.current?.stop() } catch (_) {}
    setListening(false)
    return transcript
  }, [transcript])

  const reset = useCallback(() => {
    stopSpeaking()
    try { recognitionRef.current?.stop() } catch (_) {}
    setListening(false)
    setTranscript('')
  }, [stopSpeaking])

  useEffect(() => () => {
    try { recognitionRef.current?.stop() } catch (_) {}
    window.speechSynthesis?.cancel()
  }, [])

  return {
    listening, speaking, transcript, supported, usingFallback,
    speak, stopSpeaking, startListening, stopListening, reset,
  }
}
