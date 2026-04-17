import { useState, useEffect } from 'react';
import { X, Save, MapPin, Users, Trash2, ExternalLink, MessageCircle } from 'lucide-react';
import api from '../../api/axios';

interface User {
    id: number;
    nombre: string;
    username: string;
    telefono?: string;
    foto_perfil?: string;
}

interface EventModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSave: () => void;
    event?: any;
    initialDate?: string;
}

// Sub-component for Availability Check
const AvailabilityIndicator = ({ participantId, date, duration }: { participantId: string, date: string, duration: string }) => {
    const [status, setStatus] = useState<'idle' | 'checking' | 'available' | 'busy'>('idle');

    useEffect(() => {
        if (!participantId || !date) {
            setStatus('idle');
            return;
        }

        const check = async () => {
            setStatus('checking');
            try {
                const res = await api.get(`/calendar.php?action=check_availability&receptor_id=${participantId}&fecha=${date}&duracion=${duration}`);
                if (res.data.busy) {
                    setStatus('busy');
                } else {
                    setStatus('available');
                }
            } catch (e) {
                setStatus('idle');
            }
        };

        const timeout = setTimeout(check, 500); // Debounce
        return () => clearTimeout(timeout);
    }, [participantId, date, duration]);

    if (status === 'checking') return <span className="text-gray-400 text-xs animate-pulse">Verificando...</span>;
    if (status === 'busy') return <span className="text-red-500 text-xs font-bold flex items-center gap-1">❌ Ocupado</span>;
    if (status === 'available') return <span className="text-green-500 text-xs font-bold flex items-center gap-1">✅ Disponible</span>;
    return null;
};

