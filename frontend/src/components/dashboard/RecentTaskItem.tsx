import { Calendar, Folder, Paperclip } from 'lucide-react';
import { format, isValid } from 'date-fns';
import { es } from 'date-fns/locale';

interface TaskItemProps {
    id: number;
    title: string;
    projectName: string;
    date?: string; // Optional date/deadline
    status?: string;
    files_count?: number;
}

const RecentTaskItem = ({ title, projectName, date, files_count }: TaskItemProps) => {

    const formatDateSafe = (dateString?: string) => {
        if (!dateString) return null;
        try {
            const d = new Date(dateString);
            return isValid(d) ? format(d, 'd/MM/yyyy', { locale: es }) : null;
        } catch (e) {
            return null;
        }
    };

    const displayDate = formatDateSafe(date);

    const statusColors: Record<string, string> = {
        'completado': '#22c55e', // green-500
        'en_progreso': '#eab308', // yellow-500
        'pendiente': '#ef4444' // red-500
    };

    const isOverdue = (() => {
        if (!date || status === 'completado') return false;
        const todayStr = new Date().toISOString().split('T')[0];
        const dueStr = date.split('T')[0];
        return dueStr < todayStr;
    })();

    return (
        <div
            className="flex items-center justify-between p-3 bg-tudu-column-light dark:bg-tudu-column-dark rounded-lg mb-2 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors cursor-pointer group border-l-4"
            style={{ borderLeftColor: statusColors[(status || '').toLowerCase()] || '#ef4444' }}
        >
            <div className="flex-1 min-w-0 mr-4">
                <div className="flex items-center gap-2">
                    <h5 className="font-medium text-tudu-text-light dark:text-tudu-text-dark truncate group-hover:text-tudu-accent transition-colors">{title}</h5>
                    {Number(files_count || 0) > 0 && (
                        <div className="flex items-center text-xs text-purple-500 shrink-0" title={`${files_count} archivos adjuntos`}>
                            <Paperclip size={12} className="mr-0.5" />
                            <span>{files_count}</span>
                        </div>
                    )}
                </div>
                <div className="flex items-center text-xs text-tudu-text-muted mt-1">
                    <Folder size={12} className="mr-1" />
                    <span className="truncate">{projectName}</span>
                </div>
            </div>
            {displayDate && (
                <div className="text-xs font-medium px-2 py-1 bg-white dark:bg-tudu-bg-dark rounded text-tudu-text-muted flex items-center shrink-0 relative">
                    {isOverdue && (
                        <span className="absolute -top-1 -right-1 flex h-3 w-3 z-10">
                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span className="relative inline-flex rounded-full h-3 w-3 bg-red-600"></span>
                        </span>
                    )}
                    <Calendar size={12} className="mr-1" />
                    {displayDate}
                </div>
            )}
        </div>
    );
};

export default RecentTaskItem;
