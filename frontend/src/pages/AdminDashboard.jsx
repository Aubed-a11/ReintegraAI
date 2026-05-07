import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import { useNavigate } from 'react-router-dom'
import { Card, EmptyState, StatusPill, Tag } from '../components/ui'
import { useApp } from '../context/AppContext'
import { adminApi } from '../services/api'

const STATUS_COLORS = {
  PENDING: 'var(--ocre)',
  VALIDATED: 'var(--green)',
  REJECTED: 'var(--red)',
}

export default function AdminDashboard() {
  const { isAdmin } = useApp()
  const navigate = useNavigate()
  const [stats, setStats] = useState(null)
  const [migrants, setMigrants] = useState([])
  const [plans, setPlans] = useState([])
  const [loading, setLoading] = useState(true)
  const [activeTab, setActiveTab] = useState('overview')

  useEffect(() => {
    if (!isAdmin) {
      navigate('/')
      return
    }
    loadData()
  }, [isAdmin, navigate])

  const loadData = async () => {
    try {
      const [statsRes, migrantsRes, plansRes] = await Promise.all([
        adminApi.dashboard(),
        adminApi.migrants(),
        adminApi.plans(),
      ])
      
      if (statsRes.ok) setStats(statsRes.data.data)
      if (migrantsRes.ok) setMigrants(migrantsRes.data.data)
      if (plansRes.ok) setPlans(plansRes.data.data)
    } catch (err) {
      toast.error('Erreur de chargement des données')
    } finally {
      setLoading(false)
    }
  }

  if (!isAdmin) return null
  if (loading) return <div className="loading">Chargement...</div>

  return (
    <div style={{ padding: 28 }}>
      <div style={{ maxWidth: 1200, margin: '0 auto' }}>
        
        {/* Header */}
        <div style={{ marginBottom: 32 }}>
          <h1 style={{ fontFamily: 'var(--font-display)', fontSize: 28, fontWeight: 700, color: 'var(--blue)', marginBottom: 8 }}>
            Dashboard Administrateur
          </h1>
          <p style={{ fontSize: 14, color: 'var(--muted)' }}>
            Suivi de l'évolution du programme HorizonAI
          </p>
        </div>

        {/* Tabs */}
        <div style={{ display: 'flex', gap: 4, marginBottom: 24, borderBottom: '1px solid var(--border)' }}>
          {[
            { id: 'overview', label: 'Aperçu général' },
            { id: 'migrants', label: 'Migrants inscrits' },
            { id: 'plans', label: 'Plans générés' },
          ].map(tab => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              style={{
                padding: '12px 20px',
                border: 'none',
                background: activeTab === tab.id ? 'var(--blue)' : 'transparent',
                color: activeTab === tab.id ? 'white' : 'var(--mid)',
                borderRadius: '8px 8px 0 0',
                fontSize: 14,
                fontWeight: 500,
                cursor: 'pointer',
                transition: 'all .2s',
              }}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {/* Overview Tab */}
        {activeTab === 'overview' && stats && (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: 20 }}>
            
            {/* Stats principales */}
            <Card style={{ gridColumn: 'span 2' }}>
              <h3 style={{ fontSize: 18, fontWeight: 600, marginBottom: 16 }}>Statistiques générales</h3>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: 16 }}>
                <div style={{ textAlign: 'center' }}>
                  <div style={{ fontSize: 32, fontWeight: 700, color: 'var(--blue)' }}>{stats.general.total_migrants}</div>
                  <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>Migrants inscrits</div>
                </div>
                <div style={{ textAlign: 'center' }}>
                  <div style={{ fontSize: 32, fontWeight: 700, color: 'var(--green)' }}>{stats.general.migrants_with_plans}</div>
                  <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>Avec plan généré</div>
                </div>
                <div style={{ textAlign: 'center' }}>
                  <div style={{ fontSize: 32, fontWeight: 700, color: 'var(--ocre)' }}>{stats.general.pending_plans}</div>
                  <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>Plans en attente</div>
                </div>
                <div style={{ textAlign: 'center' }}>
                  <div style={{ fontSize: 32, fontWeight: 700, color: 'var(--purple)' }}>{stats.general.validated_plans}</div>
                  <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>Plans validés</div>
                </div>
                <div style={{ textAlign: 'center' }}>
                  <div style={{ fontSize: 32, fontWeight: 700, color: 'var(--blue-mid)' }}>{stats.today_sessions}</div>
                  <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>Sessions IA aujourd'hui</div>
                </div>
                <div style={{ textAlign: 'center' }}>
                  <div style={{ fontSize: 32, fontWeight: 700, color: 'var(--green-mid)' }}>{stats.weekly_plans}</div>
                  <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>Plans cette semaine</div>
                </div>
              </div>
            </Card>

            {/* Plans par statut */}
            <Card>
              <h3 style={{ fontSize: 16, fontWeight: 600, marginBottom: 16 }}>Plans par statut</h3>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                {stats.plans_by_status.map(status => (
                  <div key={status.statut} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <StatusPill status={status.statut} />
                    <span style={{ fontWeight: 600 }}>{status.count}</span>
                  </div>
                ))}
              </div>
            </Card>

            {/* Migrants par pays */}
            <Card>
              <h3 style={{ fontSize: 16, fontWeight: 600, marginBottom: 16 }}>Migrants par pays</h3>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                {stats.migrants_by_country.slice(0, 8).map(country => (
                  <div key={country.pays_origine} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span style={{ fontSize: 14 }}>{country.pays_origine || 'Non spécifié'}</span>
                    <Tag variant="gray">{country.count}</Tag>
                  </div>
                ))}
              </div>
            </Card>

            {/* Taux de completion */}
            <Card>
              <h3 style={{ fontSize: 16, fontWeight: 600, marginBottom: 16 }}>Complétion des profils</h3>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                {stats.completion_stats.map(stat => (
                  <div key={stat.range} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span style={{ fontSize: 14 }}>{stat.range}</span>
                    <Tag variant="blue">{stat.count}</Tag>
                  </div>
                ))}
              </div>
            </Card>
          </div>
        )}

        {/* Migrants Tab */}
        {activeTab === 'migrants' && (
          <div style={{ display: 'grid', gap: 16 }}>
            {migrants.length === 0 ? (
              <EmptyState icon="👥" title="Aucun migrant" subtitle="Les migrants inscrits apparaîtront ici" />
            ) : (
              migrants.map(migrant => (
                <Card key={migrant.id} hover>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 12 }}>
                    <div>
                      <h3 style={{ fontSize: 16, fontWeight: 600, marginBottom: 4 }}>
                        {migrant.first_name} {migrant.last_name}
                      </h3>
                      <p style={{ fontSize: 14, color: 'var(--muted)' }}>{migrant.email}</p>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                      <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 4 }}>
                        Inscrit le {new Date(migrant.created_at).toLocaleDateString('fr-FR')}
                      </div>
                      <Tag variant={migrant.has_plan ? 'green' : 'gray'}>
                        {migrant.has_plan ? 'Plan généré' : 'Sans plan'}
                      </Tag>
                    </div>
                  </div>
                  
                  <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap', marginBottom: 12 }}>
                    <div>
                      <span style={{ fontSize: 12, color: 'var(--muted)' }}>Pays:</span>
                      <span style={{ fontSize: 14, marginLeft: 4 }}>{migrant.pays_origine || 'Non spécifié'}</span>
                    </div>
                    <div>
                      <span style={{ fontSize: 12, color: 'var(--muted)' }}>Ville:</span>
                      <span style={{ fontSize: 14, marginLeft: 4 }}>{migrant.ville_retour || 'Non spécifiée'}</span>
                    </div>
                    <div>
                      <span style={{ fontSize: 12, color: 'var(--muted)' }}>Profil:</span>
                      <span style={{ fontSize: 14, marginLeft: 4 }}>{migrant.completion_pct}%</span>
                    </div>
                  </div>

                  {migrant.competences.length > 0 && (
                    <div style={{ marginBottom: 12 }}>
                      <span style={{ fontSize: 12, color: 'var(--muted)' }}>Compétences:</span>
                      <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap', marginTop: 4 }}>
                        {migrant.competences.map((comp, i) => (
                          <Tag key={i} variant="blue" style={{ fontSize: 11 }}>{comp}</Tag>
                        ))}
                      </div>
                    </div>
                  )}

                  {migrant.plan_status && (
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                      <span style={{ fontSize: 12, color: 'var(--muted)' }}>Plan:</span>
                      <StatusPill status={migrant.plan_status} />
                      {migrant.score_ia && (
                        <span style={{ fontSize: 14, fontWeight: 600 }}>{migrant.score_ia}/100</span>
                      )}
                    </div>
                  )}
                </Card>
              ))
            )}
          </div>
        )}

        {/* Plans Tab */}
        {activeTab === 'plans' && (
          <div style={{ display: 'grid', gap: 16 }}>
            {plans.length === 0 ? (
              <EmptyState icon="📋" title="Aucun plan" subtitle="Les plans générés apparaîtront ici" />
            ) : (
              plans.map(plan => (
                <Card key={plan.id} hover>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 12 }}>
                    <div>
                      <h3 style={{ fontSize: 16, fontWeight: 600, marginBottom: 4 }}>
                        Plan #{plan.id}
                      </h3>
                      <p style={{ fontSize: 14, color: 'var(--muted)' }}>
                        {plan.first_name} {plan.last_name} • {plan.pays_origine}
                      </p>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                      <StatusPill status={plan.statut} />
                      <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>
                        {new Date(plan.created_at).toLocaleDateString('fr-FR')}
                      </div>
                    </div>
                  </div>

                  <div style={{ display: 'flex', gap: 16, alignItems: 'center', marginBottom: 12 }}>
                    <div>
                      <span style={{ fontSize: 12, color: 'var(--muted)' }}>Score:</span>
                      <span style={{ fontSize: 16, fontWeight: 600, marginLeft: 4 }}>{plan.score_ia}/100</span>
                    </div>
                    <div>
                      <span style={{ fontSize: 12, color: 'var(--muted)' }}>Opportunités:</span>
                      <span style={{ fontSize: 14, marginLeft: 4 }}>{plan.opportunities_count}</span>
                    </div>
                  </div>

                  {plan.resume_global && (
                    <p style={{ fontSize: 13, color: 'var(--mid)', lineHeight: 1.5, padding: '8px 12px', background: 'var(--sand)', borderRadius: 6 }}>
                      {plan.resume_global}
                    </p>
                  )}
                </Card>
              ))
            )}
          </div>
        )}
      </div>
    </div>
  )
}