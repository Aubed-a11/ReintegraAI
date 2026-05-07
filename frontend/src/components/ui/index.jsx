import { motion } from 'framer-motion'
import clsx from 'clsx'

// ── Button ────────────────────────────────────────────────────
const btnBase = {
  display: 'inline-flex', alignItems: 'center', gap: 8,
  padding: '11px 22px', borderRadius: 10,
  fontFamily: 'var(--font-body)', fontSize: 13, fontWeight: 600,
  cursor: 'pointer', border: 'none', transition: 'var(--t)',
  outline: 'none', whiteSpace: 'nowrap',
}
const btnVariants = {
  primary: { background: 'var(--ocre)', color: '#fff', boxShadow: '0 4px 18px rgba(196,122,53,.32)' },
  blue:    { background: 'var(--blue)', color: '#fff', boxShadow: '0 4px 16px rgba(15,52,96,.22)' },
  ghost:   { background: 'rgba(255,255,255,.1)', color: '#fff', border: '1px solid rgba(255,255,255,.2)' },
  outline: { background: '#fff', color: 'var(--blue)', border: '1.5px solid var(--blue)' },
  danger:  { background: '#fff', color: '#e53935', border: '1.5px solid #e53935' },
  muted:   { background: 'var(--sand)', color: 'var(--mid)', border: '1.5px solid var(--border)' },
}
const btnSizes = {
  sm:  { padding: '8px 14px', fontSize: 12 },
  md:  {},
  lg:  { padding: '14px 28px', fontSize: 15 },
  icon:{ padding: '9px', borderRadius: 9 },
}

export function Button({ variant='primary', size='md', disabled, loading, children, style, ...props }) {
  return (
    <motion.button
      style={{
        ...btnBase,
        ...btnVariants[variant],
        ...btnSizes[size],
        opacity: (disabled || loading) ? 0.6 : 1,
        cursor: (disabled || loading) ? 'not-allowed' : 'pointer',
        ...style,
      }}
      whileHover={!disabled && !loading ? { y: -1, filter: 'brightness(1.05)' } : {}}
      whileTap={!disabled && !loading ? { y: 0, scale: 0.98 } : {}}
      disabled={disabled || loading}
      {...props}
    >
      {loading ? <Spinner size={14} /> : children}
    </motion.button>
  )
}

// ── Spinner ───────────────────────────────────────────────────
export function Spinner({ size = 18, color = 'currentColor' }) {
  return (
    <motion.span
      animate={{ rotate: 360 }}
      transition={{ duration: 0.7, repeat: Infinity, ease: 'linear' }}
      style={{
        display: 'inline-block', width: size, height: size,
        border: `2px solid rgba(255,255,255,.3)`,
        borderTopColor: color === 'currentColor' ? 'white' : color,
        borderRadius: '50%', flexShrink: 0,
      }}
    />
  )
}

// ── Card ──────────────────────────────────────────────────────
export function Card({ children, style, hover = true, accent, ...props }) {
  return (
    <motion.div
      style={{
        background: 'var(--white)',
        border: '1px solid var(--border)',
        borderRadius: 'var(--r-lg)',
        overflow: 'hidden',
        borderLeft: accent ? `3px solid ${accent}` : undefined,
        ...style,
      }}
      whileHover={hover ? { y: -2, boxShadow: 'var(--shadow)' } : {}}
      transition={{ duration: 0.2 }}
      {...props}
    >
      {children}
    </motion.div>
  )
}

// ── Tag / Badge ───────────────────────────────────────────────
const tagColors = {
  blue:   { bg: 'var(--blue-light)',  color: 'var(--blue-mid)' },
  green:  { bg: 'var(--green-light)', color: '#086040' },
  ocre:   { bg: 'var(--ocre-light)',  color: '#8a4a10' },
  red:    { bg: 'var(--red-light)',   color: '#b71c1c' },
  gray:   { bg: 'var(--sand)',        color: 'var(--muted)' },
  dark:   { bg: 'var(--dark)',        color: '#fff' },
}

