import { useState, useEffect } from 'react';
import api from '../../api/axios';
import { Building2, Search, Edit2, Trash2, CheckCircle, XCircle, Plus } from 'lucide-react';

interface Organization {
    id: number;
    nombre: string;
    plan?: string;
    plan_status?: string;
    plan_renews_at?: string;
    created_at?: string;
}

const BackofficeTenants = () => {
    const [organizations, setOrganizations] = useState<Organization[]>([]);
    const [loading, setLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [view, setView] = useState<'list' | 'form'>('list');
    const [editingOrg, setEditingOrg] = useState<Organization | null>(null);
    const [formData, setFormData] = useState({
        nombre: ''
    });
    const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);

    useEffect(() => {
        fetchOrganizations();
    }, []);

    const fetchOrganizations = async () => {
        setLoading(true);
        try {
            const res = await api.get('/organizations.php?action=list');
            if (res.data.status === 'success') {
                setOrganizations(res.data.data);
            }
        } catch (error) {
            console.error('Error fetching organizations:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleCreateClick = () => {
        setEditingOrg(null);
        setFormData({ nombre: '' });
        setView('form');
        setMessage(null);
    };

    const handleEditClick = (org: Organization) => {
        setEditingOrg(org);
        setFormData({ nombre: org.nombre });
        setView('form');
        setMessage(null);
    };

    const handleDeleteClick = async (orgId: number) => {
        if (orgId === 1) {
            alert('No se puede eliminar la organización principal del sistema.');
            return;
        }
        if (!confirm('¿Estás seguro de eliminar esta organización? Esto podría afectar a los usuarios asociados.')) return;

        try {
            await api.post(`/organizations.php?action=delete&id=${orgId}`);
            fetchOrganizations();
            setMessage({ type: 'success', text: 'Organización eliminada.' });
        } catch (error: any) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Error al eliminar.' });
        }
    };

    const handleToggleLifetime = async (orgId: number) => {
        if (orgId === 1) {
            alert('La organización principal no necesita ser modificada.');
            return;
        }
        if (!confirm('¿Quieres alternar el estado God Mode (Lifetime) para esta cuenta?')) return;

        setLoading(true);
        try {
            const res = await api.post(`/organizations.php?action=toggle_lifetime&id=${orgId}`);
            fetchOrganizations();
            setMessage({ type: 'success', text: res.data.message });
        } catch (error: any) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Error al cambiar plan.' });
        } finally {
            setLoading(false);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setMessage(null);

        try {
            const res = await api.post('/organizations.php?action=save', {
                id: editingOrg?.id,
                nombre: formData.nombre
            });

            if (res.data.status === 'success') {
                setMessage({ type: 'success', text: editingOrg ? 'Organización actualizada.' : 'Organización creada.' });
                fetchOrganizations();
                setView('list');
            } else {
                setMessage({ type: 'error', text: res.data.message || 'Error desconocido' });
            }
        } catch (error: any) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Error al guardar.' });
        } finally {
            setLoading(false);
        }
    };

    const filteredOrgs = organizations.filter(o =>
        o.nombre.toLowerCase().includes(searchTerm.toLowerCase())
    );

    return (
        <div className="animate-fade-in-up h-full flex flex-col">
            <header className="mb-8 flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold text-white mb-2">Inquilinos de la Red (Tenants)</h1>
                    <p className="text-slate-400">Administra todas las empresas independientes en la plataforma SaaS.</p>
                </div>
            </header>

            <div className="flex-1 bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden flex flex-col">
                <div className="p-6 flex flex-col h-full">
                    {message && (
                        <div className={`mb-6 p-4 rounded-xl flex items-center gap-3 text-sm animate-shake ${message.type === 'success' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}`}>
                            {message.type === 'success' ? <CheckCircle size={20} /> : <XCircle size={20} />}
                            {message.text}
                        </div>
                    )}

                    {view === 'list' ? (
                        <>
                            <div className="flex flex-col sm:flex-row gap-4 mb-6 justify-between">
                                <div className="relative flex-1 max-w-xl">
                                    <Search className="absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-500" size={20} />
                                    <input
                                        type="text"
                                        placeholder="Buscar espacio..."
                                        value={searchTerm}
                                        onChange={e => setSearchTerm(e.target.value)}
                                        className="w-full pl-12 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 outline-none font-medium transition-all text-white placeholder-slate-500"
                                    />
                                </div>
                                <button
                                    onClick={handleCreateClick}
                                    className="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-slate-950 px-6 py-3 rounded-xl text-sm font-bold transition-all shadow-lg shadow-amber-500/20"
                                >
                                    <Plus size={20} /> Crear Espacio de Trabajo
                                </button>
                            </div>

                            <div className="flex-1 overflow-auto custom-scrollbar border border-slate-800 rounded-xl bg-slate-950/50">
                                <table className="w-full text-left border-collapse">
                                    <thead className="bg-slate-900 sticky top-0 z-10 border-b border-slate-800">
                                        <tr>
                                            <th className="px-6 py-5 text-xs font-bold text-slate-400 uppercase tracking-wider">ID Red</th>
                                            <th className="px-6 py-5 text-xs font-bold text-slate-400 uppercase tracking-wider">Espacio de Negocio</th>
                                            <th className="px-6 py-5 text-xs font-bold text-slate-400 uppercase tracking-wider">Plan (Nivel)</th>
                                            <th className="px-6 py-5 text-xs font-bold text-slate-400 uppercase tracking-wider">Renovación</th>
                                            <th className="px-6 py-5 text-xs font-bold text-slate-400 uppercase tracking-wider text-right">Controles Globales</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-800/50">
                                        {loading && organizations.length === 0 ? (
                                            Array.from({ length: 5 }).map((_, i) => (
                                                <tr key={i} className="animate-pulse">
                                                    <td colSpan={5} className="px-6 py-6 border-b border-slate-800/30"></td>
                                                </tr>
                                            ))
                                        ) : filteredOrgs.length === 0 ? (
                                            <tr>
                                                <td colSpan={5} className="px-6 py-16 text-center text-slate-500 italic">
                                                    No se encontraron espacios con ese criterio.
                                                </td>
                                            </tr>
                                        ) : (
                                            filteredOrgs.map(org => (
                                                <tr key={org.id} className="hover:bg-slate-800/40 transition-colors group">
                                                    <td className="px-6 py-5 whitespace-nowrap text-sm font-mono text-slate-500">
                                                        TDU-{String(org.id).padStart(4, '0')}
                                                    </td>
                                                    <td className="px-6 py-5 whitespace-nowrap">
                                                        <div className="flex items-center gap-4">
                                                            <div className="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center border border-slate-700">
                                                                <Building2 size={20} className="text-slate-400" />
                                                            </div>
                                                            <div>
                                                                <span className="text-base font-bold text-white block mb-1">{org.nombre}</span>
                                                                <div className="flex items-center gap-2">
                                                                    {org.id === 1 && <span className="text-[10px] bg-blue-500/20 text-blue-400 px-2 py-0.5 rounded uppercase font-bold border border-blue-500/20">Master Node</span>}
                                                                    {org.plan_status === 'lifetime' && <span className="text-[10px] bg-amber-500/20 text-amber-500 px-2 py-0.5 rounded uppercase font-bold border border-amber-500/30 flex items-center gap-1">⚡ God Mode (Free)</span>}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-5 whitespace-nowrap">
                                                        <div className="flex items-center gap-2">
                                                            {org.plan_status === 'lifetime' ? (
                                                                <span className="text-xs bg-amber-500/10 text-amber-500 px-2 py-1 rounded uppercase font-bold text-center border border-amber-500/20">God Mode</span>
                                                            ) : org.plan_status === 'active' ? (
                                                                <span className="text-xs bg-emerald-500/10 text-emerald-400 px-2 py-1 rounded uppercase font-bold text-center border border-emerald-500/20">{org.plan || 'Starter'}</span>
                                                            ) : (
                                                                <span className="text-xs bg-slate-800 text-slate-400 px-2 py-1 rounded uppercase font-bold text-center border border-slate-700">Trial / Vencido</span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-5 whitespace-nowrap text-sm text-slate-400 font-mono">
                                                        {org.plan_status === 'lifetime' ? '∞ Para Siempre' : 
                                                         org.plan_renews_at ? new Date(org.plan_renews_at).toLocaleDateString('es-MX') : 'No Registra Pago'}
                                                    </td>
                                                    <td className="px-6 py-5 whitespace-nowrap text-right text-sm">
                                                        <div className="flex items-center justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                                            <button title="Editar metadatos" onClick={() => handleEditClick(org)} className="p-2.5 text-blue-400 hover:bg-blue-500/10 rounded-xl transition-colors ring-1 ring-transparent hover:ring-blue-500/20">
                                                                <Edit2 size={18} />
                                                            </button>
                                                            {org.id !== 1 && (
                                                                <>
                                                                    <button title={org.plan_status === 'lifetime' ? 'Revocar God Mode' : 'Inyectar God Mode (Ilimitado)'} onClick={() => handleToggleLifetime(org.id)} className={`p-2.5 rounded-xl transition-all duration-300 ring-1 ring-transparent ${org.plan_status === 'lifetime' ? 'text-amber-500 bg-amber-500/10 ring-amber-500/30 hover:bg-amber-500/20' : 'text-slate-500 hover:text-amber-400 hover:bg-amber-500/10 hover:ring-amber-500/20'}`}>
                                                                        <CheckCircle size={18} />
                                                                    </button>
                                                                    <button title="Exterminar Organización" onClick={() => handleDeleteClick(org.id)} className="p-2.5 text-red-500 hover:bg-red-500/10 rounded-xl transition-colors ring-1 ring-transparent hover:ring-red-500/20">
                                                                        <Trash2 size={18} />
                                                                    </button>
                                                                </>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    ) : (
                        <div className="max-w-xl mx-auto w-full flex-1 flex flex-col justify-center">
                            <h3 className="text-2xl font-bold text-white mb-8 text-center flex justify-center items-center gap-3">
                                <Building2 className="text-amber-500" />
                                {editingOrg ? 'Editar Espacio' : 'Crear Nuevo Espacio de Trabajo (Bolsa de Límites)'}
                            </h3>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="space-y-2">
                                    <label className="text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">Nombre Comercial de la Empresa / Espacio</label>
                                    <input
                                        type="text"
                                        value={formData.nombre}
                                        onChange={e => setFormData({ ...formData, nombre: e.target.value })}
                                        className="w-full px-4 py-4 bg-slate-950 border border-slate-800 rounded-xl text-base focus:ring-2 focus:ring-amber-500 outline-none font-medium transition-all text-white"
                                        placeholder="Ej: Interdata Software S.A."
                                        required
                                        autoFocus
                                    />
                                </div>

                                <div className="flex flex-col gap-4 pt-6 mt-8 border-t border-slate-800">
                                    <button
                                        type="submit"
                                        disabled={loading}
                                        className="w-full py-4 text-base font-bold text-slate-950 bg-amber-500 hover:bg-amber-400 rounded-xl shadow-lg shadow-amber-500/20 transition-all disabled:opacity-70"
                                    >
                                        {loading ? 'Preparando...' : editingOrg ? 'Guardar Cambios' : 'Crear y Guardar Espacio'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setView('list')}
                                        className="w-full py-4 text-sm font-bold text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-all"
                                    >
                                        Cancelar Operación
                                    </button>
                                </div>
                            </form>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default BackofficeTenants;
