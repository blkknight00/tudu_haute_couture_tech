import api from '../api/axios';

export interface AgentAction {
    type: string;
    data: any;
}

export const executeAgentAction = async (
    action: AgentAction,
    navigate: (path: string) => void,
    setSelectedProjectId?: (id: string) => void
) => {
    console.log("Executing Action:", action);

    switch (action.type) {
        case 'CREATE_TASK':
            try {
                // We use the existing save_task.php API
                // For a real implementation, we might need more nuanced data
                const response = await api.post('/save_task.php', {
                    titulo: action.data.title,
                    descripcion: action.data.description || '',
                    prioridad: action.data.priority || 'media',
                    fecha_termino: action.data.due_date || '',
                    proyecto_id: action.data.project_id || 1 // Fallback to 1 if not provided, but AI should provide it
                });

                if (response.data.status === 'success') {
                    return "Tarea creada con éxito.";
                } else {
                    return "Error al crear la tarea: " + (response.data.message || "Error desconocido");
                }
            } catch (err) {
                return "Error al crear la tarea.";
            }

        case 'CREATE_REMINDER':
            try {
                const response = await api.post('/reminders.php?action=create', {
                    titulo: action.data.title,
                    fecha_recordatorio: action.data.reminder_at
                });

                if (response.data.status === 'success') {
                    return `Recordatorio programado para el ${action.data.reminder_at}.`;
                } else {
                    return "Error al programar el recordatorio: " + (response.data.message || "Error desconocido");
                }
            } catch (err) {
                return "Error al contactar con el servicio de recordatorios.";
            }

        case 'CREATE_EVENT':
            try {
                const response = await api.post('/calendar.php?action=save_event', {
                    titulo: action.data.title,
                    start: action.data.start,
                    end: action.data.end || action.data.start,
                    tipo_evento: action.data.type || 'trabajo',
                    privacidad: 'public',
                    descripcion: action.data.description || '',
                    ubicacion_tipo: 'oficina',
                    ubicacion_detalle: '',
                    link_maps: ''
                });

                if (response.data.status === 'success') {
                    return "Cita creada en el calendario con éxito.";
                } else {
                    return "Error al crear la cita: " + (response.data.message || "Error desconocido");
                }
            } catch (err) {
                return "Error al crear la cita en el calendario.";
            }

        case 'FILTER_PROJECTS':
            if (action.data.project_id && setSelectedProjectId) {
                setSelectedProjectId(String(action.data.project_id));
                return `Se ha aplicado el filtro para el proyecto con ID ${action.data.project_id}.`;
            }
            return `Se ha aplicado el filtro para proyectos ${action.data.visibility}.`;

        case 'SHOW_VIEW':
            const viewMap: Record<string, string> = {
                'list': '/',
                'kanban': '/kanban',
                'calendar': '/calendar',
                'calendario': '/calendar',
                'proyectos': '/',
                'tasks': '/',
                'tareas': '/'
            };
            const requestedView = action.data.view?.toLowerCase();

            if (action.data.project_id && setSelectedProjectId) {
                setSelectedProjectId(String(action.data.project_id));
            }

            if (viewMap[requestedView]) {
                navigate(viewMap[requestedView]);
                return `Cambiando a vista de ${requestedView}${action.data.project_id ? ' y filtrando proyecto' : ''}.`;
            }
            return `Vista "${requestedView}" no encontrada.`;

        default:
            return "Acción no reconocida.";
    }
};
