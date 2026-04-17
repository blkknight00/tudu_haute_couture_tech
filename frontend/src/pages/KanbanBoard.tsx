import { useState, useEffect } from 'react';
import { DndContext, type DragEndEvent, DragOverlay, type DragStartEvent, useSensor, useSensors, PointerSensor, TouchSensor, closestCorners } from '@dnd-kit/core';
import KanbanColumn from '../components/kanban/KanbanColumn';
import KanbanCard from '../components/kanban/KanbanCard';
import api from '../api/axios';
import { useTaskModal } from '../contexts/TaskModalContext';
import { useProjectFilter } from '../contexts/ProjectFilterContext';
import ErrorBoundary from '../components/common/ErrorBoundary';

const KanbanBoard = () => {
    const [columns, setColumns] = useState<any>({
        pendiente: [],
        en_progreso: [],
        completado: []
    });
    const { projectType, selectedProjectId } = useProjectFilter();
    const [activeTask, setActiveTask] = useState<any>(null);
    const [loading, setLoading] = useState(true);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }), // Prevent accidental drags
        useSensor(TouchSensor)
    );


    const { setRefreshBoard } = useTaskModal();

    const fetchTasks = async () => {
        try {
            let url = `/kanban.php?view=${projectType}`;
            if (selectedProjectId) {
                url += `&proyecto_id=${selectedProjectId}`;
            }
            const response = await api.get(url);
            if (response.data.status === 'success') {
                setColumns(response.data.columns);
            }
        } catch (error) {
            console.error('Error fetching kanban:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchTasks();
        setRefreshBoard(fetchTasks);

        const handleProjectSaved = () => fetchTasks();
        window.addEventListener('project-saved', handleProjectSaved);

        return () => window.removeEventListener('project-saved', handleProjectSaved);
    }, [projectType, selectedProjectId]);

    const handleDragStart = (event: DragStartEvent) => {
        const { active } = event;
        // Find the task object
        let task = null;
        Object.keys(columns).forEach(key => {
            const found = columns[key].find((t: any) => String(t.id) === String(active.id));
            if (found) task = found;
        });
        setActiveTask(task);
    };

    const findContainer = (id: any) => {
        if (Object.keys(columns).includes(id as string)) return id;
        return Object.keys(columns).find(key => columns[key].find((t: any) => String(t.id) === String(id)));
    };

    const handleDragOver = (event: any) => {
        const { active, over } = event;
        if (!over) return;

        const activeId = active.id;
        const overId = over.id;

        const activeContainer = findContainer(activeId);
        const overContainer = findContainer(overId);

        if (!activeContainer || !overContainer || activeContainer === overContainer) {
            return;
        }

        setColumns((prev: any) => {
            const activeItems = prev[activeContainer];
            const overItems = prev[overContainer];

            const activeIndex = activeItems.findIndex((i: any) => String(i.id) === String(activeId));
            const overIndex = overItems.findIndex((i: any) => String(i.id) === String(overId));

            let newIndex;
            if (Object.keys(columns).includes(overId)) {
                newIndex = overItems.length + 1;
            } else {
                const isAfterLastItem = over && overIndex === overItems.length - 1;
                const modifier = isAfterLastItem ? 1 : 0;
                newIndex = overIndex >= 0 ? overIndex + modifier : overItems.length + 1;
            }

            const task = activeItems[activeIndex];

            return {
                ...prev,
                [activeContainer]: [...prev[activeContainer].filter((item: any) => String(item.id) !== String(active.id))],
                [overContainer]: [
                    ...prev[overContainer].slice(0, newIndex),
                    { ...task, estado: overContainer },
                    ...prev[overContainer].slice(newIndex, prev[overContainer].length)
                ]
            };
        });
    };

    const handleDragEnd = async (event: DragEndEvent) => {
        const { active, over } = event;
        setActiveTask(null);

        if (!over) return;

        const activeId = active.id;
        const overId = over.id;

        const activeContainer = findContainer(activeId);
        const overContainer = findContainer(overId);

        if (!activeContainer || !overContainer) {
            return;
        }

        // Only update if we actually changed columns
        // Note: we compare the container ID where the task ended up
        if (activeContainer === overContainer) {
            return;
        }

        try {
            const response = await api.post('/update_task_status.php', {
                id: activeId,
                status: overContainer
            });

            if (response.data.status !== 'success') {
                throw new Error(response.data.message || 'Error al actualizar estado');
            }
        } catch (error: any) {
            console.error('Failed to update status:', error);
            fetchTasks();
            alert("No se pudo actualizar el estado de la tarea. Error: " + (error.response?.data?.message || error.message));
        }
    };

    if (loading) return <div className="p-8 text-center text-tudu-text-muted">Cargando tablero...</div>;

    return (
        <ErrorBoundary>
            <DndContext
                sensors={sensors}
                collisionDetection={closestCorners}
                onDragStart={handleDragStart}
                onDragOver={handleDragOver}
                onDragEnd={handleDragEnd}
            >
                <div className="flex flex-col gap-4 flex-1 h-full px-2">
                    {/* Header removed as it is now controlled by ActionBar */}
                    <div className="flex flex-row gap-4 flex-1 overflow-x-auto pb-4 snap-x snap-mandatory">
                        <KanbanColumn
                            id="pendiente"
                            title="Pendiente"
                            tasks={columns.pendiente}
                            color="bg-red-500"
                        />
                        <KanbanColumn
                            id="en_progreso"
                            title="En Progreso"
                            tasks={columns.en_progreso}
                            color="bg-yellow-500"
                        />
                        <KanbanColumn
                            id="completado"
                            title="Completado"
                            tasks={columns.completado}
                            color="bg-green-500"
                        />
                    </div>
                </div>

                <DragOverlay>
                    {activeTask ? <KanbanCard task={activeTask} isOverlay /> : null}
                </DragOverlay>
            </DndContext>
        </ErrorBoundary>
    );
};

export default KanbanBoard;
