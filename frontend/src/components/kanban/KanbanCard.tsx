import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Clock, MessageSquare, Paperclip } from 'lucide-react'; // Added Paperclip
import clsx from 'clsx';
import { useTaskModal } from '../../contexts/TaskModalContext';
import { BASE_URL } from '../../api/axios';

interface Task {
    id: number;
    titulo: string;
    descripcion?: string;
    proyecto_nombre?: string;
    prioridad: 'alta' | 'media' | 'baja';
    fecha_termino?: string;
    asignados?: { username: string; foto_perfil: string | null }[];
    files_count?: number;
    comments_count?: number;
    estado: string;
}

interface KanbanCardProps {
    task: Task;
    isOverlay?: boolean;
}

const KanbanCard = ({ task, isOverlay = false }: KanbanCardProps) => {
    const { openEditTaskModal } = useTaskModal();

    // Call useSortable only if not an overlay
    const sortable = useSortable({ id: task.id, disabled: isOverlay });
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging
    } = sortable;

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging && !isOverlay ? 0.3 : 1,
    };

    const statusColors: Record<string, string> = {
        'completado': '#22c55e', // green-500
        'en_progreso': '#eab308', // yellow-500
        'pendiente': '#ef4444' // red-500
    };

    const isOverdue = (() => {
        if (!task.fecha_termino || task.estado === 'completado') return false;
        const todayStr = new Date().toISOString().split('T')[0];
        const dueStr = task.fecha_termino.split('T')[0];
        return dueStr < todayStr;
    })();

    return (
        <div
            ref={setNodeRef}
            style={{
                ...style,
                borderLeftColor: statusColors[(task.estado || '').toLowerCase()] || '#ef4444'
            }}
            {...attributes}
            {...listeners}
            onClick={() => openEditTaskModal(task)}
            className={clsx(
                "bg-white/80 dark:bg-tudu-content-dark/80 backdrop-blur-md p-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-3 cursor-grab hover:shadow-md transition-shadow select-none",
                "border-l-4"
            )}
        >
            <div className="flex justify-between items-start mb-2">
                <span className="text-xs font-semibold text-tudu-text-muted uppercase tracking-wider">
                    {task.proyecto_nombre || 'Sin Proyecto'}
                </span>
                {task.fecha_termino && (
                    <div className="relative">
                        {isOverdue && (
                            <span className="absolute -top-1 -right-1 flex h-3 w-3 z-10">
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span className="relative inline-flex rounded-full h-3 w-3 bg-red-600"></span>
                            </span>
                        )}
                        <span className={clsx(
                            "flex items-center text-xs font-medium px-2 py-0.5 rounded-full border",
                            (() => {
                                const todayStr = new Date().toISOString().split('T')[0];
                                const dueStr = task.fecha_termino.split('T')[0];

                                if (dueStr < todayStr && task.estado !== 'completado') return "text-red-600 bg-red-50 border-red-100 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400"; // Overdue
                                if (dueStr === todayStr && task.estado !== 'completado') return "text-orange-600 bg-orange-50 border-orange-100 dark:bg-orange-900/20 dark:border-orange-800 dark:text-orange-400"; // Today
                                return "text-gray-500 bg-gray-50 border-gray-100 dark:text-gray-400 dark:bg-gray-800 dark:border-gray-700"; // Future
                            })()
                        )}>
                            <Clock size={11} className="mr-1" />
                            {new Date(task.fecha_termino + 'T00:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })}
                        </span>
                    </div>
                )}
            </div>

            <h4 className="text-sm font-semibold text-tudu-text-light dark:text-tudu-text-dark mb-2 line-clamp-2">
                {task.titulo}
                {Number(task.files_count || 0) > 0 && (
                    <Paperclip size={14} className="inline ml-1 text-gray-400" />
                )}
            </h4>

            <div className="flex items-center justify-between mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                <div className="flex -space-x-2">
                    {task.asignados && task.asignados.length > 0 ? (
                        task.asignados.map((user, idx) => (
                            <div key={idx} className="w-6 h-6 rounded-full bg-gray-200 border-2 border-white dark:border-gray-700 overflow-hidden" title={user.username}>
                                {user.foto_perfil ? (
                                    <img
                                        src={user.foto_perfil.startsWith('http') ? user.foto_perfil : `${BASE_URL}/${user.foto_perfil}`}
                                        alt={user.username}
                                    />
                                ) : (
                                    <div className="w-full h-full bg-tudu-accent text-[10px] text-white flex items-center justify-center">
                                        {user.username[0]}
                                    </div>
                                )}
                            </div>
                        ))
                    ) : (
                        <div className="w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-800 border-2 border-dashed border-gray-300 flex items-center justify-center">
                            <span className="text-xs text-gray-400">+</span>
                        </div>
                    )}
                </div>

                <div className="flex gap-3 text-gray-400">
                    {Number(task.files_count || 0) > 0 && (
                        <div className="flex items-center text-xs text-purple-500" title={`${task.files_count} archivos`}>
                            <Paperclip size={14} className="mr-1" />
                            <span>{task.files_count}</span>
                        </div>
                    )}
                    <div className="flex items-center text-xs">
                        <MessageSquare size={14} className="mr-1" />
                        <span>{task.comments_count || 0}</span>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default KanbanCard;
