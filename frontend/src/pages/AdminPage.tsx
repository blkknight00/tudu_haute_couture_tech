import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  BarChart3, Users, Building2, FolderKanban, CreditCard,
  Settings, MessageSquare, Search, ChevronRight, ChevronLeft,
  Shield, RefreshCw, X, CheckCircle2, AlertTriangle,
  Save, Eye, EyeOff, Smartphone, TrendingUp, Activity,
  Loader2, ArrowLeft
} from 'lucide-react';
import axios from 'axios';

const API = (import.meta.env.VITE_API_URL || 'http://localhost/tudu_haute_couture_tech/api') + '/saas_admin.php';

// ── Types ─────────────────────────────────────────────────────────────
interface Metrics {
  total_orgs: number; active_orgs: number; trial_orgs: number; past_due_orgs: number;
  total_users: number; total_projects: number; total_tasks: number; tasks_month: number;
  mrr_mxn: number; wa_messages_month: number;
}

interface Tenant {
  id: number; nombre: string; edition: string; plan: string; plan_status: string;
  trial_ends_at: string | null; plan_renews_at: string | null;
  members_limit: number; projects_limit: number; tasks_limit: number;
  whatsapp_bot: number; created_at: string;
  members_count: number; projects_count: number; tasks_count: number;
}

interface SettingItem {
  key: string; icon: string; label: string; group: string;
  set: boolean; preview: string;
}

interface WaLog {
  id: number; from_number: string; to_number: string; body: string;
  direccion: string; status: string; created_at: string;
  usuario_nombre: string | null; tarea_titulo: string | null;
}

type Tab = 'dashboard' | 'tenants' | 'settings' | 'whatsapp';