export function Tag({ variant='blue', children, style }) {
  const c = tagColors[variant] || tagColors.blue
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 4,
      padding: '3px 10px', borderRadius: 20,
      fontSize: 11, fontWeight: 600,
      background: c.bg, color: c.color,
      ...style,
    }}>
      {children}
    </span>
  )
}

// ── Input ─────────────────────────────────────────────────────
export function Input({ label, error, style, containerStyle, ...props }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 5, ...containerStyle }}>
      {label && (
        <label style={{ fontSize: 11, fontWeight: 600, color: 'var(--mid)', textTransform: 'uppercase', letterSpacing: '.06em' }}>
          {label}
        </label>
      )}
      <input
        style={{
          padding: '11px 13px',
          border: `1.5px solid ${error ? 'var(--red)' : 'var(--border)'}`,
          borderRadius: 9,
          fontSize: 13, color: 'var(--dark)',
          background: 'var(--sand)',
          outline: 'none',
          transition: 'var(--t)',
          width: '100%',
          ...style,
        }}
        onFocus={e => { e.target.style.borderColor = 'var(--blue-mid)'; e.target.style.background = 'white'; e.target.style.boxShadow = '0 0 0 3px var(--blue-light)' }}
        onBlur={e => { e.target.style.borderColor = error ? 'var(--red)' : 'var(--border)'; e.target.style.background = 'var(--sand)'; e.target.style.boxShadow = 'none' }}
        {...props}
      />
      {error && <span style={{ fontSize: 11, color: 'var(--red)' }}>{error}</span>}
    </div>
  )
}

// ── Select ────────────────────────────────────────────────────
export function Select({ label, error, options = [], containerStyle, ...props }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 5, ...containerStyle }}>
      {label && (
        <label style={{ fontSize: 11, fontWeight: 600, color: 'var(--mid)', textTransform: 'uppercase', letterSpacing: '.06em' }}>
          {label}
        </label>
      )}
      <select
        style={{
          padding: '11px 13px', border: '1.5px solid var(--border)',
          borderRadius: 9, fontSize: 13, color: 'var(--dark)',
          background: 'var(--sand)', outline: 'none',
          cursor: 'pointer', width: '100%',
        }}
        {...props}
      >
        {options.map(o => (
          <option key={o.value} value={o.value}>{o.label}</option>
        ))}
      </select>
    </div>
  )
}

// ── Status Pill ───────────────────────────────────────────────
const statusMap = {
  PENDING:      { label: 'En attente',  color: '#f9a825', bg: '#fff8e1', text: '#8a5e00' },
  UNDER_REVIEW: { label: 'En révision', color: '#1a6fa8', bg: '#e6f1fb', text: '#0c3e6e' },
  VALIDATED:    { label: 'Validé',      color: '#0e9f6e', bg: '#eaf3de', text: '#086040' },
  REJECTED:     { label: 'Refusé',      color: '#e53935', bg: '#fdecea', text: '#b71c1c' },
}

export function StatusPill({ status }) {
  const s = statusMap[status] || statusMap.PENDING
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 5,
      padding: '3px 10px', borderRadius: 20,
      fontSize: 11, fontWeight: 600,
      background: s.bg, color: s.text,
    }}>
      <span style={{ width: 6, height: 6, borderRadius: '50%', background: s.color, flexShrink: 0 }} />
      {s.label}
    </span>
  )
}

