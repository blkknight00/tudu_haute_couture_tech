import { useState, useEffect } from 'react';
import api from '../../api/axios';
import { X, FolderPlus, Search, Edit2, Trash2, CheckCircle, XCircle, Folder } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { useProjectModal } from '../../contexts/ProjectModalContext';

interface Project {
    id: number;
    nombre: string;
    descripcion: string;
    user_id: number;
    fecha_creacion: string;
    organizacion_id: number | null;
    organizacion_nombre: string | null;
    total_tasks: number;
    creador_username?: string;
    creador_nombre?: string;
}

interface ProjectManagementModalProps {
    isOpen: boolean;
    onClose: () => void;
}

const ProjectManagementModal = ({ isOpen, onClose }: ProjectManagementModalProps) => {
    const { user: currentUser } = useAuth();
    const { openNewProjectModal, openEditProjectModal, setRefreshProjects } = useProjectModal();
    const [projects, setProjects] = useState<Project[]>([]);
    const [loading, setLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);

    const isGlobalAdmin = currentUser?.rol === 'super_admin' || currentUser?.rol === 'admin_global';

    const fetchProjects = async () => {
        setLoading(true);
        try {
            const res = await api.get('/projects.php');
            if (res.data.status === 'success') {
                setProjects(res.data.data);
            }
        } catch (error) {
            console.error('Error fetching projects:', error);
            setMessage({ type: 'error', text: 'Error al cargar proyectos.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (isOpen) {
            fetchProjects();
            setMessage(null);
            // Register this fetch function as the refresh trigger for the project modal context
            setRefreshProjects(fetchProjects);
        }
    }, [isOpen]);

    const handleDeleteClick = async (projectId: number) => {
        if (!confirm('¿Estás seguro de eliminar este proyecto?')) return;
        try {
            await api.delete(`/projects.php?id=${projectId}`);
            fetchProjects();
            setMessage({ type: 'success', text: 'Proyecto eliminado.' });
        } catch (error) {
            setMessage({ type: 'error', text: 'Error al eliminar proyecto.' });
        }
    };

    const filteredProjects = projects.filter(p => {
        const matchesName = p.nombre?.toLowerCase().includes(searchTerm.toLowerCase()) || false;
        const orgName = p.organizacion_nombre || '';
        const matchesOrg = orgName.toLowerCase().includes(searchTerm.toLowerCase());
        return matchesName || matchesOrg;
    });

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fade-in" onClick={onClose}>
            <div
                className="bg-white dark:bg-tudu-content-dark w-full max-w-5xl h-[80vh] rounded-2xl shadow-2xl overflow-hidden flex flex-col"
                onClick={e => e.stopPropagation()}
            >
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 className="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <Folder className="text-tudu-accent" />
                        Gestión de Proyectos
                    </h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-white transition-colors">
                        <X size={24} />
                    </button>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-hidden flex flex-col p-6">
                    {message && (
                        <div className={`mb-4 p-3 rounded-lg flex items-center gap-2 text-sm ${message.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}>
                            {message.type === 'success' ? <CheckCircle size={16} /> : <XCircle size={16} />}
                            {message.text}
                        </div>
                    )}

                    <div className="flex flex-col sm:flex-row gap-4 mb-6 justify-between">
                        <div className="relative flex-1 max-w-md">
                            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={18} />
                            <input
                                type="text"
                                placeholder="Buscar proyectos o empresas..."
                                value={searchTerm}
                                onChange={e => setSearchTerm(e.target.value)}
                                className="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-tudu-accent outline-none"
                            />
                        </div>
                        <button
                            onClick={() => openNewProjectModal()}
                            className="flex items-center gap-2 bg-tudu-accent hover:bg-tudu-accent-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
                        >
                            <FolderPlus size={18} /> Nuevo Proyecto
                        </button>
                    </div>

                    <div className="flex-1 overflow-y-auto custom-scrollbar bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700">
                        <table className="w-full text-left border-collapse">
                            <thead className="bg-gray-100 dark:bg-gray-800 sticky top-0 z-10">
                                <tr>
                                    <th className="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Proyecto</th>
                                    <th className="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tipo</th>
                                    {isGlobalAdmin && (
                                        <th className="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Empresa</th>
                                    )}
                                    <th className="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Tareas</th>
                                    <th className="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                {loading ? (
                                    <tr>
                                        <td colSpan={isGlobalAdmin ? 5 : 4} className="px-6 py-10 text-center text-sm text-gray-500">
                                            Cargando proyectos...
                                        </td>
                                    </tr>
                                ) : filteredProjects.length === 0 ? (
                                    <tr>
                                        <td colSpan={isGlobalAdmin ? 5 : 4} className="px-6 py-10 text-center text-sm text-gray-500">
                                            No se encontraron proyectos.
                                        </td>
                                    </tr>
                                ) : (
                                    filteredProjects.map(p => (
                                        <tr key={p.id} className="hover:bg-white dark:hover:bg-gray-700/50 transition-colors group">
                                            <td className="px-6 py-4">
                                                <div className="text-sm font-medium text-gray-900 dark:text-white">{p.nombre}</div>
                                                <div className="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs">{p.descripcion || 'Sin descripción'}</div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    ${p.user_id === 0 ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800'}`}>
                                                    {p.user_id === 0 ? 'Público' : 'Privado'}
                                                </span>
                                            </td>
                                            {isGlobalAdmin && (
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="text-sm text-gray-600 dark:text-gray-300">
                                                        {p.organizacion_nombre || '-'}
                                                    </span>
                                                </td>
                                            )}
                                            <td className="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500 dark:text-gray-400">
                                                {p.total_tasks}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <div className="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button title="Editar" onClick={() => openEditProjectModal(p)} className="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                                        <Edit2 size={16} />
                                                    </button>
                                                    <button title="Eliminar" onClick={() => handleDeleteClick(p.id)} className="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                                        <Trash2 size={16} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ProjectManagementModal;
