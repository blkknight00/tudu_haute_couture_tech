import { useState, useEffect } from 'react';
import api from '../../api/axios';
import { Users, Activity, Building2, BarChart3, Database, MessageSquare, ShieldAlert } from 'lucide-react';

interface MetricsData {
    total_orgs: number;
    active_orgs: number;
    trial_orgs: number;
    past_due_orgs: number;
    total_users: number;
    total_projects: number;
    total_tasks: number;
    tasks_month: number;
    mrr_mxn: number;
    wa_messages_month: number;
}

const BackofficeDashboard = () => {
    const [metrics, setMetrics] = useState<MetricsData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        fetchMetrics();
    }, []);

    const fetchMetrics = async () => {
        try {
            const res = await api.get('/saas_admin.php?action=metrics');
            if (res.data.status === 'success') {
                setMetrics(res.data.data);
            } else {
                setError(res.data.message || 'Error al cargar métricas');
            }
        } catch (err: any) {
            console.error('Error fetching backoffice metrics:', err);
            setError(err.response?.data?.message || 'Error de conexión con la Nube');
        } finally {
            setLoading(false);
        }
    };

    if (error) {
        return (
            <div className="flex-1 h-full flex flex-col items-center justify-center gap-4 text-red-500">
                <ShieldAlert size={48} className="text-red-500/50" />
                <h3 className="font-bold text-xl uppercase tracking-widest">Error Crítico</h3>
                <p className="font-mono text-sm">{error}</p>
                <button onClick={() => { setError(null); setLoading(true); fetchMetrics(); }} className="mt-4 px-6 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded-xl transition-colors">Reintentar</button>
            </div>
        );
    }

    if (loading || !metrics) {
        return (
            <div className="flex-1 h-full flex flex-col items-center justify-center animate-pulse gap-4 text-slate-500">
                <div className="w-16 h-16 border-4 border-amber-500/20 border-t-amber-500 rounded-full animate-spin"></div>
                <p className="font-mono text-sm uppercase tracking-widest">Alineando constelaciones de datos...</p>
            </div>
        );
    }

    const StatCard = ({ title, value, subtitle, icon, color }: any) => (
        <div className="haute-glass-card p-6 rounded-2xl relative overflow-hidden group">
            <div className={`absolute -right-6 -top-6 w-24 h-24 bg-${color}-500/5 rounded-full blur-2xl group-hover:bg-${color}-500/10 transition-colors`}></div>
            <div className="flex justify-between items-start mb-4">
                <div>
                    <p className="text-slate-400 text-sm font-medium uppercase tracking-wider mb-1">{title}</p>
                    <h3 className="text-3xl font-bold text-white tracking-tight">{value}</h3>
                </div>
                <div className={`p-3 bg-${color}-500/10 text-${color}-400 rounded-xl`}>
                    {icon}
                </div>
            </div>
            {subtitle && <p className="text-sm text-slate-500 font-mono mt-4 border-t border-slate-800/50 pt-3">{subtitle}</p>}
        </div>
    );

    return (
        <div className="space-y-8 animate-fade-in-up">
            <header className="mb-10">
                <h1 className="text-3xl font-bold text-white mb-2">Resumen Global de la Red SaaS</h1>
                <p className="text-slate-400">Métricas en tiempo real de todos los inquilinos y uso de infraestructura operativa.</p>
            </header>

            {/* Financial & Core */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div className="haute-glass-card bg-gradient-to-br from-amber-500/10 to-orange-600/5 border-amber-500/20 p-6 rounded-2xl relative overflow-hidden group">
                    <div className="flex justify-between items-start mb-4">
                        <div>
                            <p className="text-amber-200/60 text-sm font-bold uppercase tracking-wider mb-1">MRR Estimado (MXN)</p>
                            <h3 className="text-4xl font-bold text-amber-400 tracking-tight">
                                ${new Intl.NumberFormat('es-MX').format(metrics.mrr_mxn / 100)}
                            </h3>
                        </div>
                        <div className="p-3 bg-amber-500/20 text-amber-400 rounded-xl">
                            <BarChart3 size={24} />
                        </div>
                    </div>
                    <p className="text-sm text-amber-200/40 font-mono mt-4 border-t border-amber-500/20 pt-3">Excluye cuentas God Mode (Lifetime).</p>
                </div>

                <StatCard 
                    title="Espacios (Workspaces)" 
                    value={metrics.total_orgs} 
                    subtitle={<span className="text-emerald-400">{metrics.active_orgs} Activos / {metrics.trial_orgs} Trials</span>}
                    icon={<Building2 size={24} />} 
                    color="indigo" 
                />
                
                <StatCard 
                    title="Usuarios Globales" 
                    value={new Intl.NumberFormat('es-MX').format(metrics.total_users)} 
                    subtitle="Identidades almacenadas en el sistema"
                    icon={<Users size={24} />} 
                    color="sky" 
                />

                <StatCard 
                    title="Tráfico WhatsApp" 
                    value={new Intl.NumberFormat('es-MX').format(metrics.wa_messages_month)} 
                    subtitle="Mensajes procesados este mes"
                    icon={<MessageSquare size={24} />} 
                    color="emerald" 
                />
            </div>

            <h2 className="text-xl font-bold text-white mt-12 mb-6">Uso de Base de Datos y Motor de Operaciones</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <StatCard 
                    title="Total Proyectos" 
                    value={new Intl.NumberFormat('es-MX').format(metrics.total_projects)} 
                    subtitle="Tableros Kanban distribuidos"
                    icon={<Database size={24} />} 
                    color="purple" 
                />
                <StatCard 
                    title="Tareas Históricas" 
                    value={new Intl.NumberFormat('es-MX').format(metrics.total_tasks)} 
                    subtitle="Tareas actualmente vivas (no archivadas)"
                    icon={<Activity size={24} />} 
                    color="rose" 
                />
                <StatCard 
                    title="Actividad Mensual" 
                    value={`+${new Intl.NumberFormat('es-MX').format(metrics.tasks_month)}`} 
                    subtitle="Nuevas tareas generadas este mes"
                    icon={<Activity size={24} />} 
                    color="blue" 
                />
            </div>
            
            {/* Informational Pane */}
            <div className="mt-8 haute-glass-card p-6 rounded-2xl flex items-center justify-between">
                <div>
                    <h4 className="text-white font-bold mb-1">Estado de la Infraestructura</h4>
                    <p className="text-slate-400 text-sm">Los Microservicios de facturación y Base de Datos están operando nominalmente.</p>
                </div>
                <div className="flex items-center gap-2 text-emerald-400 bg-emerald-400/10 px-4 py-2 rounded-xl font-mono text-sm font-bold">
                    <span className="relative flex h-3 w-3">
                      <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                      <span className="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    ALL SYSTEMS NOMINAL
                </div>
            </div>
        </div>
    );
};

export default BackofficeDashboard;
