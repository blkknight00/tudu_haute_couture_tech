import { useState, useEffect } from 'react';
import { X, Save } from 'lucide-react';
import api from '../../api/axios';
import { useAuth } from '../../contexts/AuthContext';

interface Organization {
    id: number;
    nombre: string;
}

interface ProjectModalProps {
    isOpen: boolean;
    onClose: () => void;
    project?: any;
    onSave: () => void;
}

const ProjectModal = ({ isOpen, onClose, project, onSave }: ProjectModalProps) => {
    const { user: currentUser } = useAuth();
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [organizations, setOrganizations] = useState<Organization[]>([]);
    const [selectedOrgId, setSelectedOrgId] = useState('');
    const [visibility, setVisibility] = useState<'public' | 'private'>('public');
    const [isLoading, setIsLoading] = useState(false);

    const isGlobalAdmin = currentUser?.rol === 'super_admin' || currentUser?.rol === 'admin_global';

    useEffect(() => {
        if (isOpen) {
            if (project) {
                setName(project.nombre);
                setDescription(project.descripcion || '');
                setSelectedOrgId(project.organizacion_id?.toString() || '');
                setVisibility((project.user_id && project.user_id > 0) ? 'private' : 'public');
            } else {
                setName('');
                setDescription('');
                setSelectedOrgId(currentUser?.organizacion_id?.toString() || '');
                setVisibility('public');
            }

            if (isGlobalAdmin) {
                fetchOrganizations();
            }
        }
    }, [isOpen, project, isGlobalAdmin]);

    const fetchOrganizations = async () => {
        try {
            const res = await api.get('/organizations.php?action=list');
            if (res.data.status === 'success') {
                setOrganizations(res.data.data);
            }
        } catch (error) {
            console.error('Error fetching organizations:', error);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);

        try {
            await api.post('/projects.php', {
                id: project?.id,
                nombre: name,
                descripcion: description,
                organizacion_id: selectedOrgId || undefined,
                visibilidad: visibility
            });
            onSave();

            // Dispatch global event so ActionBar and other listeners can reload
            window.dispatchEvent(new CustomEvent('project-saved'));

            onClose();
        } catch (error) {
            console.error('Error saving project:', error);
        } finally {
            setIsLoading(false);
        }
    };

    if (!isOpen) return null;

    return (
        <div
            className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            onClick={onClose}
        >
            <div
                className="bg-white dark:bg-tudu-column-dark rounded-xl shadow-2xl w-full max-w-md transform transition-all border border-gray-200 dark:border-gray-700 overflow-hidden"
                onClick={(e) => e.stopPropagation()}
            >

                {/* Header */}
                <div className="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 className="text-xl font-bold text-tudu-text-light dark:text-tudu-text-dark">
                        {project ? 'Editar Proyecto' : 'Nuevo Proyecto'}
                    </h2>
                    <button onClick={onClose} className="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                        <X size={24} />
                    </button>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="p-4 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
                    <div>
                        <label className="block text-sm font-medium text-tudu-text-muted dark:text-tudu-text-muted-dark mb-1">
                            Nombre del Proyecto
                        </label>
                        <input
                            type="text"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            required
                            className="w-full px-3 py-2 bg-gray-50 dark:bg-tudu-bg-dark border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-tudu-accent focus:border-transparent outline-none transition-all dark:text-white"
                            placeholder="Ej. Rediseño Web"
                        />
                    </div>

                    {isGlobalAdmin && (
                        <div>
                            <label className="block text-sm font-medium text-tudu-text-muted dark:text-tudu-text-muted-dark mb-1">
                                Organización / Empresa
                            </label>
                            <select
                                value={selectedOrgId}
                                onChange={(e) => setSelectedOrgId(e.target.value)}
                                className="w-full px-3 py-2 bg-gray-50 dark:bg-tudu-bg-dark border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-tudu-accent focus:border-transparent outline-none transition-all dark:text-white"
                                required
                            >
                                <option value="">Seleccionar Empresa...</option>
                                {organizations.map(org => (
                                    <option key={org.id} value={org.id}>{org.nombre}</option>
                                ))}
                            </select>
                        </div>
                    )}

                    <div>
                        <label className="block text-sm font-medium text-tudu-text-muted dark:text-tudu-text-muted-dark mb-1">
                            Visibilidad
                        </label>
                        <select
                            value={visibility}
                            onChange={(e) => setVisibility(e.target.value as 'public' | 'private')}
                            className="w-full px-3 py-2 bg-gray-50 dark:bg-tudu-bg-dark border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-tudu-accent focus:border-transparent outline-none transition-all dark:text-white"
                        >
                            <option value="public">Público (Visible para la empresa)</option>
                            <option value="private">Privado (Solo yo)</option>
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-tudu-text-muted dark:text-tudu-text-muted-dark mb-1">
                            Descripción (Opcional)
                        </label>
                        <textarea
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            rows={3}
                            className="w-full px-3 py-2 bg-gray-50 dark:bg-tudu-bg-dark border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-tudu-accent focus:border-transparent outline-none transition-all dark:text-white resize-none"
                            placeholder="Detalles sobre el proyecto..."
                        />
                    </div>
                </form>

                {/* Footer */}
                <div className="flex justify-end gap-3 p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-tudu-bg-dark/50 rounded-b-xl">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        onClick={handleSubmit}
                        disabled={isLoading}
                        className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-tudu-accent hover:bg-tudu-accent-hover rounded-lg shadow-md transition-all transform active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Save size={18} />
                        {isLoading ? 'Guardando...' : 'Guardar Proyecto'}
                    </button>
                </div>
            </div>
        </div>
    );
};

export default ProjectModal;
