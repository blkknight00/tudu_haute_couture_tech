import React, { useEffect, useState, useRef } from 'react';
import api from '../../api/axios';
import { useTaskModal } from '../../contexts/TaskModalContext';
import { useProjectFilter } from '../../contexts/ProjectFilterContext';
import { format, isValid } from 'date-fns';
import { es } from 'date-fns/locale';
import {
    Folder,
    MessageSquare,
    Paperclip,
    Calendar,
    Share2,
    Archive,
    Trash2,
    MessageCircle, // For WhatsApp
    Clock,
    X
} from 'lucide-react';
import ShareModal from './ShareModal';
import FilterBar from './FilterBar';

interface Task {
    id: number;
    titulo: string;
    descripcion: string;
    estado: string;
    prioridad: string;
    fecha_termino: string | null;
    fecha_creacion: string;
    proyecto_nombre: string | null;
    visibility: 'public' | 'private';
    comments_count: number;
    files_count: number;
    assignees: any[];
    tags: { id: number; nombre: string; color: string }[];
}

interface User {
    id: number;
    nombre: string;
    telefono: string | null;
}

const TaskList: React.FC = () => {
    const [tasks, setTasks] = useState<Task[]>([]);
    const [users, setUsers] = useState<User[]>([]);
    const [loading, setLoading] = useState(true);
    // const [error, setError] = useState<string | null>(null);
    const { openEditTaskModal } = useTaskModal();
    const { projectType, selectedProjectId } = useProjectFilter();

    // Filters State
    const [filters, setFilters] = useState({
        status: 'todos',
        designer: '',
        priority: 'todas',
        date: '',
        search: ''
    });

    // WhatsApp Menu State
    const [waMenuOpenId, setWaMenuOpenId] = useState<number | null>(null);
    const waMenuRef = useRef<HTMLDivElement>(null);

    // Share Modal State
    const [shareModalOpen, setShareModalOpen] = useState(false);
    const [shareTaskData, setShareTaskData] = useState<{ id: number, title: string } | null>(null);

    // Close menu when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (waMenuRef.current && !waMenuRef.current.contains(event.target as Node)) {
                setWaMenuOpenId(null);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);


    // Fetch Tasks & Users
    useEffect(() => {
        const fetchData = async () => {
            setLoading(true);
            try {
                // 1. Fetch Tasks with Filters
                let taskUrl = `/tasks.php?view=${projectType}&t=${Date.now()}`;

                if (selectedProjectId) {
                    taskUrl += `&proyecto_id=${selectedProjectId}`;
                }

                // Add filters to URL
                if (filters.status !== 'todos') taskUrl += `&estado=${filters.status}`;
                if (filters.priority !== 'todas') taskUrl += `&prioridad=${filters.priority}`;
                if (filters.designer !== '') taskUrl += `&usuario_id_filtro=${filters.designer}`;
                if (filters.date !== '') taskUrl += `&fecha_vencimiento=${filters.date}`;
                if (filters.search !== '') taskUrl += `&search=${encodeURIComponent(filters.search)}`;

                const taskRes = await api.get(taskUrl);

                if (taskRes.data && taskRes.data.status === 'success') {
                    const raw: Task[] = Array.isArray(taskRes.data.tasks) ? taskRes.data.tasks : [];
                    // Deduplicate by id (guard against backend JOIN duplicates)
                    const seen = new Map<number, Task>();
                    raw.forEach(t => { if (!seen.has(t.id)) seen.set(t.id, t); });
                    setTasks(Array.from(seen.values()));
                } else {
                    console.warn("API Error (Tasks):", taskRes.data);
                    setTasks([]);
                }

                // 2. Fetch Users (for WhatsApp and Designer filter) - only if not already fetched
                if (users.length === 0) {
                    const userRes = await api.get('/get_options.php');
                    if (userRes.data && userRes.data.users) {
                        setUsers(userRes.data.users);
                    }
                }

            } catch (error) {
                console.error("Error fetching data:", error);
            } finally {
                setLoading(false);
            }
        };

        fetchData();
    }, [projectType, selectedProjectId, filters]);

    // Helpers
    const formatDateSafe = (dateString: string | null, formatStr: string = 'd MMM') => {
        if (!dateString) return null;
        const date = new Date(dateString);
        return isValid(date) ? format(date, formatStr, { locale: es }) : null;
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'completado': return '#22c55e'; // green-500
            case 'en_progreso': return '#eab308'; // yellow-500
            default: return '#ef4444'; // red-500
        }
    };

    // Actions
    const handleArchive = async (e: React.MouseEvent, id: number) => {
        e.stopPropagation();
        if (!window.confirm("¿Archivar esta tarea?")) return;
        try {
            await api.post('/archivar_tarea.php', { tarea_id: id });
            setTasks(prev => prev.filter(t => t.id !== id));
        } catch (err: any) {
            console.error(err);
            alert("Error al archivar: " + (err.response?.data?.message || err.message));
        }
    };

    const handleDelete = async (e: React.MouseEvent, id: number) => {
        e.stopPropagation();
        if (!window.confirm("¿Eliminar tarea permanentemente?")) return;
        try {
            await api.get(`/eliminar_tarea.php?id=${id}`);
            setTasks(prev => prev.filter(t => t.id !== id));
        } catch (err: any) {
            alert("Error al eliminar: " + (err.response?.data?.message || err.message));
        }
    };

    const handleShare = (e: React.MouseEvent, task: Task) => {
        e.stopPropagation();
        setShareTaskData({ id: task.id, title: task.titulo });
        setShareModalOpen(true);
    };

    const toggleWaMenu = (e: React.MouseEvent, taskId: number) => {
        e.stopPropagation();
        setWaMenuOpenId(waMenuOpenId === taskId ? null : taskId);
    };

    const sendWhatsAppToUser = async (e: React.MouseEvent, user: User, task: Task) => {
        e.stopPropagation(); // prevent modal open
        if (!user.telefono) {
            alert("Este usuario no tiene teléfono registrado.");
            return;
        }

        try {
            // Fetch public link
            const res = await api.get(`/generar_share_link.php?id=${task.id}`);

            if (res.data && res.data.success) {
                const url = res.data.url;

                let text = `Hola ${user.nombre}, te comparto la tarea: '${task.titulo}' para que la revises.\n\n`;
                text += `*Ver Tarea*: ${url}`;

                // Strip non-numeric chars from phone
                const cleanPhone = user.telefono.replace(/[^0-9]/g, '');
                window.open(`https://wa.me/${cleanPhone}?text=${encodeURIComponent(text)}`, '_blank');
                setWaMenuOpenId(null);
            } else {
                alert("Error obteniendo enlace para compartir.");
            }
        } catch (err) {
            console.error(err);
            alert("No se pudo conectar con el servidor para generar el enlace.");
        }
    };

    if (loading) return <div className="p-8 text-center text-gray-400">Cargando...</div>;

    return (
        <div className="flex flex-col gap-4 pb-20"> {/* pb-20 for safe scroll */}
            <div className="flex justify-between items-center mb-2 px-1">
                <h3 className="font-semibold text-lg text-gray-800 dark:text-white">Lista de Tareas</h3>
                <span className="text-xs text-gray-500 bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded-full">{tasks.length} visibles</span>
            </div>

            <FilterBar
                filters={filters}
                setFilters={setFilters}
                users={users}
            />

            {tasks.length === 0 ? (
                <div className="text-center py-10 bg-white/85 dark:bg-tudu-content-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10 rounded-xl border border-gray-100 dark:border-gray-700">
                    <p className="text-gray-400">No hay tareas para mostrar.</p>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4">
                    {tasks.map(task => (
                        <div
                            key={task.id}
                            onClick={() => openEditTaskModal(task)}
                            className={`
                                group relative bg-white/85 dark:bg-tudu-content-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10 
                                rounded-lg shadow-sm hover:shadow-md transition-all duration-200 
                                border border-gray-100 dark:border-gray-700
                                border-l-[4px] p-4 cursor-pointer
                            `}
                            style={{ borderLeftColor: getStatusColor(task.estado) }}
                        >
                            {/* Project Name */}
                            <div className="flex items-center gap-2 mb-2 text-xs text-gray-500 font-medium">
                                <Folder size={12} className="text-tudu-accent" />
                                <span className="uppercase tracking-wide">{task.proyecto_nombre || 'Sin Proyecto'}</span>
                            </div>

                            {/* Title */}
                            <h4 className="text-base font-bold text-gray-800 dark:text-gray-100 mb-2 leading-tight group-hover:text-tudu-accent transition-colors">
                                {task.titulo}
                            </h4>

                            {/* Tags */}
                            {task.tags && task.tags.length > 0 && (() => {
                                // Deduplicate tags by id before rendering
                                const uniqueTags = Array.from(
                                    new Map(task.tags.map(t => [t.id, t])).values()
                                );
                                return (
                                    <div className="flex flex-wrap gap-1 mb-3">
                                        {uniqueTags.map(tag => (
                                            <span
                                                key={`task-${task.id}-tag-${tag.id}`}
                                                className="px-2 py-0.5 rounded-full text-[10px] font-bold text-white shadow-sm"
                                                style={{ backgroundColor: tag.color || '#6B7280' }}
                                            >
                                                {tag.nombre}
                                            </span>
                                        ))}
                                    </div>
                                );
                            })()}

                            {/* Dates */}
                            <div className="flex flex-wrap items-center gap-4 text-xs text-gray-500 mb-4">
                                <div className="flex items-center gap-1" title="Fecha de Creación">
                                    <Clock size={13} className="text-gray-400" />
                                    <span>{formatDateSafe(task.fecha_creacion, 'd MMM HH:mm')}</span>
                                </div>

                                {task.fecha_termino && (() => {
                                    const isOverdue = new Date(task.fecha_termino).getTime() < Date.now() && task.estado !== 'completado';
                                    return (
                                        <div className={`relative flex items-center gap-1 ${isOverdue ? 'text-red-500 font-bold' : ''}`} title="Vencimiento">
                                            {isOverdue && (
                                                <span className="absolute -top-1 -right-3 flex h-3 w-3 z-10">
                                                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                                    <span className="relative inline-flex rounded-full h-3 w-3 bg-red-600"></span>
                                                </span>
                                            )}
                                            <Calendar size={13} />
                                            <span>{formatDateSafe(task.fecha_termino, 'd MMM HH:mm')}</span>
                                        </div>
                                    );
                                })()}
                            </div>

                            {/* Footer: Notes & Actions */}
                            <div className="flex justify-between items-end border-t border-gray-50 dark:border-gray-700/50 pt-3 mt-1 relative">
                                {/* Left: Notes / Files Indicators */}
                                <div className="flex gap-3">
                                    {task.comments_count > 0 && (
                                        <div className="flex items-center gap-1 text-xs text-blue-500 font-medium bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded">
                                            <MessageSquare size={12} />
                                            <span>{task.comments_count}</span>
                                        </div>
                                    )}
                                    {Number(task.files_count || 0) > 0 && (
                                        <div className="flex items-center gap-1 text-xs text-purple-500 font-medium bg-purple-50 dark:bg-purple-900/20 px-2 py-1 rounded" title={`${task.files_count} archivos`}>
                                            <Paperclip size={12} />
                                            <span>{task.files_count}</span>
                                        </div>
                                    )}
                                </div>

                                {/* Right: Action Buttons */}
                                <div className="flex items-center gap-1 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100">
                                    <button
                                        onClick={(e) => handleShare(e, task)}
                                        className="p-1.5 text-gray-400 hover:text-blue-500 hover:bg-blue-50 rounded transition-colors"
                                        title="Compartir"
                                    >
                                        <Share2 size={16} />
                                    </button>

                                    {/* WhatsApp Button with Dropdown */}
                                    <div className="relative">
                                        <button
                                            onClick={(e) => toggleWaMenu(e, task.id)}
                                            className={`p-1.5 rounded transition-colors ${waMenuOpenId === task.id ? 'text-green-500 bg-green-50' : 'text-gray-400 hover:text-green-500 hover:bg-green-50'}`}
                                            title="Enviar WhatsApp"
                                        >
                                            <MessageCircle size={16} />
                                        </button>

                                        {/* Dropdown Menu */}
                                        {waMenuOpenId === task.id && (
                                            <div
                                                ref={waMenuRef}
                                                className="absolute right-0 bottom-full mb-2 w-56 bg-white/85 dark:bg-tudu-column-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 z-50 overflow-hidden animation-fade-in"
                                            >
                                                <div className="p-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                                                    <span className="text-xs font-semibold text-gray-500">Enviar a:</span>
                                                    <button onClick={(e) => { e.stopPropagation(); setWaMenuOpenId(null); }} className="text-gray-400 hover:text-gray-600"><X size={12} /></button>
                                                </div>
                                                <div className="max-h-48 overflow-y-auto custom-scrollbar">
                                                    {users.filter(u => u.telefono).length === 0 ? (
                                                        <div className="p-3 text-xs text-gray-400 text-center">No hay usuarios con teléfono.</div>
                                                    ) : (
                                                        users.filter(u => u.telefono).map(user => (
                                                            <button
                                                                key={user.id}
                                                                onClick={(e) => sendWhatsAppToUser(e, user, task)}
                                                                className="w-full text-left px-3 py-2 text-sm hover:bg-green-50 dark:hover:bg-green-900/20 text-gray-700 dark:text-gray-200 flex items-center gap-2 transition-colors"
                                                            >
                                                                <div className="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-xs">
                                                                    {user.nombre.charAt(0)}
                                                                </div>
                                                                <span className="truncate flex-1">{user.nombre}</span>
                                                                <MessageCircle size={12} className="text-green-500" />
                                                            </button>
                                                        ))
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    <button
                                        onClick={(e) => handleArchive(e, task.id)}
                                        className="p-1.5 text-gray-400 hover:text-amber-500 hover:bg-amber-50 rounded transition-colors"
                                        title="Archivar"
                                    >
                                        <Archive size={16} />
                                    </button>
                                    <button
                                        onClick={(e) => handleDelete(e, task.id)}
                                        className="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition-colors"
                                        title="Eliminar"
                                    >
                                        <Trash2 size={16} />
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Share Modal */}
            <ShareModal
                isOpen={shareModalOpen}
                onClose={() => setShareModalOpen(false)}
                taskId={shareTaskData?.id || null}
                taskTitle={shareTaskData?.title || ''}
            />
        </div>
    );
};

export default TaskList;
