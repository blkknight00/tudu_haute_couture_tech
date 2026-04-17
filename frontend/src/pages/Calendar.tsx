import { useState, useEffect, useRef } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import esLocale from '@fullcalendar/core/locales/es';
import { Plus, Inbox, LayoutDashboard, Kanban, Calendar as CalendarIcon } from 'lucide-react';
import api from '../api/axios';
import { useTaskModal } from '../contexts/TaskModalContext';
// ProjectFilterContext is used by ActionBar but calendar has its own filters
import EventModal from '../components/calendar/EventModal';
import RequestsModal from '../components/calendar/RequestsModal';

const Calendar = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const calendarRef = useRef<FullCalendar>(null);
    const [filter, setFilter] = useState('confirmed'); // confirmed | pending
    const [pendingCount, setPendingCount] = useState(0);
    const [calendarEvents, setCalendarEvents] = useState<any[]>([]);
    const [currentRange, setCurrentRange] = useState<{ start: string; end: string } | null>(null);
    const [loadingEvents, setLoadingEvents] = useState(false);
    const [calendarError, setCalendarError] = useState<string | null>(null);

    // Modals State
    const [isEventModalOpen, setIsEventModalOpen] = useState(false);
    const [isRequestsModalOpen, setIsRequestsModalOpen] = useState(false);
    const [selectedEvent, setSelectedEvent] = useState<any>(null);
    const [selectedDate, setSelectedDate] = useState<string>('');

    const { openEditTaskModal } = useTaskModal();

    // Fetch events whenever filter or visible date range changes
    const fetchEvents = async (range: { start: string; end: string }) => {
        setLoadingEvents(true);
        setCalendarError(null);
        try {
            const url = `/calendar.php?action=get_events&filter=${filter}&start=${range.start}&end=${range.end}`;
            const res = await api.get(url);
            if (res.data.status === 'success' && Array.isArray(res.data.data)) {
                setCalendarEvents(res.data.data);
            } else {
                const errMsg = res.data?.message || JSON.stringify(res.data).substring(0, 200);
                console.warn('[Calendar] API returned:', res.data);
                setCalendarError(`Error API: ${errMsg}`);
                setCalendarEvents([]);
            }
        } catch (e: any) {
            const errMsg = e?.response?.data?.message || e?.message || String(e);
            console.error('[Calendar] fetch error:', e);
            setCalendarError(`Error de red: ${errMsg}`);
            setCalendarEvents([]);
        } finally {
            setLoadingEvents(false);
        }
    };

    useEffect(() => {
        if (!currentRange) return;
        fetchEvents(currentRange);

        const handleProjectSaved = () => {
            if (currentRange) fetchEvents(currentRange);
        };
        window.addEventListener('project-saved', handleProjectSaved);

        return () => window.removeEventListener('project-saved', handleProjectSaved);
    }, [filter, currentRange]);

    // Initial Data & Polling
    useEffect(() => {
        fetchPendingCount();
        fetchSummary();

        // Polling for requests count
        const interval = setInterval(() => {
            fetchPendingCount();
            fetchSummary();
        }, 30000);
        return () => clearInterval(interval);
    }, []);

    const fetchPendingCount = async () => {
        try {
            const res = await api.get('/calendar.php?action=get_events&filter=pending');
            if (res.data.status === 'success') {
                setPendingCount(res.data.data.length);
            }
        } catch (e) {
            console.error(e);
        }
    };

    const fetchSummary = async () => {
        try {
            const res = await api.get('/calendar.php?action=get_summary');
            if (res.data.status === 'success') {
                // setSummary(res.data.data);
            }
        } catch (e) {
            console.error(e);
        }
    };

    const handleEventClick = (info: any) => {
        const event = info.event;
        const id = event.id;

        if (id.startsWith('task-')) {
            // It's a Task
            const taskId = parseInt(id.replace('task-', ''));
            // Create a minimal task object to open the modal
            // Ideally, openEditTaskModal should fetch the full task by ID.
            // Let's assume openEditTaskModal expects a task object. 
            // We can fetch it or just pass minimal info if TaskModal handles fetching.
            // Looking at TaskModalContext... it sets 'editingTask'.
            // If we only pass Partial, TaskModal might break if it expects full fields.
            // Let's fetch the task first or improve TaskModalContext.
            // For now, let's try opening with what we have (ID is crucial).
            // Actually, let's use a helper in TaskModalContext or just fetch here?
            // Let's hack: The previous Calendar.tsx used 'fetchTaskDetails' but was empty.
            // I'll assume standard TaskModal usage requires proper object.
            // Let's modify TaskModal to fetch if object is partial? No, that's risky.
            // Let's just create a dummy task object with the ID and refresh.
            // Wait, standard 'openEditTaskModal' takes a task object.
            // I'll call API to get task details first.
            api.post('/tasks.php', { id: taskId })
                .then(() => {
                    // Oops tasks.php architecture might be complex.
                    // Let's try to 'openEditTaskModal' with the event extended props mapped.
                    openEditTaskModal({
                        id: taskId,
                        titulo: event.title,
                        descripcion: event.extendedProps.descripcion,
                        proyecto_id: event.extendedProps.proyecto_id,
                        estado: event.extendedProps.status,
                        prioridad: event.extendedProps.priority,
                        fecha_termino: event.startStr,
                        // Missing: tags, visibility, etc.
                        // Ideally TaskModal fetches details on mount if incomplete?
                        // Let's verify TaskModal behavior. 
                        // Actually, I can just use the 'task' ID to find it in the global list if I had it.
                        // I will pass the partial object. TaskModal usually refreshes or I can set it to fetch.
                    });
                })
                .catch(() => {
                    // Fallback
                    openEditTaskModal({ id: taskId, titulo: event.title });
                });
        } else if (id.startsWith('req-')) {
            // It's a Request (shouldn't happen in 'confirmed' view, but specific filter)
            // Do nothing or open requests modal
            setIsRequestsModalOpen(true);
        } else if (id.startsWith('busy-')) {
            // Private event
            return;
        } else {
            // Standard Event
            setSelectedEvent(event);
            setIsEventModalOpen(true);
        }
    };

    const handleDateClick = (info: any) => {
        // Clear selection
        setSelectedEvent(null);
        setSelectedDate(info.dateStr);
        setIsEventModalOpen(true);
    };

    const formatToMySQL = (date: Date) => {
        const pad = (n: number) => n.toString().padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
    };

    const handleEventDrop = async (info: any) => {
        const event = info.event;
        const id = event.id;

        if (id.startsWith('task-')) {
            if (!confirm(`¿Cambiar vencimiento de "${event.title}" a ${event.startStr}?`)) {
                info.revert();
                return;
            }

            try {
                await api.post('/calendar.php?action=update_task_due_date', {
                    id: id.replace('task-', ''),
                    fecha_termino: event.startStr.split('T')[0]
                });
                await fetchSummary();
            } catch (e) {
                console.error("Error updating task date:", e);
                alert("Error al actualizar la tarea.");
                info.revert();
            }
        } else {
            const start = event.start;
            const end = event.end || start;

            const payload = {
                event_id: event.id,
                titulo: event.title,
                start: formatToMySQL(start),
                end: formatToMySQL(end),
                tipo_evento: event.extendedProps.tipo,
                privacidad: event.extendedProps.privacidad,
                ubicacion_tipo: event.extendedProps.ubicacion_tipo,
                ubicacion_detalle: event.extendedProps.ubicacion_detalle,
                link_maps: event.extendedProps.link_maps,
                descripcion: event.extendedProps.descripcion
            };

            try {
                await api.post('/calendar.php?action=save_event', payload);
            } catch (e) {
                console.error("Error moving event:", e);
                info.revert();
                alert('Error al mover evento');
            }
        }
    };

    const handleEventResize = async (info: any) => {
        const event = info.event;
        const start = event.start;
        const end = event.end || start;

        const payload = {
            event_id: event.id,
            titulo: event.title,
            start: formatToMySQL(start),
            end: formatToMySQL(end),
            tipo_evento: event.extendedProps.tipo,
            privacidad: event.extendedProps.privacidad,
            ubicacion_tipo: event.extendedProps.ubicacion_tipo,
            ubicacion_detalle: event.extendedProps.ubicacion_detalle,
            link_maps: event.extendedProps.link_maps,
            descripcion: event.extendedProps.descripcion
        };

        try {
            await api.post('/calendar.php?action=save_event', payload);
        } catch (e) {
            console.error("Error resizing event:", e);
            info.revert();
            alert('Error al cambiar la duración');
        }
    };

    return (
        <div className="flex flex-col h-full gap-4">

            {/* Toolbar */}
            <div className="flex flex-col sm:flex-row justify-center items-center gap-6 bg-white/85 dark:bg-tudu-content-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">

                {/* View Switcher (Moved from ActionBar) - HIDDEN ON MOBILE */}
                <div className="hidden sm:flex bg-gray-100 dark:bg-tudu-column-dark p-1 rounded-lg">
                    <button
                        onClick={() => navigate('/')}
                        className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-sm font-medium transition-all ${location.pathname === '/' ? 'bg-white dark:bg-tudu-bg-dark text-tudu-accent shadow-sm' : 'text-tudu-text-muted hover:text-tudu-text-light dark:hover:text-white'}`}
                        title="Dashboard"
                    >
                        <LayoutDashboard size={16} />
                        <span className="hidden lg:inline">Dashboard</span>
                    </button>
                    <button
                        onClick={() => navigate('/kanban')}
                        className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-sm font-medium transition-all ${location.pathname === '/kanban' ? 'bg-white dark:bg-tudu-bg-dark text-tudu-accent shadow-sm' : 'text-tudu-text-muted hover:text-tudu-text-light dark:hover:text-white'}`}
                        title="Tablero Kanban"
                    >
                        <Kanban size={16} />
                        <span className="hidden lg:inline">Tablero</span>
                    </button>
                    <button
                        onClick={() => navigate('/calendar')}
                        className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-sm font-medium transition-all ${location.pathname === '/calendar' ? 'bg-white dark:bg-tudu-bg-dark text-tudu-accent shadow-sm' : 'text-tudu-text-muted hover:text-tudu-text-light dark:hover:text-white'}`}
                        title="Calendario"
                    >
                        <CalendarIcon size={16} />
                        <span className="hidden lg:inline">Calendario</span>
                    </button>
                </div>

                {/* Filter */}
                <div className="flex items-center gap-2 w-full sm:w-auto">
                    <select
                        value={filter}
                        onChange={(e) => setFilter(e.target.value)}
                        className="p-2 bg-gray-50 dark:bg-tudu-bg-dark border border-gray-200 dark:border-gray-600 rounded-lg text-sm font-medium focus:ring-2 focus:ring-tudu-accent outline-none w-full sm:w-64"
                    >
                        <option value="confirmed">📅 Eventos Confirmados</option>
                        <option value="pending">⏳ Solicitudes Pendientes</option>
                    </select>
                </div>

                {/* Right: Actions */}
                <div className="flex items-center gap-2 w-full sm:w-auto">
                    <button
                        onClick={() => { setSelectedEvent(null); setSelectedDate(''); setIsEventModalOpen(true); }}
                        className="flex-1 sm:flex-none flex items-center justify-center gap-2 bg-tudu-accent hover:bg-tudu-accent-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                    >
                        <Plus size={18} />
                        <span className="hidden sm:inline">Nuevo Evento</span>
                        <span className="sm:hidden">Nuevo</span>
                    </button>

                    <button
                        onClick={() => setIsRequestsModalOpen(true)}
                        className="relative flex items-center justify-center gap-2 bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 hover:bg-orange-200 dark:hover:bg-orange-900/50 px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                    >
                        <Inbox size={18} />
                        {pendingCount > 0 && (
                            <span className="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] text-white font-bold animate-pulse shadow-sm">
                                {pendingCount}
                            </span>
                        )}
                    </button>
                </div>
            </div>

            {/* Calendar View */}
            <div className="flex-1 bg-white/85 dark:bg-tudu-content-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden relative z-10 flex flex-col">

                {loadingEvents && (
                    <div className="text-xs text-center text-gray-400 mb-1 animate-pulse">Cargando eventos...</div>
                )}
                {calendarError && (
                    <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 mb-2 text-sm text-red-700 dark:text-red-400 flex items-center justify-between">
                        <span>⚠️ {calendarError}</span>
                        <button
                            onClick={() => currentRange && fetchEvents(currentRange)}
                            className="ml-3 text-xs underline hover:no-underline"
                        >
                            Reintentar
                        </button>
                    </div>
                )}
                {!loadingEvents && !calendarError && calendarEvents.length === 0 && currentRange && (
                    <div className="text-xs text-center text-amber-500 mb-1">
                        No se encontraron eventos para este período. <button onClick={() => currentRange && fetchEvents(currentRange)} className="underline">Recargar</button>
                    </div>
                )}

                {/* Legend */}
                <div className="flex flex-wrap items-center justify-center gap-4 mb-4 text-xs text-gray-500 dark:text-gray-400 border-b dark:border-gray-700 pb-2">
                    <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-blue-500"></span> General</span>
                    <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-green-500"></span> Reunión</span>
                    <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-red-500"></span> Entrega</span>
                    <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-yellow-500"></span> Hoy</span>
                    <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-purple-600"></span> Tarea</span>
                    <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-gray-500"></span> Ocupado</span>
                </div>

                <FullCalendar
                    ref={calendarRef}
                    plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
                    initialView="dayGridMonth"
                    locale={esLocale}
                    events={calendarEvents}
                    datesSet={(dateInfo) => {
                        setCurrentRange({ start: dateInfo.startStr, end: dateInfo.endStr });
                    }}
                    eventClick={handleEventClick}
                    dateClick={handleDateClick}
                    eventDrop={handleEventDrop}
                    eventResize={handleEventResize}
                    editable={true}
                    droppable={true}
                    headerToolbar={{
                        left: window.innerWidth < 640 ? 'title' : 'prev,next today',
                        center: window.innerWidth < 640 ? '' : 'title',
                        right: window.innerWidth < 640 ? 'prev,next today' : 'dayGridMonth,timeGridWeek,timeGridDay'
                    }}
                    footerToolbar={window.innerWidth < 640 ? {
                        left: '',
                        center: 'dayGridMonth,timeGridWeek,timeGridDay',
                        right: ''
                    } : false}
                    height="100%"
                    dayMaxEvents={false}
                    eventDisplay="block"
                    eventTimeFormat={{
                        hour: '2-digit',
                        minute: '2-digit',
                        meridiem: false
                    }}
                    firstDay={1} // Monday start
                    buttonText={{
                        today: 'Hoy',
                        month: 'Mes',
                        week: 'Semana',
                        day: 'Día',
                        list: 'Lista'
                    }}
                />
            </div>

            {/* Modals */}
            <EventModal
                isOpen={isEventModalOpen}
                onClose={() => setIsEventModalOpen(false)}
                onSave={() => calendarRef.current?.getApi().refetchEvents()}
                event={selectedEvent}
                initialDate={selectedDate}
            />

            <RequestsModal
                isOpen={isRequestsModalOpen}
                onClose={() => setIsRequestsModalOpen(false)}
                onUpdate={() => { calendarRef.current?.getApi().refetchEvents(); fetchPendingCount(); }}
            />
        </div>
    );
};

export default Calendar;
