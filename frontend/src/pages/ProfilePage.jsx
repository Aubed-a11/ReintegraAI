import { motion } from 'framer-motion'
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Button, Card, SectionHeader } from '../components/ui'
import { useApp } from '../context/AppContext'

const PAYS = ['Sénégal','Côte d\'Ivoire','Niger','Mali','Guinée','Burkina Faso','Cameroun','Togo','Bénin','Mauritanie']
const AGES = ['18-24','25-34','35-44','45-54','55+']
const ETUDES = ['Sans diplôme','Primaire','Collège','Bac','Bac +2','Bac +3','Bac +5','Doctorat']
const EXPS = ['Moins de 2 ans','2-5 ans','5-10 ans','Plus de 10 ans']
const FAMILLES = ['Célibataire','Marié(e)','Avec enfants à charge']
const SKILLS = ['Agriculture','Commerce','BTP / Construction','Informatique','Couture / Textile','Transport','Santé / Soins','Enseignement','Restauration','Artisanat']
const VULNS = [
  { value: 'SANTE_CHRONIQUE',        label: 'Problème de santé chronique' },
  { value: 'FEMME_ENCEINTE',         label: 'Femme enceinte ou allaitante' },
  { value: 'MINEUR_NON_ACCOMPAGNE',  label: 'Mineur non accompagné' },
  { value: 'VICTIME_TRAITE',         label: 'Victime de traite ou violence' },
  { value: 'HANDICAP',               label: 'Situation de handicap' },
  { value: 'SANTE_URGENTE',          label: 'Prise en charge médicale urgente' },
]

const STEP_LABELS = ['Inscription', 'Profil', 'Plan IA', 'Validation']

