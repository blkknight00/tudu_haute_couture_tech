import { useState, useEffect } from 'react';
import api from '../../api/axios';
import { ShieldAlert, Search, RefreshCw, User as UserIcon } from 'lucide-react';

interface AuditEntry {
    id: number;
    fecha: string;
    usuario_id: number;
    usuario_nombre: string | null;
    accion: string;
    tabla_afectada: string;
    registro_id: number | null;
    detalles: string;
}

const BackofficeAudit = () => {
    const [entries, setEntries] = useState<AuditEntry[]>([]);
    const [loading, setLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');

    useEffect(() => {
        fetchLogs();
        setSearchTerm('');
    }, []);

    const fetchLogs = async () => {
        setLoading(true);
        try {
            const res = await api.get('/audit.php?action=list');
            if (res.data.status === 'success') {
                setEntries(res.data.data);
            }
        } catch (error) {
            console.error('Error fetching audit logs:', error);
        } finally {
            setLoading(false);
        }
    };

    const getActionBadgeClass = (action: string) => {
        const uAction = action.toUpperCase();
        if (uAction.includes('INSERT')) return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
        if (uAction.includes('UPDATE')) return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
        if (uAction.includes('DELETE')) return 'bg-red-500/10 text-red-400 border-red-500/20';
        if (uAction.includes('LOGIN')) return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
        return 'bg-slate-800 text-slate-300 border-slate-700';
    };

    const filteredEntries = entries.filter(e =>
        (e.usuario_nombre?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
        e.accion.toLowerCase().includes(searchTerm.toLowerCase()) ||
        e.detalles.toLowerCase().includes(searchTerm.toLowerCase()) ||
        e.tabla_afectada.toLowerCase().includes(searchTerm.toLowerCase())
    );

    return (
        <div className="animate-fade-in-up h-full flex flex-col">
            <header className="mb-8 flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold text-white mb-2 flex items-center gap-3">
                        Auditoría Global
                    </h1>
                    <p className="text-slate-400">Rastreo de seguridad: Cien últimos movimientos del ecosistema.</p>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        onClick={fetchLogs}
                        disabled={loading}
                        className="p-3 text-slate-400 hover:text-amber-500 hover:bg-amber-500/10 rounded-xl transition-all"
                        title="Refrescar Logs"
                    >
                        <RefreshCw size={20} className={loading ? 'animate-spin text-amber-400' : ''} />
                    </button>
                </div>
            </header>

            <div className="flex-1 bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden flex flex-col">
                {/* Filters */}
                <div className="p-6 border-b border-slate-800/50">
                    <div className="relative max-w-xl">
                        <Search className="absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-500" size={20} />
                        <input
                            type="text"
                            placeholder="Buscar por usuario, acción, id o datos..."
                            value={searchTerm}
                            onChange={e => setSearchTerm(e.target.value)}
                            className="w-full pl-12 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 outline-none font-medium transition-all text-white placeholder-slate-500"
                        />
                    </div>
                </div>

                {/* Table */}
                <div className="flex-1 overflow-auto custom-scrollbar p-6 bg-slate-950/20">
                    <table className="w-full text-left border-collapse table-fixed">
                        <thead className="bg-slate-900 sticky top-0 z-10 text-xs text-slate-500 font-bold uppercase tracking-wider border-b border-slate-800">
                            <tr>
                                <th className="px-6 py-4 w-48">Marca de Tiempo</th>
                                <th className="px-6 py-4 w-56">Actor / Usuario</th>
                                <th className="px-6 py-4 w-36">Evento</th>
                                <th className="px-6 py-4 w-40">Entorno (Tabla)</th>
                                <th className="px-6 py-4">Metadatos / Carga</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/50 text-sm">
                            {loading && Array.from({ length: 10 }).map((_, i) => (
                                <tr key={i} className="animate-pulse">
                                    <td colSpan={5} className="px-6 py-6 border-b border-slate-800/30"></td>
                                </tr>
                            ))}
                            {!loading && filteredEntries.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-6 py-20 text-center text-slate-500 italic">
                                        Sistema sin actividad concurrente bajo esos parámetros.
                                    </td>
                                </tr>
                            )}
                            {!loading && filteredEntries.map((e) => (
                                <tr key={e.id} className="hover:bg-slate-800/30 transition-colors group">
                                    <td className="px-6 py-5 whitespace-nowrap text-xs text-slate-400 font-mono">
                                        {new Date(e.fecha).toLocaleString('es-ES', {
                                            day: '2-digit', month: '2-digit', year: 'numeric',
                                            hour: '2-digit', minute: '2-digit', second: '2-digit'
                                        })}
                                    </td>
                                    <td className="px-6 py-5">
                                        <div className="flex items-center gap-3">
                                            <div className="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center">
                                                <UserIcon size={14} className="text-slate-400" />
                                            </div>
                                            <div className="flex flex-col">
                                                <span className="font-bold text-slate-200 truncate max-w-[150px]">
                                                    {e.usuario_nombre || 'Desconocido / API'}
                                                </span>
                                                <span className="text-[10px] text-slate-500 font-mono">ID: {e.usuario_id}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-5 uppercase text-[10px] font-bold">
                                        <span className={`px-2.5 py-1 rounded-md border ${getActionBadgeClass(e.accion)}`}>
                                            {e.accion}
                                        </span>
                                    </td>
                                    <td className="px-6 py-5 font-mono text-xs text-sky-400/80">
                                        {e.tabla_afectada}
                                        {e.registro_id && <span className="text-slate-600 ml-1">#{e.registro_id}</span>}
                                    </td>
                                    <td className="px-6 py-5 text-sm text-slate-400 truncate max-w-sm" title={e.detalles}>
                                        <code className="text-xs bg-slate-950 px-2 py-1 rounded text-slate-400 border border-slate-800">{e.detalles}</code>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};

export default BackofficeAudit;
