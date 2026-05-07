import { AnimatePresence, motion } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'
import { useApp } from '../context/AppContext'
import { chatApi } from '../services/api'

const QUICK_QUESTIONS = [
  'Comment fonctionne le micro-crédit ?',
  'Quels documents dois-je préparer ?',
  'Combien de temps dure la formation ?',
  'Que faire dès mon arrivée au pays ?',
]

export default function ChatPage() {
  const { plan, profile, lang } = useApp()
  const [messages, setMessages] = useState([
    {
      role: 'ai',
      text: 'Bonjour ! Je suis votre assistant HorizonAI. Je connais votre plan de réintégration et peux répondre à toutes vos questions sur le programme AVRR, les démarches à suivre et les ressources disponibles. Comment puis-je vous aider ?',
      time: new Date(),
    }
  ])
  const [input,    setInput]    = useState('')
  const [loading,  setLoading]  = useState(false)
  const messagesEnd = useRef(null)
  const inputRef    = useRef(null)

  useEffect(() => {
    messagesEnd.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  const sendMessage = async (text = input) => {
    const msg = text.trim()
    if (!msg || loading) return

    setInput('')
    setMessages(prev => [...prev, { role: 'user', text: msg, time: new Date() }])
    setLoading(true)

    // Historique pour le backend (format {role, content})
    const history = messages.map(m => ({
      role:    m.role === 'ai' ? 'assistant' : 'user',
      content: m.text,
    }))

    let res
    try {
      res = await chatApi.send(msg, [...history, { role: 'user', content: msg }], lang)
    } catch (err) {
      res = { ok: false, data: { message: err?.message || 'Erreur de communication avec le serveur' } }
    }
    setLoading(false)

    if (res?.ok) {
      setMessages(prev => [...prev, {
        role: 'ai',
        text: res.data.data.message,
        time: new Date(),
        lang: res.data.data.lang,
      }])
    } else {
      const errorText = res.data?.message || res.data?.error || res.data?.message || 'Désolé, une erreur est survenue. Veuillez réessayer.'
      setMessages(prev => [...prev, {
        role: 'ai',
        text: errorText,
        time: new Date(),
        isError: true,
      }])
    }

    inputRef.current?.focus()
  }

  const formatTime = d => d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })

  return (
    <div style={{ padding: 28, display: 'flex', flexDirection: 'column', height: 'calc(100vh - var(--topbar-h))' }}>
      <div style={{ maxWidth: 680, margin: '0 auto', width: '100%', display: 'flex', flexDirection: 'column', height: '100%' }}>

        {/* Header */}
        <div style={{ marginBottom: 18, flexShrink: 0 }}>
          <div style={{ fontSize: 11, fontWeight: 600, letterSpacing: '.12em', textTransform: 'uppercase', color: 'var(--ocre)', marginBottom: 4 }}>
            Assistant IA
          </div>
          <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 22, fontWeight: 700, color: 'var(--blue)', marginBottom: 4 }}>
            Posez vos questions
          </h2>
          <p style={{ fontSize: 12, color: 'var(--muted)' }}>
            Réponses alimentées par Claude Sonnet API · Contexte de votre plan intégré
          </p>
        </div>

        {/* Quick questions */}
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 18, flexShrink: 0 }}>
          {QUICK_QUESTIONS.map(q => (
            <motion.button key={q} whileHover={{ y: -1 }} whileTap={{ scale: 0.97 }}
              onClick={() => sendMessage(q)}
              disabled={loading}
              style={{
                padding: '6px 12px', borderRadius: 20, fontSize: 12,
                border: '1.5px solid var(--border)', background: 'white',
                color: 'var(--mid)', cursor: 'pointer', transition: 'var(--t)',
                fontFamily: 'var(--font-body)',
              }}
            >
              {q}
            </motion.button>
          ))}
        </div>

        {/* Messages */}
        <div style={{
          flex: 1, overflow: 'auto', display: 'flex', flexDirection: 'column',
          gap: 14, paddingBottom: 20,
        }}>
          <AnimatePresence initial={false}>
            {messages.map((msg, i) => (
              <motion.div key={i}
                initial={{ opacity: 0, y: 12, scale: .98 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                style={{
                  display: 'flex', gap: 10, alignItems: 'flex-start',
                  flexDirection: msg.role === 'user' ? 'row-reverse' : 'row',
                }}
              >
                {/* Avatar */}
                <div style={{
                  width: 32, height: 32, borderRadius: '50%', flexShrink: 0,
                  background: msg.role === 'ai' ? 'var(--blue)' : 'var(--ocre)',
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  fontSize: 11, fontWeight: 700, color: 'white',
                }}>
                  {msg.role === 'ai' ? 'AI' : 'M'}
                </div>

                {/* Bulle */}
                <div style={{ maxWidth: '74%' }}>
                  <div style={{
                    padding: '11px 14px', borderRadius: 14, fontSize: 13, lineHeight: 1.6,
                    background: msg.role === 'ai' ? 'white' : 'var(--blue)',
                    color: msg.role === 'ai' ? 'var(--dark)' : 'white',
                    border: msg.role === 'ai' ? '1px solid var(--border)' : 'none',
                    borderBottomLeftRadius: msg.role === 'ai' ? 3 : 14,
                    borderBottomRightRadius: msg.role === 'user' ? 3 : 14,
                    opacity: msg.isError ? 0.7 : 1,
                  }}>
                    {msg.text.split('\n').map((line, j) => (
                      <span key={j}>{line}{j < msg.text.split('\n').length - 1 && <br/>}</span>
                    ))}
                  </div>
                  <div style={{
                    fontSize: 10, color: 'var(--muted)', marginTop: 4,
                    textAlign: msg.role === 'user' ? 'right' : 'left',
                  }}>
                    {formatTime(msg.time)}
                    {msg.lang && msg.lang !== lang && ` · ${msg.lang.toUpperCase()}`}
                  </div>
                </div>
              </motion.div>
            ))}
          </AnimatePresence>

          {/* Typing indicator */}
          <AnimatePresence>
            {loading && (
              <motion.div
                initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }}
                style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}
              >
                <div style={{ width: 32, height: 32, borderRadius: '50%', background: 'var(--blue)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11, fontWeight: 700, color: 'white', flexShrink: 0 }}>
                  AI
                </div>
                <div style={{
                  padding: '12px 16px', borderRadius: '14px 14px 14px 3px',
                  background: 'white', border: '1px solid var(--border)',
                  display: 'flex', gap: 5, alignItems: 'center',
                }}>
                  {[0, 0.2, 0.4].map((delay, i) => (
                    <motion.span key={i}
                      animate={{ y: [0, -5, 0] }}
                      transition={{ duration: 0.9, repeat: Infinity, delay }}
                      style={{ width: 7, height: 7, borderRadius: '50%', background: 'var(--blue-mid)', display: 'block' }}
                    />
                  ))}
                </div>
              </motion.div>
            )}
          </AnimatePresence>

          <div ref={messagesEnd} />
        </div>

        {/* Input */}
        <div style={{
          background: 'white', border: '1px solid var(--border)',
          borderRadius: 'var(--r-lg)', padding: '10px 14px',
          display: 'flex', alignItems: 'flex-end', gap: 10, flexShrink: 0,
          boxShadow: 'var(--shadow-sm)',
        }}>
          <textarea
            ref={inputRef}
            value={input}
            onChange={e => { setInput(e.target.value); e.target.style.height = 'auto'; e.target.style.height = Math.min(e.target.scrollHeight, 110) + 'px' }}
            onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage() } }}
            placeholder="Posez votre question... (Entrée pour envoyer)"
            rows={1}
            style={{
              flex: 1, border: 'none', outline: 'none',
              fontFamily: 'var(--font-body)', fontSize: 13,
              resize: 'none', maxHeight: 110, lineHeight: 1.5,
              background: 'transparent', color: 'var(--dark)',
            }}
          />
          <motion.button
            whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }}
            onClick={() => sendMessage()}
            disabled={loading || !input.trim()}
            style={{
              width: 38, height: 38, borderRadius: 10,
              background: loading || !input.trim() ? 'var(--border)' : 'var(--blue)',
              color: 'white', display: 'flex', alignItems: 'center', justifyContent: 'center',
              cursor: loading || !input.trim() ? 'not-allowed' : 'pointer',
              border: 'none', flexShrink: 0, transition: 'var(--t)',
            }}
          >
            <svg width="15" height="15" fill="none" stroke="white" strokeWidth="2.5" viewBox="0 0 24 24">
              <line x1="22" y1="2" x2="11" y2="13"/>
              <polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
          </motion.button>
        </div>
      </div>
    </div>
  )
}
