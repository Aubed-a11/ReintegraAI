import { AnimatePresence, motion } from 'framer-motion'
import { useState } from 'react'
import toast from 'react-hot-toast'
import { useNavigate } from 'react-router-dom'
import { Button, Input } from '../components/ui'
import { useApp } from '../context/AppContext'

const LANGS = [
  { code: 'fr',  label: 'FR',  name: 'Français'   },
  { code: 'en',  label: 'EN',  name: 'English'    },
  { code: 'ar',  label: 'AR',  name: 'العربية'    },
  { code: 'wo',  label: 'WO',  name: 'Wolof'      },
  { code: 'bm',  label: 'BM',  name: 'Bambara'    },
  { code: 'ha',  label: 'HA',  name: 'Hausa'      },
  { code: 'ff',  label: 'FF',  name: 'Fulfuldé'   },
  { code: 'tzm', label: 'TZM', name: 'Tamazight'  },
]

const SKILLS = ['Agriculture','Commerce','BTP / Construction','Informatique','Couture / Textile','Transport','Santé / Soins','Enseignement','Restauration','Artisanat']

export default function LoginPage() {
  const navigate = useNavigate()
  const { login, register, setLang, lang } = useApp()
  const [mode, setMode] = useState('login')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [firstName, setFirstName] = useState('')
  const [lastName, setLastName] = useState('')
  const [phone, setPhone] = useState('')
  const [age, setAge] = useState('')
  const [gender, setGender] = useState('')
  const [paysOrigine, setPaysOrigine] = useState('')
  const [villeRetour, setVilleRetour] = useState('')
  const [niveauEtudes, setNiveauEtudes] = useState('')
  const [anneesExperience, setAnneesExperience] = useState('')
  const [situationFamiliale, setSituationFamiliale] = useState('')
  const [competences, setCompetences] = useState([])
  const [langue, setLangue] = useState('')
  const [objectifs, setObjectifs] = useState('')
  const [besoins, setBesoins] = useState('')
  const [contraintes, setContraintes] = useState('')
  const [sante, setSante] = useState('')
  const [enfants, setEnfants] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  const handleLogin = async () => {
    if (!email.trim() || !password) { setError('Email et mot de passe requis'); return }
    setError(''); setLoading(true)
    try {
      const res = await login(email.trim(), password, lang)
      setLoading(false)
      if (res.ok) {
        toast.success('Connexion réussie !')
        navigate('/profil')
      } else {
        setError(res.data?.message || 'Email ou mot de passe incorrect')
      }
    } catch (error) {
      setLoading(false)
      setError(error?.message || 'Erreur de connexion au serveur')
    }
  }

  const handleRegister = async () => {
    if (!firstName.trim() || !lastName.trim() || !email.trim() || !password || !confirmPassword || !phone.trim()) {
      setError('Tous les champs obligatoires doivent être remplis'); return
    }
    if (password !== confirmPassword) { setError('Les mots de passe ne correspondent pas'); return }
    if (password.length < 8) { setError('Le mot de passe doit contenir au moins 8 caractères'); return }
    setError(''); setLoading(true)
    try {
      const res = await register({
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        email: email.trim(),
        password,
        phone: phone.trim(),
        age: age ? parseInt(age, 10) : null,
        gender: gender.trim(),
        pays_origine: paysOrigine.trim(),
        ville_retour: villeRetour.trim(),
        niveau_etudes: niveauEtudes.trim(),
        annees_experience: anneesExperience.trim(),
        situation_familiale: situationFamiliale.trim(),
        competences,
        langue: langue.trim(),
        objectifs: objectifs.trim(),
        besoins: besoins.trim(),
        contraintes: contraintes.trim(),
        sante: sante.trim(),
        enfants: enfants ? parseInt(enfants, 10) : null,
      }, lang)
      setLoading(false)
      if (res.ok) {
        toast.success('Inscription réussie !')
        navigate('/profil')
      } else {
        setError(res.data?.message || 'Impossible de créer le compte')
      }
    } catch (error) {
      setLoading(false)
      setError(error?.message || 'Erreur de communication avec le serveur')
    }
  }

  const toggleSkill = skill => {
    setCompetences(current => current.includes(skill)
      ? current.filter(item => item !== skill)
      : [...current, skill]
    )
  }

  return (
    <div style={{
      minHeight: '100vh', display: 'flex',
      background: 'var(--sand)',
    }}>
      <motion.div
        initial={{ opacity: 0, x: -40 }}
        animate={{ opacity: 1, x: 0 }}
        transition={{ duration: .7, ease: 'easeOut' }}
        style={{
          flex: 1,
          background: 'linear-gradient(180deg, #0f4d95 0%, #0b76a4 100%)',
          display: 'flex', flexDirection: 'column',
          justifyContent: 'center', padding: '56px 60px',
          position: 'relative', overflow: 'hidden',
        }}
      >
        <div style={{ position: 'absolute', top: -40, right: -40, width: 320, height: 320, borderRadius: '50%', background: 'rgba(255,255,255,.08)', filter: 'blur(4px)' }} />
        <div style={{ position: 'absolute', bottom: -60, left: -20, width: 240, height: 240, borderRadius: '50%', background: 'rgba(255,255,255,.06)', filter: 'blur(2px)' }} />

        <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 52, position: 'relative' }}>
          <img
            src="/logo.svg"
            alt="HorizonAI"
            style={{ width: 48, height: 48, borderRadius: 12, objectFit: 'contain' }}
          />
          <span style={{ fontFamily: 'var(--font-display)', fontSize: 24, fontWeight: 700, color: '#fff' }}>HorizonAI</span>
        </div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: .3, duration: .6 }}
          style={{ position: 'relative' }}
        >
          <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 42, fontWeight: 700, color: '#fff', lineHeight: 1.12, marginBottom: 20, maxWidth: 440 }}>
            Votre plan de retour,{' '}
            <em style={{ color: 'var(--ocre)', fontStyle: 'normal' }}>
              personnalisé par l'IA
            </em>
          </h2>
          <p style={{ fontSize: 15, color: 'rgba(255,255,255,.6)', lineHeight: 1.7, maxWidth: 380 }}>
            Le programme AVRR de l'OIM utilise l'intelligence artificielle pour construire un plan de réintégration adapté à votre profil, validé par un agent humain.
          </p>
        </motion.div>

        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: .6, duration: .6 }}
          style={{ display: 'flex', gap: 32, marginTop: 52, position: 'relative' }}
        >
          {[['4 axes', 'Emploi · Logement · Finance · Santé'], ['8 langues', 'FR · EN · AR · WO · BM · HA · FF · TZM'], ['100% sécurisé', 'Données anonymisées']].map(([val, label]) => (
            <div key={val}>
              <div style={{ fontFamily: 'var(--font-display)', fontSize: 20, fontWeight: 700, color: 'var(--ocre)', marginBottom: 4 }}>{val}</div>
              <div style={{ fontSize: 11, color: 'rgba(255,255,255,.45)', lineHeight: 1.4 }}>{label}</div>
            </div>
          ))}
        </motion.div>

        <div style={{ position: 'absolute', bottom: 32, left: 64, display: 'flex', gap: 24, alignItems: 'center' }}>
          <div style={{ fontSize: 11, color: 'rgba(255,255,255,.3)', fontWeight: 600, letterSpacing: '.08em', textTransform: 'uppercase' }}>Partenaires</div>
          {['OIM Maroc', 'BAIC'].map(p => (
            <div key={p} style={{ fontSize: 12, color: 'rgba(255,255,255,.5)', background: 'rgba(255,255,255,.07)', padding: '4px 10px', borderRadius: 6 }}>{p}</div>
          ))}
        </div>
      </motion.div>

      <div style={{ width: 520, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '38px 28px' }}>
        <motion.div initial={{ opacity: 0, x: 30 }} animate={{ opacity: 1, x: 0 }} transition={{ duration: .6, ease: 'easeOut' }} style={{ width: '100%', maxWidth: 420, background: 'rgba(255,255,255,0.98)', padding: '38px 32px', borderRadius: 32, border: '1px solid rgba(15,63,114,.08)', boxShadow: '0 26px 70px rgba(15,50,100,.14)' }}>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, marginBottom: 18, justifyContent: 'space-between', alignItems: 'center' }}>
            {LANGS.map(l => (
              <button key={l.code} onClick={() => setLang(l.code)}
                style={{ padding: '5px 12px', borderRadius: 6, fontSize: 11, fontWeight: 600, border: `1px solid ${lang === l.code ? 'var(--blue)' : 'var(--border)'}`, background: lang === l.code ? 'var(--blue)' : 'white', color: lang === l.code ? 'white' : 'var(--mid)', cursor: 'pointer' }}>
                {l.label}
              </button>
            ))}
          </div>

          <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 30, fontWeight: 700, color: '#103b70', marginBottom: 10 }}>Connexion / Inscription</h3>
          <p style={{ fontSize: 14, color: 'rgba(35,55,88,.76)', marginBottom: 24, lineHeight: 1.7 }}>
            Créez un compte rapidement puis complétez votre profil de retour depuis votre espace personnel.
          </p>

          <div style={{ display: 'flex', gap: 10, marginBottom: 20 }}>
            <button onClick={() => { setMode('login'); setError('') }}
              style={{ flex: 1, padding: '12px 18px', borderRadius: 999, border: '1px solid transparent', background: mode === 'login' ? '#0f4d95' : '#f4f7fb', color: mode === 'login' ? '#fff' : '#4b5d79', cursor: 'pointer', fontWeight: 700, transition: 'all .2s ease' }}>
              Connexion
            </button>
            <button onClick={() => { setMode('register'); setError('') }}
              style={{ flex: 1, padding: '12px 18px', borderRadius: 999, border: '1px solid transparent', background: mode === 'register' ? '#0f4d95' : '#f4f7fb', color: mode === 'register' ? '#fff' : '#4b5d79', cursor: 'pointer', fontWeight: 700, transition: 'all .2s ease' }}>
              Inscription
            </button>
          </div>

          <AnimatePresence mode="wait">
            <motion.div key={mode} initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -12 }}>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                {mode === 'login' ? (
                  <>
                    <Input label="Email" type="email" placeholder="email@exemple.com" value={email} onChange={e => { setEmail(e.target.value); setError('') }} error={error} />
                    <Input label="Mot de passe" type="password" placeholder="••••••••" value={password} onChange={e => { setPassword(e.target.value); setError('') }} error={error} />
                    <Button variant="primary" style={{ width: '100%', justifyContent: 'center', borderRadius: 999, padding: '14px 0', boxShadow: '0 14px 30px rgba(15,77,146,.12)' }} loading={loading} onClick={handleLogin}>
                      Se connecter
                    </Button>
                  </>
                ) : (
                  <>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                      <Input label="Prénom" value={firstName} onChange={e => { setFirstName(e.target.value); setError('') }} />
                      <Input label="Nom" value={lastName} onChange={e => { setLastName(e.target.value); setError('') }} />
                    </div>
                    <Input label="Email" type="email" placeholder="email@exemple.com" value={email} onChange={e => { setEmail(e.target.value); setError('') }} />
                    <Input label="Téléphone" type="tel" placeholder="+221 77 123 45 67" value={phone} onChange={e => { setPhone(e.target.value); setError('') }} />
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                      <Input label="Mot de passe" type="password" placeholder="••••••••" value={password} onChange={e => { setPassword(e.target.value); setError('') }} />
                      <Input label="Confirmer le mot de passe" type="password" placeholder="••••••••" value={confirmPassword} onChange={e => { setConfirmPassword(e.target.value); setError('') }} />
                    </div>

                    <div style={{ padding: '16px', borderRadius: 18, background: 'rgba(14,159,110,.06)', border: '1px solid rgba(14,159,110,.12)', color: 'var(--dark)', fontSize: 13, lineHeight: 1.6 }}>
                      <div style={{ fontWeight: 700, marginBottom: 8 }}>Informations facultatives</div>
                      Complétez votre profil plus tard depuis l’espace profil pour bénéficier d’un plan plus précis.
                    </div>

                    <Button variant="primary" style={{ width: '100%', justifyContent: 'center', borderRadius: 999, padding: '14px 0', boxShadow: '0 14px 30px rgba(15,77,146,.12)' }} loading={loading} onClick={handleRegister}>
                      Créer mon compte
                    </Button>
                  </>
                )}
              </div>
            </motion.div>
          </AnimatePresence>

          {error && <div style={{ marginTop: 18, color: 'var(--red)', fontSize: 13 }}>{error}</div>}
        </motion.div>
      </div>
    </div>
  )
}