// ── Score Ring ────────────────────────────────────────────────
export function ScoreRing({ score = 0, size = 90 }) {
  const r = size * 0.38
  const circumference = 2 * Math.PI * r
  const offset = circumference - (score / 100) * circumference
  const color = score >= 75 ? 'var(--green)' : score >= 50 ? 'var(--ocre)' : 'var(--red)'

  return (
    <div style={{ position: 'relative', width: size, height: size, flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} style={{ transform: 'rotate(-90deg)' }}>
        <circle cx={size/2} cy={size/2} r={r} fill="none" stroke="var(--border)" strokeWidth={size*0.07} />
        <motion.circle
          cx={size/2} cy={size/2} r={r} fill="none"
          stroke={color} strokeWidth={size*0.07}
          strokeLinecap="round"
          strokeDasharray={circumference}
          initial={{ strokeDashoffset: circumference }}
          animate={{ strokeDashoffset: offset }}
          transition={{ duration: 1.2, ease: 'easeOut' }}
        />
      </svg>
      <div style={{ position: 'absolute', textAlign: 'center' }}>
        <div style={{ fontFamily: 'var(--font-display)', fontSize: size * 0.22, fontWeight: 700, color: 'var(--blue)', lineHeight: 1 }}>{score}</div>
        <div style={{ fontSize: size * 0.1, color: 'var(--muted)', textTransform: 'uppercase', letterSpacing: '.06em', marginTop: 2 }}>score</div>
      </div>
    </div>
  )
}

// ── Progress Steps (sidebar) ───────────────────────────────────
export function ProgressSteps({ steps }) {
  return (
    <div>
      {steps.map((step, i) => (
        <div key={i} style={{ display: 'flex', alignItems: 'flex-start', gap: 10, marginBottom: 12, position: 'relative' }}>
          {i < steps.length - 1 && (
            <div style={{ position: 'absolute', left: 9, top: 22, width: 1, height: 'calc(100% + 2px)', background: 'rgba(255,255,255,.1)' }} />
          )}
          <div style={{
            width: 20, height: 20, borderRadius: '50%', flexShrink: 0,
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: 9, fontWeight: 700, position: 'relative', zIndex: 1,
            background: step.status === 'done' ? 'var(--green)' : step.status === 'active' ? 'var(--ocre)' : 'rgba(255,255,255,.08)',
            color: step.status === 'todo' ? 'rgba(255,255,255,.25)' : '#fff',
          }}>
            {step.status === 'done' ? '✓' : i + 1}
          </div>
          <div style={{ fontSize: 11, color: 'rgba(255,255,255,.5)', lineHeight: 1.4 }}>
            <strong style={{ display: 'block', color: 'rgba(255,255,255,.8)', fontWeight: 500 }}>{step.label}</strong>
            {step.sublabel}
          </div>
        </div>
      ))}
    </div>
  )
}

// ── Section Header ────────────────────────────────────────────
export function SectionHeader({ eyebrow, title, subtitle }) {
  return (
    <div style={{ marginBottom: 28 }}>
      {eyebrow && <div style={{ fontSize: 11, fontWeight: 600, letterSpacing: '.12em', textTransform: 'uppercase', color: 'var(--ocre)', marginBottom: 8 }}>{eyebrow}</div>}
      <h2 style={{ fontFamily: 'var(--font-display)', fontSize: 28, fontWeight: 700, color: 'var(--blue)', marginBottom: 6 }}>{title}</h2>
      {subtitle && <p style={{ fontSize: 13, color: 'var(--muted)', lineHeight: 1.6 }}>{subtitle}</p>}
    </div>
  )
}

// ── Divider ───────────────────────────────────────────────────
export function Divider({ style }) {
  return <div style={{ height: 1, background: 'var(--border)', ...style }} />
}

// ── Empty State ───────────────────────────────────────────────
export function EmptyState({ icon, title, subtitle, action }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      style={{ textAlign: 'center', padding: '64px 32px' }}
    >
      {icon && <div style={{ fontSize: 40, marginBottom: 16 }}>{icon}</div>}
      <h3 style={{ fontFamily: 'var(--font-display)', fontSize: 18, color: 'var(--blue)', marginBottom: 8 }}>{title}</h3>
      {subtitle && <p style={{ fontSize: 13, color: 'var(--muted)', marginBottom: 22, lineHeight: 1.6 }}>{subtitle}</p>}
      {action}
    </motion.div>
  )
}

// ── Skeleton loader ───────────────────────────────────────────
export function Skeleton({ width = '100%', height = 16, style }) {
  return (
    <motion.div
      animate={{ opacity: [0.4, 0.8, 0.4] }}
      transition={{ duration: 1.5, repeat: Infinity }}
      style={{
        width, height, borderRadius: 6,
        background: 'linear-gradient(90deg, var(--border) 0%, var(--sand) 50%, var(--border) 100%)',
        backgroundSize: '200% 100%',
        ...style,
      }}
    />
  )
}
