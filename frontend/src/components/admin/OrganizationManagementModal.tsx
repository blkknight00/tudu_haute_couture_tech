import { useState, useEffect } from 'react';
import api from '../../api/axios';
import { X, Building2, Search, Edit2, Trash2, CheckCircle, XCircle, Plus } from 'lucide-react';

interface Organization {
    id: number;
    nombre: string;
    plan_status?: string;
    created_at?: string;
    deepseek_api_key?: string;
}

interface OrganizationManagementModalProps {
    isOpen: boolean;
    onClose: () => void;
}

const OrganizationManagementModal = ({ isOpen, onClose }: OrganizationManagementModalProps) => {
    const [organizations, setOrganizations] = useState<Organization[]>([]);
    const [loading, setLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [view, setView] = useState<'list' | 'form'>('list');
    const [editingOrg, setEditingOrg] = useState<Organization | null>(null);
    const [formData, setFormData] = useState({
        nombre: '',
        deepseek_api_key: ''
    });
    const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);

    useEffect(() => {
        if (isOpen) {
            fetchOrganizations();
            setView('list');
            setMessage(null);
        }
    }, [isOpen]);

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
        setFormData({ nombre: '', deepseek_api_key: '' });
        setView('form');
        setMessage(null);
    };

    const handleEditClick = (org: Organization) => {
        setEditingOrg(org);
        setFormData({ nombre: org.nombre, deepseek_api_key: org.deepseek_api_key || '' });
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
        if (!confirm('¿Quieres alternar el estado Lifetime para esta cuenta?')) return;

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
                nombre: formData.nombre,
                deepseek_api_key: formData.deepseek_api_key || null
            });
            console.log('Save Org Response:', res.data);

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

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fade-in" onClick={onClose}>
            <div
                className="bg-white dark:bg-tudu-content-dark w-full max-w-4xl h-[70vh] rounded-2xl shadow-2xl overflow-hidden flex flex-col"
                onClick={e => e.stopPropagation()}
            >
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-tudu-accent/5 to-transparent">
                    <h2 className="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <Building2 className="text-tudu-accent" />
                        Gestión de Empresas / Organizaciones
                    </h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-white transition-colors">
                        <X size={24} />
                    </button>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-hidden flex flex-col p-6">
                    {message && (
                        <div className={`mb-4 p-3 rounded-xl flex items-center gap-2 text-sm animate-shake ${message.type === 'success' ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-red-50 text-red-700 border border-red-100'}`}>
                            {message.type === 'success' ? <CheckCircle size={16} /> : <XCircle size={16} />}
                            {message.text}
                        </div>
                    )}

                    {view === 'list' ? (
                        <>
                            <div className="flex flex-col sm:flex-row gap-4 mb-6 justify-between">
                                <div className="relative flex-1 max-w-md">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={18} />
                                    <input
                                        type="text"
                                        placeholder="Buscar empresas..."
                                        value={searchTerm}
                                        onChange={e => setSearchTerm(e.target.value)}
                                        className="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-600 rounded-xl text-sm focus:ring-2 focus:ring-tudu-accent outline-none font-medium transition-all"
                                    />
                                </div>
                                <button
                                    onClick={handleCreateClick}
                                    className="flex items-center gap-2 bg-tudu-accent hover:bg-tudu-accent-hover text-white px-5 py-2 rounded-xl text-sm font-bold transition-all shadow-lg shadow-tudu-accent/20"
                                >
                                    <Plus size={18} /> Nueva Empresa
                                </button>
                            </div>

                            <div className="flex-1 overflow-y-auto custom-scrollbar border border-gray-100 dark:border-gray-700 rounded-2xl bg-gray-50/30 dark:bg-gray-800/20">
                                <table className="w-full text-left border-collapse">
                                    <thead className="bg-white dark:bg-tudu-content-dark sticky top-0 z-10 border-b border-gray-100 dark:border-gray-700">
                                        <tr>
                                            <th className="px-6 py-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th>
                                            <th className="px-6 py-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nombre de la Empresa</th>
                                            <th className="px-6 py-4 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                        {loading && organizations.length === 0 ? (
                                            Array.from({ length: 3 }).map((_, i) => (
                                                <tr key={i} className="animate-pulse">
                                                    <td colSpan={3} className="px-6 py-4 h-16 bg-white dark:bg-transparent"></td>
                                                </tr>
                                            ))
                                        ) : filteredOrgs.length === 0 ? (
                                            <tr>
                                                <td colSpan={3} className="px-6 py-10 text-center text-gray-500 dark:text-gray-400 italic">
                                                    No se encontraron empresas.
                                                </td>
                                            </tr>
                                        ) : (
                                            filteredOrgs.map(org => (
                                                <tr key={org.id} className="hover:bg-white dark:hover:bg-gray-800/40 transition-colors group">
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-400">
                                                        #{org.id}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="flex items-center gap-3">
                                                            <div className="w-8 h-8 rounded-lg bg-tudu-accent/10 flex items-center justify-center">
                                                                <Building2 size={16} className="text-tudu-accent" />
                                                            </div>
                                                            <span className="text-sm font-semibold text-gray-800 dark:text-white">{org.nombre}</span>
                                                            {org.id === 1 && <span className="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded uppercase font-bold">Principal</span>}
                                                            {org.plan_status === 'lifetime' && <span className="text-[10px] bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400 px-1.5 py-0.5 rounded uppercase font-bold">Lifetime / Free</span>}
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                        <div className="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                            <button title="Editar" onClick={() => handleEditClick(org)} className="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-xl transition-colors">
                                                                <Edit2 size={18} />
                                                            </button>
                                                            {org.id !== 1 && (
                                                                <>
                                                                    <button title={org.plan_status === 'lifetime' ? 'Revocar God Mode' : 'Activar God Mode (Lifetime Gratis)'} onClick={() => handleToggleLifetime(org.id)} className={`p-2 rounded-xl transition-colors ${org.plan_status === 'lifetime' ? 'text-amber-600 hover:bg-amber-50' : 'text-gray-400 hover:text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/30'}`}>
                                                                        <CheckCircle size={18} />
                                                                    </button>
                                                                    <button title="Eliminar" onClick={() => handleDeleteClick(org.id)} className="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-xl transition-colors">
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
                        <div className="max-w-md mx-auto w-full flex-1 flex flex-col justify-center">
                            <h3 className="text-xl font-bold text-gray-800 dark:text-white mb-8 text-center">
                                {editingOrg ? 'Editar Empresa' : 'Nueva Empresa'}
                            </h3>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="space-y-2">
                                    <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider ml-1">Nombre Comercial</label>
                                    <input
                                        type="text"
                                        value={formData.nombre}
                                        onChange={e => setFormData({ ...formData, nombre: e.target.value })}
                                        className="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl text-sm focus:ring-2 focus:ring-tudu-accent outline-none font-medium transition-all"
                                        placeholder="Ej: Mi Nueva Empresa S.A."
                                        required
                                        autoFocus
                                    />
                                </div>

                                <div className="space-y-2">
                                    <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider ml-1">DeepSeek API Key Corporativa (Opcional)</label>
                                    <input
                                        type="password"
                                        value={formData.deepseek_api_key}
                                        onChange={e => setFormData({ ...formData, deepseek_api_key: e.target.value })}
                                        className="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none font-medium transition-all font-mono"
                                        placeholder="sk-..."
                                    />
                                    <p className="text-[10px] text-gray-400 ml-1">Si dejas esto en blanco, se usará la API Key global del sistema.</p>
                                </div>

                                <div className="flex flex-col gap-3 pt-4">
                                    <button
                                        type="submit"
                                        disabled={loading}
                                        className="w-full py-3 text-sm font-bold text-white bg-tudu-accent hover:bg-tudu-accent-hover rounded-xl shadow-lg shadow-tudu-accent/20 transition-all disabled:opacity-70"
                                    >
                                        {loading ? 'Guardando...' : editingOrg ? 'Actualizar Empresa' : 'Crear Empresa'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setView('list')}
                                        className="w-full py-3 text-sm font-bold text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 rounded-xl transition-all"
                                    >
                                        Cancelar
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

export default OrganizationManagementModal;