const EventModal = ({ isOpen, onClose, onSave, event, initialDate }: EventModalProps) => {
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState(''); // Calculated or manually set
    const [duration, setDuration] = useState('30');
    const [type, setType] = useState('reunion');
    const [privacy, setPrivacy] = useState('privado');
    const [locationType, setLocationType] = useState('oficina');
    const [locationDetail, setLocationDetail] = useState('');
    const [mapLink, setMapLink] = useState('');
    const [participantId, setParticipantId] = useState(''); // For requests/invites

    const [users, setUsers] = useState<User[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (isOpen) {
            fetchUsers();
            if (event) {
                // Edit Mode
                setTitle(event.title);
                setDescription(event.extendedProps?.descripcion || '');
                setStartDate(formatDateForInput(event.start));
                setEndDate(formatDateForInput(event.end));
                setType(event.extendedProps?.tipo || 'reunion');
                setPrivacy(event.extendedProps?.privacidad || 'privado');
                setLocationType(event.extendedProps?.ubicacion_tipo || 'oficina');
                setLocationDetail(event.extendedProps?.ubicacion_detalle || '');
                setMapLink(event.extendedProps?.link_maps || '');

                // Calculate duration roughly
                if (event.start && event.end) {
                    const diff = (new Date(event.end).getTime() - new Date(event.start).getTime()) / 60000;
                    setDuration(diff.toString());
                }
            } else {
                // Create Mode
                resetForm();
                if (initialDate) {
                    const d = new Date(initialDate);
                    // Adjust to local ISO string for input
                    const localIso = new Date(d.getTime() - (d.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
                    setStartDate(localIso);
                } else {
                    const now = new Date();
                    const localIso = new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
                    setStartDate(localIso);
                }
            }
        }
    }, [isOpen, event, initialDate]);

    // Handle duration change to update end date automatically
    useEffect(() => {
        if (startDate && duration !== 'custom') {
            const start = new Date(startDate);
            const dur = parseInt(duration);
            const end = new Date(start.getTime() + dur * 60000);
            const localEnd = new Date(end.getTime() - (end.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
            setEndDate(localEnd);
        }
    }, [startDate, duration]);

    const fetchUsers = async () => {
        try {
            const res = await api.get('/get_options.php');
            if (res.data && res.data.status === 'success') {
                setUsers(res.data.users || []);
            }
        } catch (e) {
            console.error("Error fetching users", e);
        }
    };

    const formatDateForInput = (date: any) => {
        if (!date) return '';
        const d = new Date(date);
        return new Date(d.getTime() - (d.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
    };

    const resetForm = () => {
        setTitle('');
        setDescription('');
        setDuration('30');
        setType('reunion');
        setPrivacy('privado');
        setLocationType('oficina');
        setLocationDetail('');
        setMapLink('');
        setParticipantId('');
        setError('');
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        setError('');

        try {
            const isRequest = !!participantId;
            const action = isRequest ? 'request_appointment' : 'save_event';

            const payload = {
                event_id: event?.id,
                titulo: title,
                start: startDate,
                end: endDate,
                tipo_evento: type,
                privacidad: privacy,
                descripcion: description,
                ubicacion_tipo: locationType,
                ubicacion_detalle: locationDetail,
                link_maps: mapLink,
                receptor_id: participantId,
                duracion_minutos: duration
            };

            const res = await api.post(`/calendar.php?action=${action}`, payload);
            if (res.data.status === 'success') {
                onSave();
                onClose();
            } else {
                setError(res.data.message || 'Error al guardar evento');
            }
        } catch (err: any) {
            console.error(err);
            setError(err.response?.data?.message || 'Error de conexión');
        } finally {
            setIsLoading(false);
        }
    };

    const handleDelete = async () => {
        if (!event?.id || !confirm('¿Eliminar este evento?')) return;
        setIsLoading(true);
        try {
            await api.post('/calendar.php?action=delete_event', { id: event.id });
            onSave();
            onClose();
        } catch (e) {
            alert('Error al eliminar');
        } finally {
            setIsLoading(false);
        }
    };

    const getWhatsAppLink = (participantId: number) => {
        const user = users.find(u => u.id === participantId);
        if (!user || !user.telefono) return null;

        const phone = user.telefono.replace(/[^0-9]/g, '');
        const text = encodeURIComponent(`Hola ${user.nombre}, sobre el evento "${title}"...`);
        return `https://wa.me/${phone}?text=${text}`;
    };

    if (!isOpen) return null;

    return (
        <div
            className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 overflow-y-auto"
            onClick={onClose}
        >
            <div
                className="bg-white dark:bg-tudu-content-dark w-full max-w-lg rounded-xl shadow-2xl flex flex-col my-8"
                onClick={e => e.stopPropagation()}
            >
                {/* Header */}
                <div className={`flex justify-between items-center p-4 border-b rounded-t-xl ${type === 'reunion' ? 'bg-green-600' :
                    type === 'entrega' ? 'bg-red-600' :
                        type === 'personal' ? 'bg-purple-600' : 'bg-blue-600'
                    } text-white`}>
                    <h2 className="text-lg font-bold flex items-center gap-2">
                        {event ? 'Editar Evento' : 'Nuevo Evento'}
                    </h2>
                    <button onClick={onClose} className="text-white/80 hover:text-white">
                        <X size={20} />
                    </button>
                </div>

                {/* Body */}
                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    {error && (
                        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                            {error}
                        </div>
                    )}

                    <div>
                        <label className="block text-sm font-medium dark:text-gray-300 mb-1">Título</label>
                        <input
                            type="text"
                            value={title}
                            onChange={e => setTitle(e.target.value)}
                            required
                            className="w-full p-2 border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium dark:text-gray-300 mb-1">Inicio</label>
                            <input
                                type="datetime-local"
                                value={startDate}
                                onChange={e => setStartDate(e.target.value)}
                                required
                                className="w-full p-2 border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium dark:text-gray-300 mb-1">Duración</label>
                            <select
                                value={duration}
                                onChange={e => setDuration(e.target.value)}
                                className="w-full p-2 border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm"
                            >
                                <option value="15">15 min</option>
                                <option value="30">30 min</option>
                                <option value="45">45 min</option>
                                <option value="60">1 hora</option>
                                <option value="90">1.5 horas</option>
                                <option value="120">2 horas</option>
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium dark:text-gray-300 mb-1">Tipo</label>
                            <select
                                value={type}
                                onChange={e => setType(e.target.value)}
                                className="w-full p-2 border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm"
                            >
                                <option value="personal">Personal</option>
                                <option value="reunion">Reunión</option>
                                <option value="entrega">Entrega</option>
                                <option value="revision">Revisión</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium dark:text-gray-300 mb-1">Privacidad</label>
                            <select
                                value={privacy}
                                onChange={e => setPrivacy(e.target.value)}
                                className="w-full p-2 border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm"
                            >
                                <option value="privado">Privado</option>
                                <option value="publico">Público</option>
                            </select>
                        </div>
                    </div>

                    {/* Participant / Request Section */}
                    {!event && (
                        <div>
                            <label className="block text-sm font-medium dark:text-gray-300 mb-1 flex items-center gap-2">
                                <Users size={16} /> Invitar / Solicitar Cita <span className="text-xs text-gray-400">(Opcional)</span>
                            </label>
                            <div className="flex gap-2 items-center">
                                <select
                                    value={participantId}
                                    onChange={e => setParticipantId(e.target.value)}
                                    className="flex-1 p-2 border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                                >
                                    <option value="">Evento Personal (Solo yo)</option>
                                    {users.map(u => (
                                        <option key={u.id} value={u.id}>
                                            {u.nombre} ({u.username})
                                        </option>
                                    ))}
                                </select>
                                {participantId && (
                                    <div className="text-sm">
                                        <AvailabilityIndicator participantId={participantId} date={startDate} duration={duration} />
                                    </div>
                                )}
                            </div>
                            {participantId && (
                                <p className="text-xs text-blue-600 mt-1">
                                    Se enviará una solicitud de cita a este usuario.
                                </p>
                            )}
                        </div>
                    )}

                    {event && (
                        <div className="flex gap-2 items-center text-sm pt-2 border-t dark:border-gray-700">
                            <span className="text-gray-500 font-bold text-xs uppercase">Compartir Evento:</span>
                            <button
                                type="button"
                                onClick={() => {
                                    const url = window.location.origin + '/calendar?event_id=' + event.id;
                                    navigator.clipboard.writeText(url);
                                    alert('Enlace copiado al portapapeles');
                                }}
                                className="flex items-center gap-1 text-blue-600 hover:text-blue-800 hover:underline"
                            >
                                <ExternalLink size={14} /> Copiar Enlace
                            </button>
                        </div>
                    )}

                    {/* Location Section */}
                    <div className="bg-gray-50 dark:bg-gray-800/50 p-3 rounded-lg border dark:border-gray-700 space-y-3">
                        <div className="flex gap-2">
                            <div className="w-1/3">
                                <label className="block text-xs font-bold text-gray-500 mb-1">Ubicación</label>
                                <select
                                    value={locationType}
                                    onChange={e => setLocationType(e.target.value)}
                                    className="w-full p-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                                >
                                    <option value="oficina">Oficina</option>
                                    <option value="externa">Maps / Externa</option>
                                    <option value="virtual">Virtual</option>
                                </select>
                            </div>
                            <div className="flex-1">
                                <label className="block text-xs font-bold text-gray-500 mb-1">Detalle / Dirección</label>
                                <input
                                    type="text"
                                    value={locationDetail}
                                    onChange={e => setLocationDetail(e.target.value)}
                                    placeholder={locationType === 'externa' ? "Escribe la dirección..." : "Ej. Sala 1"}
                                    className="w-full p-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                                />
                            </div>
                        </div>

                        {/* Mini Map Preview */}
                        {locationType === 'externa' && locationDetail.length > 3 && (
                            <div className="relative w-full h-48 rounded-lg overflow-hidden border dark:border-gray-600 bg-gray-200 group cursor-pointer"
                                onClick={() => window.open(`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(locationDetail)}`, '_blank')}
                                title="Clic para abrir en Google Maps y copiar el enlace"
                            >
                                <iframe
                                    width="100%"
                                    height="100%"
                                    frameBorder="0"
                                    scrolling="no"
                                    marginHeight={0}
                                    marginWidth={0}
                                    src={`https://maps.google.com/maps?q=${encodeURIComponent(locationDetail)}&t=&z=15&ie=UTF8&iwloc=&output=embed`}
                                    className="pointer-events-none"
                                ></iframe>
                                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors flex items-center justify-center">
                                    <span className="opacity-0 group-hover:opacity-100 bg-white/90 dark:bg-black/80 text-xs px-2 py-1 rounded shadow-sm transition-opacity">
                                        <ExternalLink size={12} className="inline mr-1" /> Abrir en Maps
                                    </span>
                                </div>
                            </div>
                        )}

                        <div>
                            <label className="block text-xs font-bold text-gray-500 mb-1 flex items-center gap-1">
                                <MapPin size={12} /> Link de Google Maps <span className="font-normal text-gray-400">(Pega aquí el enlace para compartir)</span>
                            </label>
                            <input
                                type="url"
                                value={mapLink}
                                onChange={e => setMapLink(e.target.value)}
                                placeholder="https://maps.app.goo.gl/..."
                                className="w-full p-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium dark:text-gray-300 mb-1">Descripción</label>
                        <textarea
                            value={description}
                            onChange={e => setDescription(e.target.value)}
                            rows={3}
                            className="w-full p-2 border rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-white resize-none"
                        />
                    </div>

                    {/* WhatsApp Quick Link (If editing and confirmed) */}
                    {event && users.length > 0 && (
                        <div className="pt-2 border-t dark:border-gray-700">
                            <p className="text-xs text-gray-500 mb-2 font-bold">ACCIONES RÁPIDAS</p>
                            <div className="flex flex-wrap gap-2">
                                {users.filter(u => event.title.includes(u.nombre) || description.includes(u.nombre)).map(u => {
                                    const link = getWhatsAppLink(u.id);
                                    if (!link) return null;
                                    return (
                                        <a
                                            key={u.id}
                                            href={link}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 px-3 py-1.5 bg-green-100 text-green-700 rounded-full text-xs font-medium hover:bg-green-200 transition-colors"
                                        >
                                            <MessageCircle size={14} /> WhatsApp a {u.nombre}
                                        </a>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </form>

                {/* Footer */}
                <div className="flex justify-between p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800 rounded-b-xl">
                    {event && (
                        <button
                            type="button"
                            onClick={handleDelete}
                            className="text-red-500 hover:text-red-700 p-2 rounded hover:bg-red-50 dark:hover:bg-red-900/20"
                        >
                            <Trash2 size={20} />
                        </button>
                    )}
                    <div className="flex gap-2 ml-auto">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg"
                        >
                            Cancelar
                        </button>
                        <button
                            onClick={handleSubmit}
                            disabled={isLoading}
                            className="px-4 py-2 bg-tudu-accent text-white rounded-lg hover:bg-tudu-accent-hover disabled:opacity-50 flex items-center gap-2"
                        >
                            <Save size={18} /> {isLoading ? 'Guardando...' : 'Guardar'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default EventModal;
