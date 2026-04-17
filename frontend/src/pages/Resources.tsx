import { useState, useEffect } from 'react';
import api from '../api/axios';
import { FileText, Image, Film, Upload, Download, File, Archive, RefreshCw, Info } from 'lucide-react';

interface Resource {
    id: number;
    filename: string;
    filepath: string;
    filetype: string;
    size: number;
    created_at: string;
}

interface ArchivedTask {
    id: number;
    titulo: string;
    descripcion: string;
    fecha_creacion: string;
    proyecto_nombre: string | null;
}

type Tab = 'files' | 'tasks';

const Resources = () => {
    const [showInfo, setShowInfo] = useState(false);
    const [activeTab, setActiveTab] = useState<Tab>('files');
    const [resources, setResources] = useState<Resource[]>([]);
    const [archivedTasks, setArchivedTasks] = useState<ArchivedTask[]>([]);
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);

    useEffect(() => {
        if (activeTab === 'files') {
            fetchResources();
        } else {
            fetchArchivedTasks();
        }
    }, [activeTab]);

    const fetchResources = async () => {
        setLoading(true);
        try {
            const res = await api.get('/resources.php');
            if (res.data.status === 'success') {
                setResources(res.data.data);
            }
        } catch (error) {
            console.error('Error fetching resources:', error);
        } finally {
            setLoading(false);
        }
    };

    const fetchArchivedTasks = async () => {
        setLoading(true);
        try {
            const res = await api.get('/tasks.php?view=all&archived=true');
            if (res.data.status === 'success') {
                setArchivedTasks(res.data.tasks || []);
            }
        } catch (error) {
            console.error('Error fetching archived tasks:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
        if (!e.target.files || e.target.files.length === 0) return;

        const file = e.target.files[0];
        const formData = new FormData();
        formData.append('file', file);
        // formData.append('user_id', '1'); // Update with actual user ID context if needed

        setUploading(true);
        try {
            const res = await api.post('/resources.php', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            if (res.data.status === 'success') {
                fetchResources();
            } else {
                alert('Error subiendo archivo: ' + res.data.message);
            }
        } catch (error) {
            console.error('Error uploading file:', error);
            alert('Error al subir el archivo');
        } finally {
            setUploading(false);
        }
    };

    const handleRestoreTask = async (id: number) => {
        if (!window.confirm("¿Restaurar esta tarea al tablero?")) return;
        try {
            await api.post('/restaurar_tarea.php', { tarea_id: id });
            setArchivedTasks(prev => prev.filter(t => t.id !== id));
        } catch (error) {
            alert('Error al restaurar la tarea');
        }
    };

    const getFileIcon = (type: string) => {
        if (type.includes('image')) return <Image size={24} className="text-blue-500" />;
        if (type.includes('pdf')) return <FileText size={24} className="text-red-500" />;
        if (type.includes('video')) return <Film size={24} className="text-purple-500" />;
        return <File size={24} className="text-gray-500" />;
    };

    const formatSize = (bytes: number) => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    return (
        <div className="h-full flex flex-col">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h1 className="text-2xl font-bold text-tudu-text-light dark:text-white">Repositorio</h1>

                {/* Tabs */}
                <div className="bg-gray-100 dark:bg-tudu-column-dark p-1 rounded-lg flex">
                    <button
                        onClick={() => setActiveTab('files')}
                        className={`px-4 py-2 rounded-md text-sm font-medium transition-all ${activeTab === 'files'
                            ? 'bg-white dark:bg-tudu-bg-dark text-tudu-accent shadow-sm'
                            : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
                            }`}
                    >
                        Archivos
                    </button>
                    <button
                        onClick={() => setActiveTab('tasks')}
                        className={`px-4 py-2 rounded-md text-sm font-medium transition-all ${activeTab === 'tasks'
                            ? 'bg-white dark:bg-tudu-bg-dark text-tudu-accent shadow-sm'
                            : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
                            }`}
                    >
                        Tareas Archivadas
                    </button>
                </div>

                {/* Info Legend */}
                <div className="relative">
                    <button
                        onClick={() => setShowInfo(!showInfo)}
                        className="p-2 text-gray-400 hover:text-tudu-accent transition-colors"
                        title="Información"
                    >
                        <Info size={20} />
                    </button>
                    {showInfo && (
                        <div className="absolute right-0 top-10 z-10 w-80 bg-white/85 dark:bg-tudu-column-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10 p-5 rounded-xl shadow-xl border border-gray-200 dark:border-gray-600 text-sm transform transition-all animate-fade-in">
                            <h4 className="font-bold text-tudu-text-light dark:text-white mb-3 flex items-center gap-2 pb-2 border-b border-gray-100 dark:border-gray-700">
                                <Info size={16} /> Ayuda
                            </h4>
                            {activeTab === 'files' ? (
                                <div className="space-y-2 text-gray-600 dark:text-gray-300">
                                    <p><b>Archivos (Repositorio):</b></p>
                                    <ul className="list-disc pl-4 space-y-1 text-xs">
                                        <li>Sirve como una carpeta de almacenamiento ("nube") para tu proyecto.</li>
                                        <li>Aquí subes documentos generales, guías, contratos o imágenes que <b>no pertenecen a una tarea específica</b>.</li>
                                        <li>Todo el equipo puede acceder a ellos.</li>
                                    </ul>
                                </div>
                            ) : (
                                <div className="space-y-2 text-gray-600 dark:text-gray-300">
                                    <p><b>Tareas Archivadas:</b></p>
                                    <ul className="list-disc pl-4 space-y-1 text-xs">
                                        <li>Es el historial de tus tareas finalizadas o canceladas.</li>
                                        <li>Se guardan aquí en lugar de borrarse permanentemente.</li>
                                        <li>Puedes consultarlas o <b>Restaurarlas</b> al tablero activo si las necesitas de vuelta.</li>
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {activeTab === 'files' && (
                    <div>
                        <input
                            type="file"
                            id="file-upload"
                            className="hidden"
                            onChange={handleFileUpload}
                            disabled={uploading}
                        />
                        <label
                            htmlFor="file-upload"
                            className={`flex items-center gap-2 px-4 py-2 rounded-lg cursor-pointer transition-colors ${uploading
                                ? 'bg-gray-400 cursor-not-allowed text-white'
                                : 'bg-tudu-accent hover:bg-tudu-accent/90 text-white'
                                }`}
                        >
                            <Upload size={18} />
                            {uploading ? 'Subiendo...' : 'Subir Archivo'}
                        </label>
                    </div>
                )}
            </div>

            <div className="bg-white/85 dark:bg-tudu-content-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex-1 overflow-hidden flex flex-col">
                {loading ? (
                    <div className="flex-1 flex items-center justify-center">
                        <p className="text-gray-500">Cargando...</p>
                    </div>
                ) : activeTab === 'files' ? (
                    // Files View
                    resources.length === 0 ? (
                        <div className="flex-1 flex flex-col items-center justify-center text-gray-400">
                            <FileText size={48} className="mb-4 opacity-50" />
                            <p>No hay archivos en el repositorio.</p>
                            <p className="text-sm">Sube uno para empezar.</p>
                        </div>
                    ) : (
                        <div className="overflow-y-auto flex-1 p-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                {resources.map((resource) => (
                                    <div key={resource.id} className="group relative bg-gray-50 dark:bg-tudu-column-dark p-4 rounded-lg border border-gray-100 dark:border-gray-700 hover:shadow-md transition-shadow">
                                        <div className="flex items-start justify-between mb-3">
                                            <div className="p-2 bg-white dark:bg-tudu-bg-dark rounded-md shadow-sm">
                                                {getFileIcon(resource.filetype)}
                                            </div>
                                        </div>
                                        <div className="mb-2">
                                            <h3 className="font-medium text-tudu-text-light dark:text-white truncate" title={resource.filename}>
                                                {resource.filename}
                                            </h3>
                                            <p className="text-xs text-tudu-text-muted">
                                                {formatSize(resource.size)} • {new Date(resource.created_at).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <div className="mt-2 pt-2 border-t border-gray-200 dark:border-gray-600 flex justify-end">
                                            <a
                                                href={`http://localhost/tudu_development/uploads/${resource.filepath.split('/').pop()}`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-xs font-semibold text-tudu-accent hover:underline flex items-center gap-1"
                                            >
                                                <Download size={14} /> Descargar
                                            </a>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )
                ) : (
                    // Tasks View
                    archivedTasks.length === 0 ? (
                        <div className="flex-1 flex flex-col items-center justify-center text-gray-400">
                            <Archive size={48} className="mb-4 opacity-50" />
                            <p>No hay tareas archivadas.</p>
                        </div>
                    ) : (
                        <div className="overflow-y-auto flex-1 p-4">
                            <div className="flex flex-col gap-2">
                                {archivedTasks.map((task) => (
                                    <div key={task.id} className="flex items-center justify-between bg-gray-50 dark:bg-tudu-column-dark p-4 rounded-lg border border-gray-100 dark:border-gray-700">
                                        <div>
                                            <h3 className="font-semibold text-tudu-text-light dark:text-white">{task.titulo}</h3>
                                            <p className="text-xs text-tudu-text-muted mt-1">
                                                {task.proyecto_nombre ? `Proyecto: ${task.proyecto_nombre}` : 'Sin proyecto'} • Creada: {new Date(task.fecha_creacion).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <button
                                            onClick={() => handleRestoreTask(task.id)}
                                            className="p-2 text-gray-400 hover:text-green-500 hover:bg-green-50 rounded-lg transition-colors flex items-center gap-2"
                                            title="Restaurar Tarea"
                                        >
                                            <RefreshCw size={16} />
                                            <span className="text-xs font-medium hidden sm:inline">Restaurar</span>
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )
                )}
            </div>
        </div>
    );
};

export default Resources;
