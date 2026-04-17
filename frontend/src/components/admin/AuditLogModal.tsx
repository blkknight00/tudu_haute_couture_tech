import { useState, useEffect } from 'react';
import api from '../../api/axios';
import { X, ClipboardList, Search, RefreshCw, User as UserIcon } from 'lucide-react';

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

interface AuditLogModalProps {
    isOpen: boolean;
    onClose: () => void;
}

const AuditLogModal = ({ isOpen, onClose }: AuditLogModalProps) => {
    const [entries, setEntries] = useState<AuditEntry[]>([]);
    const [loading, setLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');

    useEffect(() => {
        if (isOpen) {
            fetchLogs();
            setSearchTerm('');
        }
    }, [isOpen]);

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
        if (uAction.includes('INSERT')) return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
        if (uAction.includes('UPDATE')) return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
        if (uAction.includes('DELETE')) return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        if (uAction.includes('LOGIN')) return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
        return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
    };

    const filteredEntries = entries.filter(e =>
        (e.usuario_nombre?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
        e.accion.toLowerCase().includes(searchTerm.toLowerCase()) ||
        e.detalles.toLowerCase().includes(searchTerm.toLowerCase()) ||
        e.tabla_afectada.toLowerCase().includes(searchTerm.toLowerCase())
    );

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fade-in" onClick={onClose}>
            <div
                className="bg-white dark:bg-tudu-content-dark w-full max-w-5xl h-[85vh] rounded-2xl shadow-2xl overflow-hidden flex flex-col"
                onClick={e => e.stopPropagation()}
            >
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <div className="flex items-center gap-3">
                        <div className="p-2 bg-tudu-accent/10 rounded-lg">
                            <ClipboardList className="text-tudu-accent" />
                        </div>
                        <div>
                            <h2 className="text-xl font-bold text-gray-800 dark:text-white">Registro de Auditoría</h2>
                            <p className="text-xs text-tudu-text-muted">Últimos 100 movimientos del sistema</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={fetchLogs}
                            disabled={loading}
                            className="p-2 text-gray-500 hover:text-tudu-accent hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                        >
                            <RefreshCw size={20} className={loading ? 'animate-spin' : ''} />
                        </button>
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-white transition-colors">
                            <X size={24} />
                        </button>
                    </div>
                </div>

                {/* Filters */}
                <div className="px-6 py-4 border-b border-gray-50 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30">
                    <div className="relative max-w-md">
                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={18} />
                        <input
                            type="text"
                            placeholder="Filtrar por usuario, acción o detalles..."
                            value={searchTerm}
                            onChange={e => setSearchTerm(e.target.value)}
                            className="w-full pl-10 pr-4 py-2 bg-white dark:bg-tudu-bg-dark border border-gray-200 dark:border-gray-600 rounded-xl text-sm focus:ring-2 focus:ring-tudu-accent outline-none font-medium transition-all"
                        />
                    </div>
                </div>

                {/* Table */}
                <div className="flex-1 overflow-hidden flex flex-col p-2">
                    <div className="flex-1 overflow-y-auto custom-scrollbar border border-gray-100 dark:border-gray-700 rounded-xl shadow-inner bg-white dark:bg-tudu-bg-dark">
                        <table className="w-full text-left border-collapse table-fixed">
                            <thead className="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10 text-xs text-gray-500 dark:text-gray-400 font-bold uppercase tracking-wider">
                                <tr>
                                    <th className="px-4 py-3 w-40">Fecha</th>
                                    <th className="px-4 py-3 w-48">Usuario</th>
                                    <th className="px-4 py-3 w-32">Acción</th>
                                    <th className="px-4 py-3 w-32">Tabla</th>
                                    <th className="px-4 py-3 w-20 text-center">ID</th>
                                    <th className="px-4 py-3">Detalles</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800 text-sm">
                                {loading && Array.from({ length: 5 }).map((_, i) => (
                                    <tr key={i} className="animate-pulse">
                                        <td colSpan={6} className="px-4 py-4 h-12 bg-gray-50/50 dark:bg-gray-800/20"></td>
                                    </tr>
                                ))}
                                {!loading && filteredEntries.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-20 text-center text-tudu-text-muted">
                                            No se encontraron registros que coincidan con la búsqueda.
                                        </td>
                                    </tr>
                                )}
                                {!loading && filteredEntries.map((e) => (
                                    <tr key={e.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors group">
                                        <td className="px-4 py-3 whitespace-nowrap text-xs text-gray-500 font-mono">
                                            {new Date(e.fecha).toLocaleString('es-ES', {
                                                day: '2-digit',
                                                month: '2-digit',
                                                year: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                                second: '2-digit'
                                            })}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <div className="w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                                    <UserIcon size={12} className="text-gray-400" />
                                                </div>
                                                <span className="font-medium text-gray-800 dark:text-gray-200 truncate">
                                                    {e.usuario_nombre || `ID: ${e.usuario_id}`}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 uppercase text-[10px] font-bold">
                                            <span className={`px-2 py-0.5 rounded-full ${getActionBadgeClass(e.accion)}`}>
                                                {e.accion}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-blue-600 dark:text-blue-400">
                                            {e.tabla_afectada}
                                        </td>
                                        <td className="px-4 py-3 text-center text-xs text-gray-400">
                                            {e.registro_id || '-'}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-gray-600 dark:text-gray-400 truncate max-w-xs" title={e.detalles}>
                                            {e.detalles}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Footer */}
                <div className="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-700 flex justify-between items-center">
                    <div className="text-xs text-tudu-text-muted">
                        Mostrando {filteredEntries.length} registros
                    </div>
                    <button
                        onClick={onClose}
                        className="px-6 py-2 text-sm font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl transition-all"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    );
};

export default AuditLogModal;