// ═══════════════════════════════════════════════════════════════════════
export default function AdminPage() {
  const navigate = useNavigate();
  const [tab, setTab] = useState<Tab>('dashboard');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // Dashboard
  const [metrics, setMetrics] = useState<Metrics | null>(null);

  // Tenants
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [tenantSearch, setTenantSearch] = useState('');
  const [tenantFilter, setTenantFilter] = useState({ plan: '', status: '', edition: '' });
  const [selectedTenant, setSelectedTenant] = useState<any>(null);
  const [editingTenant, setEditingTenant] = useState(false);
  const [tenantForm, setTenantForm] = useState<Record<string, any>>({});
  const [savingTenant, setSavingTenant] = useState(false);

  // Settings
  const [settings, setSettings] = useState<SettingItem[]>([]);
  const [settingValues, setSettingValues] = useState<Record<string, string>>({});
  const [showValues, setShowValues] = useState<Record<string, boolean>>({});
  const [savingSetting, setSavingSetting] = useState('');

  // WhatsApp logs
  const [waLogs, setWaLogs] = useState<WaLog[]>([]);

  // ── API helpers ────────────────────────────────────────────────────
  const api = useCallback(async (action: string, params: Record<string, any> = {}, method: 'GET' | 'POST' = 'GET') => {
    const opts: any = { withCredentials: true };
    if (method === 'POST') {
      return axios.post(`${API}?action=${action}`, params, opts);
    }
    const qs = new URLSearchParams(params).toString();
    return axios.get(`${API}?action=${action}${qs ? '&' + qs : ''}`, opts);
  }, []);

  // ── Loaders ────────────────────────────────────────────────────────
  const loadMetrics = useCallback(async () => {
    try {
      const { data } = await api('metrics');
      if (data.status === 'success') setMetrics(data.data);
    } catch (e: any) {
      if (e.response?.status === 403) {
        setError('No tienes permisos de Super Admin');
      }
    }
  }, [api]);

  const loadTenants = useCallback(async () => {
    const params: Record<string, string> = {};
    if (tenantSearch) params.q = tenantSearch;
    if (tenantFilter.plan) params.plan = tenantFilter.plan;
    if (tenantFilter.status) params.status = tenantFilter.status;
    if (tenantFilter.edition) params.edition = tenantFilter.edition;
    try {
      const { data } = await api('tenants', params);
      if (data.status === 'success') setTenants(data.data);
    } catch (_) {}
  }, [api, tenantSearch, tenantFilter]);

  const loadTenantDetail = useCallback(async (id: number) => {
    try {
      const { data } = await api('tenant_detail', { id: String(id) });
      if (data.status === 'success') {
        setSelectedTenant(data.data);
        setTenantForm({
          plan: data.data.org.plan,
          plan_status: data.data.org.plan_status,
          edition: data.data.org.edition,
          members_limit: data.data.org.members_limit,
          projects_limit: data.data.org.projects_limit,
          tasks_limit: data.data.org.tasks_limit,
          whatsapp_bot: data.data.org.whatsapp_bot,
          trial_days: 14,
        });
      }
    } catch (_) {}
  }, [api]);

  const loadSettings = useCallback(async () => {
    try {
      const { data } = await api('settings');
      if (data.status === 'success') setSettings(data.data);
    } catch (_) {}
  }, [api]);

  const loadWaLogs = useCallback(async () => {
    try {
      const { data } = await api('wa_logs', { limit: '100' });
      if (data.status === 'success') setWaLogs(data.data);
    } catch (_) {}
  }, [api]);

  // ── Save handlers ──────────────────────────────────────────────────
  const saveTenant = async () => {
    if (!selectedTenant) return;
    setSavingTenant(true);
    try {
      await api('update_tenant', { id: selectedTenant.org.id, ...tenantForm }, 'POST');
      await loadTenantDetail(selectedTenant.org.id);
      await loadTenants();
      setEditingTenant(false);
    } catch (_) {}
    setSavingTenant(false);
  };

  const saveSetting = async (key: string) => {
    const value = settingValues[key];
    if (!value?.trim()) return;
    setSavingSetting(key);
    try {
      await api('save_setting', { key, value }, 'POST');
      await loadSettings();
      setSettingValues(prev => ({ ...prev, [key]: '' }));
    } catch (_) {}
    setSavingSetting('');
  };

  // ── Initial load ──────────────────────────────────────────────────
  useEffect(() => {
    (async () => {
      setLoading(true);
      await loadMetrics();
      setLoading(false);
    })();
  }, [loadMetrics]);

  useEffect(() => {
    if (tab === 'tenants') loadTenants();
    if (tab === 'settings') loadSettings();
    if (tab === 'whatsapp') loadWaLogs();
  }, [tab, loadTenants, loadSettings, loadWaLogs]);

  // ── Access denied ─────────────────────────────────────────────────
  if (error) return (
    <div style={pageStyle}>
      <div style={{ ...cardStyle, textAlign: 'center', padding: '60px' }}>
        <Shield size={48} color="#ef4444" style={{ margin: '0 auto 16px' }} />
        <h2 style={{ fontSize: '20px', fontWeight: '700', marginBottom: '8px' }}>Acceso Denegado</h2>
        <p style={{ color: '#71717a', marginBottom: '24px' }}>{error}</p>
        <button onClick={() => navigate('/login')} style={btnPrimary}>Iniciar sesión</button>
      </div>
    </div>
  );

  // ── Loading ────────────────────────────────────────────────────────
  if (loading) return (
    <div style={pageStyle}>
      <div style={{ textAlign: 'center', padding: '100px' }}>
        <Loader2 size={32} color="#7c3aed" style={{ animation: 'spin 1s linear infinite', margin: '0 auto' }} />
        <p style={{ color: '#71717a', marginTop: '16px' }}>Cargando panel de administración...</p>
        <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
      </div>
    </div>
  );

  return (
    <div style={pageStyle}>
      {/* ── Sidebar ───────────────────────────────────────────────── */}
      <aside style={{
        width: '240px', flexShrink: 0,
        background: '#111113', borderRight: '1px solid #1c1c1f',
        padding: '20px 12px', display: 'flex', flexDirection: 'column',
        position: 'fixed', top: 0, left: 0, bottom: 0, zIndex: 40,
      }}>
        {/* Logo */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '10px', padding: '8px 12px', marginBottom: '8px' }}>
          <div style={{ width: '30px', height: '30px', borderRadius: '8px', background: 'linear-gradient(135deg,#7c3aed,#4f46e5)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '14px' }}>✅</div>
          <div>
            <div style={{ fontSize: '14px', fontWeight: '700' }}>TuDu Admin</div>
            <div style={{ fontSize: '10px', color: '#52525b' }}>Super Admin Panel</div>
          </div>
        </div>

        <div style={{ height: '1px', background: '#1c1c1f', margin: '8px 0 16px' }} />

        {/* Nav items */}
        {([
          { id: 'dashboard', icon: BarChart3, label: 'Dashboard' },
          { id: 'tenants',   icon: Building2, label: 'Tenants' },
          { id: 'settings',  icon: Settings,  label: 'Configuración' },
          { id: 'whatsapp',  icon: MessageSquare, label: 'WhatsApp Logs' },
        ] as const).map(item => (
          <button key={item.id} onClick={() => { setTab(item.id); setSelectedTenant(null); }} style={{
            display: 'flex', alignItems: 'center', gap: '10px',
            padding: '10px 14px', borderRadius: '10px', border: 'none',
            cursor: 'pointer', width: '100%', textAlign: 'left',
            fontSize: '13px', fontWeight: tab === item.id ? '600' : '400',
            background: tab === item.id ? 'rgba(124,58,237,0.15)' : 'transparent',
            color: tab === item.id ? '#a78bfa' : '#71717a',
            transition: 'all 0.15s', marginBottom: '2px',
          }}>
            <item.icon size={15} />
            {item.label}
          </button>
        ))}

        <div style={{ flex: 1 }} />

        <button onClick={() => navigate('/app')} style={{
          display: 'flex', alignItems: 'center', gap: '8px',
          padding: '10px 14px', borderRadius: '10px', border: 'none',
          cursor: 'pointer', fontSize: '12px', color: '#52525b',
          background: 'transparent', width: '100%', textAlign: 'left',
        }}>
          <ArrowLeft size={14} /> Volver a TuDu
        </button>
      </aside>

      {/* ── Main content ──────────────────────────────────────────── */}
      <main style={{ marginLeft: '240px', flex: 1, padding: '28px 32px', minHeight: '100vh' }}>

        {/* ════════════════════════════════════════════════════════════ */}
        {/* DASHBOARD TAB */}
        {/* ════════════════════════════════════════════════════════════ */}
        {tab === 'dashboard' && metrics && (
          <>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '28px' }}>
              <div>
                <h1 style={{ fontSize: '22px', fontWeight: '800', letterSpacing: '-0.5px' }}>Dashboard</h1>
                <p style={{ fontSize: '12px', color: '#52525b' }}>Vista general de la plataforma TuDu SaaS</p>
              </div>
              <button onClick={loadMetrics} style={{ ...btnOutline, display: 'flex', alignItems: 'center', gap: '6px' }}>
                <RefreshCw size={13} /> Actualizar
              </button>
            </div>

            {/* MRR highlight */}
            <div style={{
              padding: '24px', borderRadius: '16px', marginBottom: '16px',
              background: 'linear-gradient(135deg,rgba(124,58,237,0.12),rgba(79,70,229,0.06))',
              border: '1px solid rgba(124,58,237,0.2)',
              display: 'flex', alignItems: 'center', gap: '20px',
            }}>
              <div style={{ width: '52px', height: '52px', borderRadius: '14px', background: 'rgba(124,58,237,0.2)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <TrendingUp size={24} color="#a78bfa" />
              </div>
              <div>
                <div style={{ fontSize: '11px', color: '#71717a', textTransform: 'uppercase', letterSpacing: '1px', marginBottom: '4px' }}>MRR Estimado</div>
                <div style={{ fontSize: '32px', fontWeight: '900', letterSpacing: '-1px', background: 'linear-gradient(135deg,#a78bfa,#7c3aed)', WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent' }}>
                  ${metrics.mrr_mxn.toLocaleString()} MXN
                </div>
              </div>
              <div style={{ marginLeft: 'auto', textAlign: 'right' }}>
                <div style={{ fontSize: '22px', fontWeight: '700', color: '#22c55e' }}>{metrics.active_orgs}</div>
                <div style={{ fontSize: '11px', color: '#52525b' }}>orgs activas</div>
              </div>
            </div>

            {/* Metric cards grid */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: '12px', marginBottom: '16px' }}>
              {[
                { label: 'Organizaciones', value: metrics.total_orgs, icon: Building2, color: '#a78bfa' },
                { label: 'En trial', value: metrics.trial_orgs, icon: Activity, color: '#f59e0b' },
                { label: 'Pagos vencidos', value: metrics.past_due_orgs, icon: AlertTriangle, color: '#ef4444' },
                { label: 'Usuarios activos', value: metrics.total_users, icon: Users, color: '#3b82f6' },
              ].map(m => (
                <div key={m.label} style={cardStyle}>
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '12px' }}>
                    <span style={{ fontSize: '11px', color: '#52525b', textTransform: 'uppercase', letterSpacing: '0.5px' }}>{m.label}</span>
                    <m.icon size={14} color={m.color} />
                  </div>
                  <div style={{ fontSize: '28px', fontWeight: '800', color: m.color, letterSpacing: '-1px' }}>{m.value.toLocaleString()}</div>
                </div>
              ))}
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: '12px' }}>
              {[
                { label: 'Proyectos totales', value: metrics.total_projects, icon: FolderKanban, color: '#22c55e' },
                { label: 'Tareas activas', value: metrics.total_tasks, icon: CheckCircle2, color: '#06b6d4' },
                { label: 'Tareas este mes', value: metrics.tasks_month, icon: TrendingUp, color: '#ec4899' },
              ].map(m => (
                <div key={m.label} style={cardStyle}>
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '12px' }}>
                    <span style={{ fontSize: '11px', color: '#52525b', textTransform: 'uppercase', letterSpacing: '0.5px' }}>{m.label}</span>
                    <m.icon size={14} color={m.color} />
                  </div>
                  <div style={{ fontSize: '28px', fontWeight: '800', letterSpacing: '-1px' }}>{m.value.toLocaleString()}</div>
                </div>
              ))}
            </div>

            {/* WhatsApp stat */}
            <div style={{ ...cardStyle, marginTop: '12px', display: 'flex', alignItems: 'center', gap: '14px' }}>
              <Smartphone size={18} color="#22c55e" />
              <div>
                <span style={{ fontSize: '13px', color: '#a1a1aa' }}>Mensajes WhatsApp este mes: </span>
                <span style={{ fontSize: '16px', fontWeight: '700', color: '#22c55e' }}>{metrics.wa_messages_month}</span>
              </div>
            </div>
          </>
        )}

        {/* ════════════════════════════════════════════════════════════ */}
        {/* TENANTS TAB */}
        {/* ════════════════════════════════════════════════════════════ */}
        {tab === 'tenants' && !selectedTenant && (
          <>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '24px' }}>
              <div>
                <h1 style={{ fontSize: '22px', fontWeight: '800', letterSpacing: '-0.5px' }}>Tenants</h1>
                <p style={{ fontSize: '12px', color: '#52525b' }}>{tenants.length} organizaciones registradas</p>
              </div>
              <button onClick={loadTenants} style={{ ...btnOutline, display: 'flex', alignItems: 'center', gap: '6px' }}>
                <RefreshCw size={13} /> Actualizar
              </button>
            </div>

            {/* Search + filters */}
            <div style={{ display: 'flex', gap: '10px', marginBottom: '20px', flexWrap: 'wrap' }}>
              <div style={{ position: 'relative', flex: 1, minWidth: '200px' }}>
                <Search size={14} color="#52525b" style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)' }} />
                <input value={tenantSearch} onChange={e => setTenantSearch(e.target.value)}
                  placeholder="Buscar organización..." style={{ ...inputStyle, paddingLeft: '34px' }}
                  onKeyDown={e => e.key === 'Enter' && loadTenants()}
                />
              </div>
              <select value={tenantFilter.edition} onChange={e => setTenantFilter(f => ({ ...f, edition: e.target.value }))} style={selectStyle}>
                <option value="">Todas las ediciones</option>
                <option value="standalone">Stand Alone</option>
                <option value="corp">Corp</option>
              </select>
              <select value={tenantFilter.plan} onChange={e => setTenantFilter(f => ({ ...f, plan: e.target.value }))} style={selectStyle}>
                <option value="">Todos los planes</option>
                <option value="starter">Starter</option>
                <option value="pro">Pro</option>
                <option value="agency">Agency</option>
                <option value="enterprise">Enterprise</option>
              </select>
              <select value={tenantFilter.status} onChange={e => setTenantFilter(f => ({ ...f, status: e.target.value }))} style={selectStyle}>
                <option value="">Todos los estados</option>
                <option value="active">Activo</option>
                <option value="trialing">En trial</option>
                <option value="past_due">Pago vencido</option>
                <option value="cancelled">Cancelado</option>
                <option value="inactive">Inactivo</option>
              </select>
            </div>

            {/* Tenants table */}
            <div style={{ ...cardStyle, padding: 0, overflow: 'hidden' }}>
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                <thead>
                  <tr style={{ borderBottom: '1px solid #1c1c1f' }}>
                    {['Organización', 'Edición', 'Plan', 'Estado', 'Miembros', 'Proyectos', 'Tareas', ''].map(h => (
                      <th key={h} style={{ padding: '12px 16px', textAlign: 'left', color: '#52525b', fontWeight: '500', fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.5px' }}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {tenants.map(t => (
                    <tr key={t.id} style={{ borderBottom: '1px solid #0f0f11', cursor: 'pointer', transition: 'background 0.1s' }}
                      onClick={() => loadTenantDetail(t.id)}
                      onMouseEnter={e => (e.currentTarget as HTMLElement).style.background = 'rgba(124,58,237,0.04)'}
                      onMouseLeave={e => (e.currentTarget as HTMLElement).style.background = ''}
                    >
                      <td style={{ padding: '12px 16px', fontWeight: '600' }}>{t.nombre}</td>
                      <td style={{ padding: '12px 16px' }}>
                        <span style={pillStyle(t.edition === 'corp' ? '#7c3aed' : '#52525b')}>
                          {t.edition === 'corp' ? '🏢 Corp' : '👤 Solo'}
                        </span>
                      </td>
                      <td style={{ padding: '12px 16px' }}>
                        <span style={pillStyle('#3b82f6')}>{t.plan}</span>
                      </td>
                      <td style={{ padding: '12px 16px' }}>
                        <span style={pillStyle(statusColor(t.plan_status))}>
                          {statusLabel(t.plan_status)}
                        </span>
                      </td>
                      <td style={{ padding: '12px 16px', color: '#a1a1aa' }}>{t.members_count}/{t.members_limit}</td>
                      <td style={{ padding: '12px 16px', color: '#a1a1aa' }}>{t.projects_count}/{t.projects_limit}</td>
                      <td style={{ padding: '12px 16px', color: '#a1a1aa' }}>{t.tasks_count}</td>
                      <td style={{ padding: '12px 16px' }}><ChevronRight size={14} color="#3f3f46" /></td>
                    </tr>
                  ))}
                  {tenants.length === 0 && (
                    <tr><td colSpan={8} style={{ padding: '40px', textAlign: 'center', color: '#3f3f46' }}>No hay organizaciones que mostrar</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          </>
        )}

        {/* ── Tenant Detail ──────────────────────────────────────────── */}
        {tab === 'tenants' && selectedTenant && (
          <>
            <button onClick={() => setSelectedTenant(null)} style={{ ...btnOutline, display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '20px' }}>
              <ChevronLeft size={14} /> Volver a tenants
            </button>

            <div style={{ display: 'flex', alignItems: 'center', gap: '16px', marginBottom: '24px' }}>
              <div style={{ width: '48px', height: '48px', borderRadius: '14px', background: 'rgba(124,58,237,0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <Building2 size={22} color="#a78bfa" />
              </div>
              <div style={{ flex: 1 }}>
                <h2 style={{ fontSize: '20px', fontWeight: '700' }}>{selectedTenant.org.nombre}</h2>
                <div style={{ display: 'flex', gap: '8px', marginTop: '4px' }}>
                  <span style={pillStyle('#7c3aed')}>{selectedTenant.org.edition}</span>
                  <span style={pillStyle('#3b82f6')}>{selectedTenant.org.plan}</span>
                  <span style={pillStyle(statusColor(selectedTenant.org.plan_status))}>{statusLabel(selectedTenant.org.plan_status)}</span>
                </div>
              </div>
              <button onClick={() => setEditingTenant(!editingTenant)} style={editingTenant ? btnPrimary : btnOutline}>
                {editingTenant ? 'Cancelar' : '✏️ Editar plan'}
              </button>
            </div>

            {/* Usage cards */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: '12px', marginBottom: '20px' }}>
              {selectedTenant.usage && Object.entries(selectedTenant.usage).map(([key, val]: [string, any]) => (
                <div key={key} style={cardStyle}>
                  <div style={{ fontSize: '11px', color: '#52525b', textTransform: 'uppercase', marginBottom: '8px' }}>{key.replace(/_/g, ' ')}</div>
                  <div style={{ fontSize: '20px', fontWeight: '700' }}>
                    {typeof val === 'object' ? `${val.current ?? 0} / ${val.limit ?? '∞'}` : String(val)}
                  </div>
                </div>
              ))}
            </div>

            {/* Edit form */}
            {editingTenant && (
              <div style={{ ...cardStyle, border: '1px solid rgba(124,58,237,0.3)', marginBottom: '20px' }}>
                <div style={{ fontSize: '14px', fontWeight: '700', marginBottom: '16px', color: '#a78bfa' }}>✏️ Editar Organización</div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: '14px' }}>
                  <FormField label="Edición">
                    <select value={tenantForm.edition} onChange={e => setTenantForm(f => ({ ...f, edition: e.target.value }))} style={selectStyle}>
                      <option value="standalone">Stand Alone</option>
                      <option value="corp">Corp</option>
                    </select>
                  </FormField>
                  <FormField label="Plan">
                    <select value={tenantForm.plan} onChange={e => setTenantForm(f => ({ ...f, plan: e.target.value }))} style={selectStyle}>
                      {['starter','pro','agency','enterprise'].map(p => <option key={p} value={p}>{p}</option>)}
                    </select>
                  </FormField>
                  <FormField label="Estado">
                    <select value={tenantForm.plan_status} onChange={e => setTenantForm(f => ({ ...f, plan_status: e.target.value }))} style={selectStyle}>
                      {['active','trialing','past_due','cancelled','inactive'].map(s => <option key={s} value={s}>{s}</option>)}
                    </select>
                  </FormField>
                  <FormField label="Límite miembros">
                    <input type="number" value={tenantForm.members_limit} onChange={e => setTenantForm(f => ({ ...f, members_limit: +e.target.value }))} style={inputStyle} />
                  </FormField>
                  <FormField label="Límite proyectos">
                    <input type="number" value={tenantForm.projects_limit} onChange={e => setTenantForm(f => ({ ...f, projects_limit: +e.target.value }))} style={inputStyle} />
                  </FormField>
                  <FormField label="Límite tareas">
                    <input type="number" value={tenantForm.tasks_limit} onChange={e => setTenantForm(f => ({ ...f, tasks_limit: +e.target.value }))} style={inputStyle} />
                  </FormField>
                  <FormField label="WhatsApp Bot">
                    <select value={tenantForm.whatsapp_bot} onChange={e => setTenantForm(f => ({ ...f, whatsapp_bot: +e.target.value }))} style={selectStyle}>
                      <option value={1}>Activado</option>
                      <option value={0}>Desactivado</option>
                    </select>
                  </FormField>
                  <FormField label="Extender trial (días)">
                    <input type="number" value={tenantForm.trial_days} onChange={e => setTenantForm(f => ({ ...f, trial_days: +e.target.value }))} style={inputStyle} />
                  </FormField>
                </div>
                <div style={{ marginTop: '16px' }}>
                  <button onClick={saveTenant} disabled={savingTenant} style={{ ...btnPrimary, display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
                    {savingTenant ? <Loader2 size={14} style={{ animation: 'spin 1s linear infinite' }} /> : <Save size={14} />}
                    Guardar cambios
                  </button>
                </div>
              </div>
            )}

            {/* Members */}
            <div style={{ ...cardStyle, padding: 0, overflow: 'hidden' }}>
              <div style={{ padding: '14px 16px', borderBottom: '1px solid #1c1c1f', fontSize: '13px', fontWeight: '600' }}>
                👥 Miembros ({selectedTenant.members?.length ?? 0})
              </div>
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '12px' }}>
                <tbody>
                  {(selectedTenant.members || []).map((m: any) => (
                    <tr key={m.id} style={{ borderBottom: '1px solid #0f0f11' }}>
                      <td style={{ padding: '10px 16px', fontWeight: '500' }}>{m.nombre}</td>
                      <td style={{ padding: '10px 16px', color: '#71717a' }}>{m.email}</td>
                      <td style={{ padding: '10px 16px' }}>
                        <span style={pillStyle(m.rol === 'super_admin' ? '#f59e0b' : '#3b82f6')}>{m.rol}</span>
                      </td>
                      <td style={{ padding: '10px 16px', color: '#52525b' }}>{m.whatsapp || m.telefono || '—'}</td>
                      <td style={{ padding: '10px 16px' }}>
                        <span style={{ color: m.activo ? '#22c55e' : '#ef4444', fontSize: '11px' }}>
                          {m.activo ? '● Activo' : '○ Inactivo'}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}

        {/* ════════════════════════════════════════════════════════════ */}
        {/* SETTINGS TAB */}
        {/* ════════════════════════════════════════════════════════════ */}
        {tab === 'settings' && (
          <>
            <div style={{ marginBottom: '28px' }}>
              <h1 style={{ fontSize: '22px', fontWeight: '800', letterSpacing: '-0.5px' }}>Configuración</h1>
              <p style={{ fontSize: '12px', color: '#52525b' }}>API keys encriptadas con AES-256 · Solo Super Admin puede ver o modificar</p>
            </div>

            {['general', 'payments', 'whatsapp'].map(group => {
              const groupSettings = settings.filter(s => s.group === group);
              if (!groupSettings.length) return null;
              return (
                <div key={group} style={{ marginBottom: '24px' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '12px' }}>
                    <div style={{ fontSize: '13px', fontWeight: '700', color: '#a78bfa', textTransform: 'uppercase', letterSpacing: '1px' }}>
                      {group === 'general' ? '⚙️ General' : group === 'payments' ? '💳 Pagos' : '📲 WhatsApp (Bot Automático)'}
                    </div>
                    {(group === 'whatsapp' || group === 'payments') && (
                      <span style={{ fontSize: '10px', fontWeight: '700', padding: '2px 10px', borderRadius: '20px', background: 'rgba(245,158,11,0.15)', color: '#f59e0b', border: '1px solid rgba(245,158,11,0.3)' }}>
                        🔜 Próximamente
                      </span>
                    )}
                  </div>
                  {group === 'whatsapp' && (
                    <div style={{ padding: '14px 18px', borderRadius: '12px', background: 'rgba(34,197,94,0.06)', border: '1px solid rgba(34,197,94,0.15)', marginBottom: '12px', fontSize: '12px', color: '#71717a', lineHeight: 1.7 }}>
                      <strong style={{ color: '#4ade80' }}>📱 WhatsApp actual:</strong> TuDu ya comparte tareas por WhatsApp usando <strong style={{ color: '#a1a1aa' }}>wa.me</strong> (sin costo, sin API).<br />
                      <strong style={{ color: '#f59e0b' }}>🤖 Bot automático:</strong> Estas credenciales de Twilio habilitarán un bot que envía notificaciones automáticas y permite responder "LISTO" desde WhatsApp. <strong style={{ color: '#a1a1aa' }}>Es un feature premium futuro.</strong>
                    </div>
                  )}
                  {group === 'payments' && (
                    <div style={{ padding: '14px 18px', borderRadius: '12px', background: 'rgba(124,58,237,0.06)', border: '1px solid rgba(124,58,237,0.15)', marginBottom: '12px', fontSize: '12px', color: '#71717a', lineHeight: 1.7 }}>
                      <strong style={{ color: '#a78bfa' }}>💳 Cobro automático:</strong> Cuando configures las API keys de Stripe y Conekta, los clientes podrán pagar sus suscripciones con tarjeta de crédito o en OXXO directamente desde la Pricing Page.
                    </div>
                  )}
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                    {groupSettings.map(s => (
                      <div key={s.key} style={{ ...cardStyle, display: 'flex', alignItems: 'center', gap: '14px' }}>
                        <span style={{ fontSize: '18px' }}>{s.icon}</span>
                        <div style={{ flex: 1 }}>
                          <div style={{ fontSize: '13px', fontWeight: '600', marginBottom: '2px' }}>{s.label}</div>
                          <div style={{ fontSize: '11px', color: '#3f3f46', fontFamily: 'monospace' }}>{s.key}</div>
                        </div>
                        {s.set ? (
                          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <span style={{ fontSize: '12px', color: '#22c55e', fontFamily: 'monospace' }}>
                              {showValues[s.key] ? (settingValues[s.key] || s.preview) : s.preview}
                            </span>
                            <CheckCircle2 size={14} color="#22c55e" />
                          </div>
                        ) : (
                          <span style={{ fontSize: '11px', color: '#ef4444' }}>No configurado</span>
                        )}
                        <div style={{ display: 'flex', gap: '6px', alignItems: 'center' }}>
                          <input
                            type={showValues[s.key] ? 'text' : 'password'}
                            value={settingValues[s.key] || ''}
                            onChange={e => setSettingValues(v => ({ ...v, [s.key]: e.target.value }))}
                            placeholder={s.set ? 'Nuevo valor...' : 'Pegar API key...'}
                            style={{ ...inputStyle, width: '200px', fontSize: '12px' }}
                          />
                          <button onClick={() => setShowValues(v => ({ ...v, [s.key]: !v[s.key] }))} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#52525b' }}>
                            {showValues[s.key] ? <EyeOff size={14} /> : <Eye size={14} />}
                          </button>
                          <button onClick={() => saveSetting(s.key)} disabled={!settingValues[s.key]?.trim() || savingSetting === s.key} style={{
                            ...btnPrimary, padding: '6px 12px', fontSize: '11px',
                            opacity: !settingValues[s.key]?.trim() ? 0.4 : 1,
                          }}>
                            {savingSetting === s.key ? <Loader2 size={12} style={{ animation: 'spin 1s linear infinite' }} /> : <Save size={12} />}
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              );
            })}
          </>
        )}

        {/* ════════════════════════════════════════════════════════════ */}
        {/* WHATSAPP LOGS TAB */}
        {/* ════════════════════════════════════════════════════════════ */}
        {tab === 'whatsapp' && (
          <>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '24px' }}>
              <div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '4px' }}>
                  <h1 style={{ fontSize: '22px', fontWeight: '800', letterSpacing: '-0.5px' }}>WhatsApp Logs</h1>
                  <span style={{ fontSize: '10px', fontWeight: '700', padding: '2px 10px', borderRadius: '20px', background: 'rgba(245,158,11,0.15)', color: '#f59e0b', border: '1px solid rgba(245,158,11,0.3)' }}>
                    🔜 Feature Premium Futuro
                  </span>
                </div>
                <p style={{ fontSize: '12px', color: '#52525b' }}>{waLogs.length} mensajes registrados</p>
              </div>
              <button onClick={loadWaLogs} style={{ ...btnOutline, display: 'flex', alignItems: 'center', gap: '6px' }}>
                <RefreshCw size={13} /> Actualizar
              </button>
            </div>

            {/* Explanation banner */}
            <div style={{ padding: '16px 20px', borderRadius: '14px', marginBottom: '16px', background: 'rgba(34,197,94,0.06)', border: '1px solid rgba(34,197,94,0.15)', fontSize: '13px', color: '#71717a', lineHeight: 1.7 }}>
              <strong style={{ color: '#4ade80' }}>📱 Actualmente:</strong> TuDu comparte tareas por WhatsApp usando <strong style={{ color: '#e4e4e7' }}>wa.me</strong> — gratis y sin APIs.<br />
              <strong style={{ color: '#f59e0b' }}>🤖 Próximamente:</strong> Cuando actives el bot automático con Twilio, aquí verás el historial de mensajes entrantes y salientes ("LISTO", "+2", comentarios, etc.).
            </div>

            <div style={{ ...cardStyle, padding: 0, overflow: 'hidden' }}>
              {waLogs.length === 0 ? (
                <div style={{ padding: '60px', textAlign: 'center', color: '#3f3f46' }}>
                  <MessageSquare size={32} style={{ margin: '0 auto 12px', opacity: 0.3 }} />
                  <p>No hay mensajes del bot aún</p>
                  <p style={{ fontSize: '11px', marginTop: '4px', maxWidth: '350px', margin: '4px auto 0', lineHeight: 1.6 }}>Cuando configures Twilio en ⚙️ Configuración, los mensajes automáticos del bot aparecerán aquí</p>
                </div>
              ) : (
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '12px' }}>
                  <thead>
                    <tr style={{ borderBottom: '1px solid #1c1c1f' }}>
                      {['Dirección', 'De', 'Para', 'Mensaje', 'Usuario', 'Tarea', 'Estado', 'Fecha'].map(h => (
                        <th key={h} style={{ padding: '10px 14px', textAlign: 'left', color: '#52525b', fontWeight: '500', fontSize: '10px', textTransform: 'uppercase' }}>{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {waLogs.map(log => (
                      <tr key={log.id} style={{ borderBottom: '1px solid #0f0f11' }}>
                        <td style={{ padding: '10px 14px' }}>
                          <span style={pillStyle(log.direccion === 'inbound' ? '#22c55e' : '#3b82f6')}>
                            {log.direccion === 'inbound' ? '📥 Entrada' : '📤 Salida'}
                          </span>
                        </td>
                        <td style={{ padding: '10px 14px', fontFamily: 'monospace', color: '#71717a' }}>{log.from_number}</td>
                        <td style={{ padding: '10px 14px', fontFamily: 'monospace', color: '#71717a' }}>{log.to_number}</td>
                        <td style={{ padding: '10px 14px', maxWidth: '250px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', color: '#a1a1aa' }}>{log.body}</td>
                        <td style={{ padding: '10px 14px', color: '#71717a' }}>{log.usuario_nombre || '—'}</td>
                        <td style={{ padding: '10px 14px', color: '#71717a' }}>{log.tarea_titulo || '—'}</td>
                        <td style={{ padding: '10px 14px' }}>
                          <span style={pillStyle(log.status === 'delivered' ? '#22c55e' : log.status === 'failed' ? '#ef4444' : '#f59e0b')}>
                            {log.status}
                          </span>
                        </td>
                        <td style={{ padding: '10px 14px', color: '#52525b', whiteSpace: 'nowrap' }}>
                          {new Date(log.created_at).toLocaleString('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </>
        )}
      </main>

      <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════
function FormField({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label style={{ display: 'block', fontSize: '11px', fontWeight: '500', color: '#71717a', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '6px' }}>{label}</label>
      {children}
    </div>
  );
}

function statusColor(s: string): string {
  return { active: '#22c55e', trialing: '#f59e0b', past_due: '#ef4444', cancelled: '#71717a', inactive: '#3f3f46' }[s] || '#52525b';
}

function statusLabel(s: string): string {
  return { active: '● Activo', trialing: '◐ Trial', past_due: '⚠ Vencido', cancelled: '○ Cancelado', inactive: '○ Inactivo' }[s] || s;
}

function pillStyle(color: string): React.CSSProperties {
  return {
    display: 'inline-block', fontSize: '11px', fontWeight: '600',
    padding: '2px 10px', borderRadius: '20px',
    background: `${color}18`, color, border: `1px solid ${color}30`,
  };
}

const pageStyle: React.CSSProperties = {
  minHeight: '100vh', background: '#09090b', color: '#e4e4e7',
  fontFamily: 'system-ui, sans-serif', display: 'flex',
};

const cardStyle: React.CSSProperties = {
  background: '#111113', border: '1px solid #1c1c1f',
  borderRadius: '14px', padding: '20px',
};

const inputStyle: React.CSSProperties = {
  width: '100%', padding: '9px 12px', borderRadius: '8px',
  background: '#09090b', border: '1px solid #27272a',
  color: '#e4e4e7', fontSize: '13px', outline: 'none',
  boxSizing: 'border-box',
};

const selectStyle: React.CSSProperties = {
  ...inputStyle, appearance: 'auto', cursor: 'pointer',
};

const btnPrimary: React.CSSProperties = {
  padding: '9px 18px', borderRadius: '10px', border: 'none',
  background: 'linear-gradient(135deg,#7c3aed,#4f46e5)', color: 'white',
  fontSize: '13px', fontWeight: '600', cursor: 'pointer',
};

const btnOutline: React.CSSProperties = {
  padding: '9px 16px', borderRadius: '10px',
  background: 'transparent', border: '1px solid #27272a',
  color: '#a1a1aa', fontSize: '13px', cursor: 'pointer',
};
