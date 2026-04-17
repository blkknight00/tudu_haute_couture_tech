import { useDroppable } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import KanbanCard from './KanbanCard';

interface KanbanColumnProps {
    id: string; // 'pendiente' | 'en_progreso' | 'completado'
    title: string;
    tasks: any[];
    color: string;
}

const KanbanColumn = ({ id, title, tasks, color }: KanbanColumnProps) => {
    const { setNodeRef } = useDroppable({ id });

    return (
        <div className="flex flex-col h-full min-w-[85vw] md:min-w-[260px] flex-1 bg-tudu-column-light/80 dark:bg-tudu-column-dark/80 backdrop-blur-md border outline-none rounded-xl border-white/20 dark:border-white/10 flex-shrink-0 snap-start shadow-sm">
            {/* Header */}
            <div className={`p-4 border-b border-gray-200 dark:border-gray-700 rounded-t-xl bg-white/85 dark:bg-tudu-content-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10 flex justify-between items-center sticky top-0 z-10`}>
                <div className="flex items-center gap-2">
                    <div className={`w-3 h-3 rounded-full ${color}`}></div>
                    <h3 className="font-semibold text-tudu-text-light dark:text-tudu-text-dark">{title}</h3>
                </div>
                <span className="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs px-2 py-1 rounded-full font-medium">
                    {tasks.length}
                </span>
            </div>

            {/* Droppable Area */}
            <div ref={setNodeRef} className="p-3 flex-1 overflow-y-auto custom-scrollbar min-h-[150px]">
                <SortableContext id={id} items={tasks.map(t => t.id)} strategy={verticalListSortingStrategy}>
                    {tasks.map((task) => (
                        <KanbanCard key={task.id} task={task} />
                    ))}
                </SortableContext>
                {tasks.length === 0 && (
                    <div className="h-full flex items-center justify-center text-gray-400 text-sm border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg m-2 p-8">
                        Arrastra tareas aquí
                    </div>
                )}
            </div>
        </div>
    );
};

export default KanbanColumn;