export default function ProfilePage() {
  const { profile, saveProfile, loading } = useApp()
  const navigate = useNavigate()

  const [form, setForm] = useState({
    pays_origine: '', ville_retour: '', tranche_age: '',
    situation_familiale: '', niveau_etudes: '', annees_experience: '',
    langue: '', objectifs: '', besoins: '', contraintes: '', sante: '',
    enfants: '', competences: [], vulnerabilites: [],
    alphabetisation: 'OUI', competences_informelles: '',
  })

  const isAnalphabete = form.alphabetisation === 'NON'
  const isPartiel     = form.alphabetisation === 'PARTIEL'

  // Pré-remplir depuis le store
  useEffect(() => {
    if (profile) {
      setForm({
        pays_origine:             profile.pays_origine             || '',
        ville_retour:             profile.ville_retour             || '',
        tranche_age:              profile.tranche_age              || '',
        situation_familiale:      profile.situation_familiale      || '',
        niveau_etudes:            profile.niveau_etudes            || '',
        annees_experience:        profile.annees_experience        || '',
        langue:                   profile.langue                   || '',
        objectifs:                profile.objectifs                || '',
        besoins:                  profile.besoins                  || '',
        contraintes:              profile.contraintes              || '',
        sante:                    profile.sante                    || '',
        enfants:                  profile.enfants                 ?? '',
        competences:              Array.isArray(profile.competences)    ? profile.competences    : [],
        vulnerabilites:           Array.isArray(profile.vulnerabilites) ? profile.vulnerabilites : [],
        alphabetisation:          profile.alphabetisation          || 'OUI',
        competences_informelles:  profile.competences_informelles  || '',
      })
    }
  }, [profile])

  const set = (key, val) => setForm(f => ({ ...f, [key]: val }))

  const toggleSkill = skill => {
    setForm(f => ({
      ...f,
      competences: f.competences.includes(skill)
        ? f.competences.filter(s => s !== skill)
        : [...f.competences, skill],
    }))
  }

  const toggleVuln = val => {
    setForm(f => ({
      ...f,
      vulnerabilites: f.vulnerabilites.includes(val)
        ? f.vulnerabilites.filter(v => v !== val)
        : [...f.vulnerabilites, val],
    }))
  }

  const handleSave = async (andGenerate = false) => {
    const res = await saveProfile(form)
    if (res?.ok && andGenerate) navigate('/plan')
  }

  return (
    <div style={{ padding: 28 }}>
      <div style={{ maxWidth: 740, margin: '0 auto' }}>
        <SectionHeader
          eyebrow="Mon Profil"
          title="Votre profil complet"
          subtitle="Ces informations permettent à l'IA de générer un plan adapté. Toutes les données sont anonymisées avant traitement Claude API."
        />

        {/* Stepper */}
        <div style={{ display: 'flex', alignItems: 'center', background: 'white', border: '1px solid var(--border)', borderRadius: 'var(--r)', padding: '18px 24px', marginBottom: 24 }}>
          {STEP_LABELS.map((label, i) => (
            <div key={i} style={{ display: 'flex', alignItems: 'center', flex: i < STEP_LABELS.length - 1 ? 1 : 0 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <div style={{
                  width: 30, height: 30, borderRadius: '50%',
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  fontSize: 11, fontWeight: 700,
                  background: i === 0 ? 'var(--green)' : i === 1 ? 'var(--blue)' : 'var(--sand)',
                  color: i <= 1 ? 'white' : 'var(--muted)',
                  boxShadow: i === 1 ? '0 0 0 4px var(--blue-light)' : 'none',
                }}>
                  {i === 0 ? '✓' : i + 1}
                </div>
                <span style={{ fontSize: 12, fontWeight: i === 1 ? 600 : 400, color: i === 1 ? 'var(--blue)' : 'var(--mid)' }}>{label}</span>
              </div>
              {i < STEP_LABELS.length - 1 && (
                <div style={{ flex: 1, height: 1, background: i === 0 ? 'var(--green)' : 'var(--border)', margin: '0 10px' }} />
              )}
            </div>
          ))}
        </div>

        {/* Section 1: Infos personnelles */}
        <Card style={{ padding: 28, marginBottom: 18 }} hover={false}>
          <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--blue)', marginBottom: 18, paddingBottom: 10, borderBottom: '1px solid var(--border)', display: 'flex', alignItems: 'center', gap: 7 }}>
            <span>👤</span> Informations personnelles
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
            <Field label="Pays d'origine *">
              <select style={selectStyle} value={form.pays_origine} onChange={e => set('pays_origine', e.target.value)}>
                <option value="">Sélectionner...</option>
                {PAYS.map(p => <option key={p}>{p}</option>)}
              </select>
            </Field>
            <Field label="Ville de retour *">
              <input style={inputStyle} placeholder="Ex: Dakar, Abidjan..." value={form.ville_retour} onChange={e => set('ville_retour', e.target.value)} />
            </Field>
            <Field label="Tranche d'âge *">
              <select style={selectStyle} value={form.tranche_age} onChange={e => set('tranche_age', e.target.value)}>
                <option value="">Sélectionner...</option>
                {AGES.map(a => <option key={a} value={a}>{a} ans</option>)}
              </select>
            </Field>
            <Field label="Situation familiale">
              <select style={selectStyle} value={form.situation_familiale} onChange={e => set('situation_familiale', e.target.value)}>
                <option value="">—</option>
                {FAMILLES.map(f => <option key={f}>{f}</option>)}
              </select>
            </Field>
          </div>
        </Card>

        {/* Section 2: Compétences */}
        <Card style={{ padding: 28, marginBottom: 18 }} hover={false}>
          <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--blue)', marginBottom: 18, paddingBottom: 10, borderBottom: '1px solid var(--border)', display: 'flex', alignItems: 'center', gap: 7 }}>
            <span>🎓</span> Compétences & Formation
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 18 }}>
            <Field label="Niveau d'études *">
              <select style={selectStyle} value={form.niveau_etudes} onChange={e => set('niveau_etudes', e.target.value)}>
                <option value="">Sélectionner...</option>
                {ETUDES.map(e => <option key={e}>{e}</option>)}
              </select>
            </Field>
            <Field label="Années d'expérience">
              <select style={selectStyle} value={form.annees_experience} onChange={e => set('annees_experience', e.target.value)}>
                <option value="">—</option>
                {EXPS.map(e => <option key={e}>{e}</option>)}
              </select>
            </Field>
          </div>
          {/* Alphabétisation */}
          <Field label="Niveau d'alphabétisation *">
            <select style={selectStyle} value={form.alphabetisation} onChange={e => set('alphabetisation', e.target.value)}>
              <option value="OUI">Je sais lire et écrire</option>
              <option value="PARTIEL">Je comprends mais j'écris difficilement</option>
              <option value="NON">Je ne sais ni lire ni écrire</option>
            </select>
          </Field>

          {/* Bannière info analphabète */}
          {(isAnalphabete || isPartiel) && (
            <motion.div initial={{ opacity: 0, y: -8 }} animate={{ opacity: 1, y: 0 }}
              style={{
                background: 'var(--blue-light)', border: '1.5px solid var(--blue-mid)',
                borderRadius: 10, padding: '12px 16px', display: 'flex', gap: 10, alignItems: 'flex-start',
              }}>
              <span style={{ fontSize: 20 }}>💡</span>
              <div>
                <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--blue)', marginBottom: 3 }}>
                  L'IA adapte votre plan même sans diplôme
                </div>
                <div style={{ fontSize: 11, color: 'var(--blue-mid)', lineHeight: 1.6 }}>
                  HorizonAI analyse les réalités économiques de votre pays de retour et propose un plan en 2 phases : intégration immédiate dans l'économie locale, puis formation pratique gratuite.
                </div>
              </div>
            </motion.div>
          )}

          {/* Compétences formelles (masquées si analphabète total) */}
          {!isAnalphabete && (
            <Field label={isPartiel ? 'Domaines de compétences (optionnel)' : 'Domaines de compétences *'}>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, padding: 12, background: 'var(--sand)', borderRadius: 9, border: '1.5px solid var(--border)' }}>
                {SKILLS.map(skill => (
                  <motion.button key={skill} whileTap={{ scale: 0.96 }}
                    onClick={() => toggleSkill(skill)}
                    style={{
                      padding: '6px 12px', borderRadius: 20, fontSize: 12, fontWeight: 600,
                      cursor: 'pointer', transition: 'var(--t)',
                      border: `1.5px solid ${form.competences.includes(skill) ? 'var(--blue)' : 'var(--border)'}`,
                      background: form.competences.includes(skill) ? 'var(--blue)' : 'white',
                      color: form.competences.includes(skill) ? 'white' : 'var(--mid)',
                    }}>
                    {skill}
                  </motion.button>
                ))}
              </div>
            </Field>
          )}

          {/* Compétences informelles (si analphabète ou partiel) */}
          {(isAnalphabete || isPartiel) && (
            <Field label={isAnalphabete ? 'Savoir-faire — décrivez ce que vous savez faire *' : 'Savoir-faire complémentaire'}>
              <textarea
                style={{ ...inputStyle, minHeight: 100 }}
                placeholder="Ex : Je sais élever des poulets, cultiver des légumes, faire de la couture, vendre au marché..."
                value={form.competences_informelles}
                onChange={e => set('competences_informelles', e.target.value)}
              />
              <span style={{ fontSize: 10, color: 'var(--muted)', marginTop: 3 }}>
                Décrivez simplement avec vos propres mots — l'IA comprendra
              </span>
            </Field>
          )}
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginTop: 20, marginBottom: 18 }}>
            <Field label="Langue">
              <input style={inputStyle} value={form.langue} onChange={e => set('langue', e.target.value)} />
            </Field>
            <Field label="Nombre d'enfants">
              <input style={inputStyle} type="number" min="0" value={form.enfants} onChange={e => set('enfants', e.target.value)} />
            </Field>
          </div>
          <Field label="Objectifs">
            <textarea style={{ ...inputStyle, minHeight: 90 }} value={form.objectifs} onChange={e => set('objectifs', e.target.value)} />
          </Field>
          <Field label="Besoins">
            <textarea style={{ ...inputStyle, minHeight: 90 }} value={form.besoins} onChange={e => set('besoins', e.target.value)} />
          </Field>
          <Field label="Contraintes">
            <textarea style={{ ...inputStyle, minHeight: 90 }} value={form.contraintes} onChange={e => set('contraintes', e.target.value)} />
          </Field>
          <Field label="Santé">
            <textarea style={{ ...inputStyle, minHeight: 90 }} value={form.sante} onChange={e => set('sante', e.target.value)} />
          </Field>
        </Card>

        {/* Section 3: Vulnérabilités */}
        <Card style={{ padding: 28, marginBottom: 18 }} hover={false}>
          <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--blue)', marginBottom: 18, paddingBottom: 10, borderBottom: '1px solid var(--border)', display: 'flex', alignItems: 'center', gap: 7 }}>
            <span>❤️</span> Vulnérabilités <span style={{ fontSize: 11, color: 'var(--muted)', fontWeight: 400, marginLeft: 6 }}>(optionnel — confidentiel)</span>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
            {VULNS.map(v => (
              <motion.label key={v.value} whileTap={{ scale: 0.98 }}
                style={{
                  display: 'flex', alignItems: 'center', gap: 10,
                  padding: '10px 14px', borderRadius: 9, cursor: 'pointer',
                  border: `1.5px solid ${form.vulnerabilites.includes(v.value) ? 'var(--blue-mid)' : 'var(--border)'}`,
                  background: form.vulnerabilites.includes(v.value) ? 'var(--blue-light)' : 'var(--sand)',
                  fontSize: 12, transition: 'var(--t)',
                }}>
                <input type="checkbox" checked={form.vulnerabilites.includes(v.value)}
                  onChange={() => toggleVuln(v.value)}
                  style={{ accentColor: 'var(--blue)', width: 14, height: 14 }} />
                {v.label}
              </motion.label>
            ))}
          </div>
        </Card>

        {/* Footer actions */}
        <div style={{ background: 'white', border: '1px solid var(--border)', borderRadius: 'var(--r)', padding: '18px 28px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 11, color: 'var(--muted)' }}>
            <svg width="14" height="14" fill="none" stroke="var(--green)" strokeWidth="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Données anonymisées — Loi n°09-08 (Maroc)
          </div>
          <div style={{ display: 'flex', gap: 10 }}>
            <Button variant="muted" size="sm" loading={loading.profile} onClick={() => handleSave(false)}>
              Sauvegarder
            </Button>
            <Button variant="primary" size="sm" loading={loading.profile} onClick={() => handleSave(true)}>
              Générer mon plan →
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}

// ── Sous-composants ───────────────────────────────────────────
function Field({ label, children }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 5 }}>
      <label style={{ fontSize: 11, fontWeight: 600, color: 'var(--mid)', textTransform: 'uppercase', letterSpacing: '.06em' }}>{label}</label>
      {children}
    </div>
  )
}

const inputStyle = {
  padding: '11px 13px', border: '1.5px solid var(--border)', borderRadius: 9,
  fontSize: 13, color: 'var(--dark)', background: 'var(--sand)',
  outline: 'none', width: '100%', fontFamily: 'var(--font-body)',
}
const selectStyle = { ...inputStyle, cursor: 'pointer' }
