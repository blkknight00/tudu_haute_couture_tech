import { useEffect, useState } from 'react';
import api from '../api/axios';
import KPICard from '../components/dashboard/KPICard';
import RecentTaskItem from '../components/dashboard/RecentTaskItem';
import TaskList from '../components/dashboard/TaskList';
import ErrorBoundary from '../components/common/ErrorBoundary';
import {
    LayoutDashboard,
    Clock,
    PlayCircle,
    CheckCircle,
    AlertTriangle
} from 'lucide-react';
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip } from 'recharts';

interface DashboardData {
    kpis: {
        total: number;
        pendientes: number;
        en_progreso: number;
        completados: number;
    };
    alerts: any[];
    quick_views: {
        due_today: any[];
        due_week: any[];
        new_today: any[];
    };
}

const Dashboard = () => {
    const [data, setData] = useState<DashboardData | null>(null);
    const [loading, setLoading] = useState(true);

    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const fetchData = async () => {
            try {
                const response = await api.get(`/dashboard.php?t=${Date.now()}`);
                if (response.data.status === 'success') {
                    setData(response.data);
                } else {
                    setError(response.data.message || 'Error desconocido al cargar datos.');
                }
            } catch (err: any) {
                console.error("Error fetching dashboard data", err);
                setError(err.message || 'Error de conexión con el servidor.');
            } finally {
                setLoading(false);
            }
        };

        fetchData();

        const handleProjectSaved = () => fetchData();
        window.addEventListener('project-saved', handleProjectSaved);

        return () => window.removeEventListener('project-saved', handleProjectSaved);
    }, []);

    if (loading) {
        return <div className="p-8 text-center text-tudu-text-muted">Cargando estadísticas...</div>;
    }

    if (error) {
        return (
            <div className="p-8 text-center">
                <div className="text-red-500 mb-2">Error cargando el dashboard:</div>
                <div className="text-tudu-text-muted">{error}</div>
                <button
                    onClick={() => window.location.reload()}
                    className="mt-4 px-4 py-2 bg-tudu-accent text-white rounded hover:bg-tudu-accent-hover"
                >
                    Reintentar
                </button>
            </div>
        );
    }

    if (!data) return null;
    if (!data.kpis) {
        return <div className="p-8 text-center text-red-500">Error: Datos de KPI no disponibles.</div>;
    }

    // Data for Chart
    const chartData = [
        { name: 'Pendientes', value: Number(data.kpis.pendientes || 0), color: '#EF4444' },
        { name: 'En Progreso', value: Number(data.kpis.en_progreso || 0), color: '#F59E0B' },
        { name: 'Completados', value: Number(data.kpis.completados || 0), color: '#10B981' },
    ].filter(item => item.value > 0);

    return (
        <div className="space-y-6">
            <h1 id="tour-dashboard-title" className="text-3xl font-bold text-tudu-text-light dark:text-tudu-text-dark mb-6">Resumen General</h1>

            {/* Alerts Section */}
            {data.alerts.length > 0 && (
                <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4 mb-6 animate-pulse-soft">
                    <div className="flex items-start">
                        <AlertTriangle className="text-red-500 mt-1 mr-3" size={20} />
                        <div>
                            <h4 className="font-semibold text-red-800 dark:text-red-300">Tareas por vencer ({data.alerts.length})</h4>
                            <p className="text-sm text-red-600 dark:text-red-400 mt-1">
                                Tienes tareas importantes que requieren tu atención inmediata.
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* KPIs Grid */}
            <ErrorBoundary>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <KPICard
                        title="Total Tareas"
                        value={data.kpis.total}
                        icon={<LayoutDashboard size={24} />}
                        bgClass="bg-blue-100 dark:bg-blue-900/30"
                        colorClass="text-blue-600 dark:text-blue-400"
                    />
                    <KPICard
                        title="Pendientes"
                        value={data.kpis.pendientes}
                        icon={<Clock size={24} />}
                        bgClass="bg-red-100 dark:bg-red-900/30"
                        colorClass="text-red-600 dark:text-red-400"
                    />
                    <KPICard
                        title="En Progreso"
                        value={data.kpis.en_progreso}
                        icon={<PlayCircle size={24} />}
                        bgClass="bg-amber-100 dark:bg-amber-900/30"
                        colorClass="text-amber-600 dark:text-amber-400"
                    />
                    <KPICard
                        title="Completadas"
                        value={data.kpis.completados}
                        icon={<CheckCircle size={24} />}
                        bgClass="bg-emerald-100 dark:bg-emerald-900/30"
                        colorClass="text-emerald-600 dark:text-emerald-400"
                    />
                </div>
            </ErrorBoundary>

            {/* Charts & Lists Row */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">

                {/* Stats Chart */}
                <ErrorBoundary>
                    <div className="bg-white dark:bg-tudu-content-dark p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 lg:col-span-1">
                        <h3 className="font-semibold text-lg mb-4 dark:text-white">Estado de Tareas</h3>
                        <div className="relative" style={{ height: 256 }}>
                            {chartData.length > 0 ? (
                                <ResponsiveContainer width="100%" height={256}>
                                    <PieChart>
                                        <Pie
                                            data={chartData}
                                            cx="50%"
                                            cy="50%"
                                            innerRadius={60}
                                            outerRadius={80}
                                            paddingAngle={5}
                                            dataKey="value"
                                        >
                                            {chartData.map((entry, index) => (
                                                <Cell key={`cell-${index}`} fill={entry.color} />
                                            ))}
                                        </Pie>
                                        <Tooltip
                                            contentStyle={{ backgroundColor: '#1F2937', borderColor: '#374151', borderRadius: '8px', color: '#fff' }}
                                            itemStyle={{ color: '#fff' }}
                                        />
                                    </PieChart>
                                </ResponsiveContainer>
                            ) : (
                                <div className="flex items-center justify-center h-full text-tudu-text-muted">
                                    No hay datos suficientes
                                </div>
                            )}
                            {/* Center Text */}
                            <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                                <span className="text-2xl font-bold text-tudu-text-light dark:text-white">{data.kpis.total}</span>
                            </div>
                        </div>
                    </div>
                </ErrorBoundary>

                {/* Quick Views */}
                <ErrorBoundary>
                    <div className="bg-white dark:bg-tudu-content-dark p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 lg:col-span-2">
                        <h3 className="font-semibold text-lg mb-4 dark:text-white">Vence Próximamente</h3>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <h4 className="text-xs uppercase text-tudu-text-muted font-semibold mb-3">Hoy</h4>
                                {data.quick_views.due_today.length === 0 && <p className="text-sm text-gray-400 italic">Nada vence hoy</p>}
                                {data.quick_views.due_today.map((task: any) => (
                                    <RecentTaskItem key={task.id} id={task.id} title={task.titulo} projectName={task.proyecto_nombre} files_count={task.files_count} />
                                ))}
                            </div>

                            <div>
                                <h4 className="text-xs uppercase text-tudu-text-muted font-semibold mb-3">Próximos 7 Días</h4>
                                {data.quick_views.due_week.length === 0 && <p className="text-sm text-gray-400 italic">Semana libre</p>}
                                {data.quick_views.due_week.map((task: any) => (
                                    <RecentTaskItem key={task.id} id={task.id} title={task.titulo} projectName={task.proyecto_nombre} date={task.fecha_termino} files_count={task.files_count} />
                                ))}
                            </div>

                            <div>
                                <h4 className="text-xs uppercase text-tudu-text-muted font-semibold mb-3">Nuevas Hoy</h4>
                                {data.quick_views.new_today.length === 0 && <p className="text-sm text-gray-400 italic">Sin actividad reciente</p>}
                                {data.quick_views.new_today.map((task: any) => (
                                    <RecentTaskItem key={task.id} id={task.id} title={task.titulo} projectName={task.proyecto_nombre} files_count={task.files_count} />
                                ))}
                            </div>
                        </div>
                    </div>
                </ErrorBoundary>
            </div>

            <div className="mt-8">
                <ErrorBoundary>
                    <TaskList />
                </ErrorBoundary>
            </div>
        </div>
    );
};

export default Dashboard;
