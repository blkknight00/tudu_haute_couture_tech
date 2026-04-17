import { useState, useEffect } from 'react';
import { X, Check, X as XIcon, Clock, MapPin, MessageSquare } from 'lucide-react';
import api from '../../api/axios';

interface Request {
    id: number;
    titulo: string;
    solicitante_nombre: string;
    receptor_nombre: string;
    fecha_propuesta: string;
    duracion_minutos: number;
    mensaje?: string;
    link_maps?: string;
    es_receptor: boolean;
}

interface RequestsModalProps {
    isOpen: boolean;
    onClose: () => void;
    onUpdate: () => void;
}

const RequestsModal = ({ isOpen, onClose, onUpdate }: RequestsModalProps) => {
    const [requests, setRequests] = useState<Request[]>([]);
    const [loading, setLoading] = useState(false);
    const [processingId, setProcessingId] = useState<number | null>(null);

    useEffect(() => {
        if (isOpen) {
            fetchRequests();
        }
    }, [isOpen]);

    const fetchRequests = async () => {
        setLoading(true);
        try {
            const res = await api.get('/calendar.php?action=get_events&filter=pending');
            if (res.data.status === 'success' && Array.isArray(res.data.data)) {
                setRequests(res.data.data.map((e: any) => ({
                    id: parseInt(e.id.replace('req-', '')),
                    titulo: e.title?.split(': ')[1] || 'Sin título',
                    solicitante_nombre: e.extendedProps?.solicitante_nombre || 'Anónimo',
                    receptor_nombre: e.extendedProps?.receptor_nombre || 'Anónimo',
                    fecha_propuesta: e.start,
                    duracion_minutos: (new Date(e.end).getTime() - new Date(e.start).getTime()) / 60000,
                    mensaje: e.extendedProps?.descripcion,
                    link_maps: e.extendedProps?.link_maps,
                    es_receptor: e.extendedProps?.es_receptor
                })));
            }
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
        }
    };

    const handleRespond = async (id: number, estado: 'aceptada' | 'rechazada') => {
        if (!confirm(`¿Estás seguro de ${estado === 'aceptada' ? 'aceptar' : 'rechazar'} esta solicitud?`)) return;

        setProcessingId(id);
        try {
            const res = await api.post('/calendar.php?action=respond_appointment', { id, estado });
            if (res.data.status === 'success') {
                setRequests(prev => prev.filter(r => r.id !== id));
                onUpdate();
                if (requests.length <= 1) onClose();
            } else {
                alert('Error: ' + (res.data.message || 'No se pudo procesar la solicitud'));
            }
        } catch (e) {
            console.error(e);
            alert('Error al conectar con el servidor');
        } finally {
            setProcessingId(null);
        }
    };

    const handleCancel = async (id: number) => {
        if (!confirm('¿Estás seguro de cancelar esta solicitud?')) return;

        setProcessingId(id);
        try {
            const res = await api.post('/calendar.php?action=delete_appointment', { id });
            if (res.data.status === 'success') {
                setRequests(prev => prev.filter(r => r.id !== id));
                onUpdate();
                if (requests.length <= 1) onClose();
            } else {
                alert('Error: ' + (res.data.message || 'No se pudo cancelar la solicitud'));
            }
        } catch (e) {
            console.error(e);
            alert('Error al conectar con el servidor');
        } finally {
            setProcessingId(null);
        }
    };

    if (!isOpen) return null;

    return (
        <div
            className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            onClick={onClose}
        >
            <div
                className="bg-white dark:bg-tudu-content-dark w-full max-w-lg rounded-xl shadow-2xl flex flex-col max-h-[80vh]"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 className="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <Clock className="text-orange-500" size={20} />
                        Solicitudes Pendientes
                    </h2>
                    <button onClick={onClose} className="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <X size={20} />
                    </button>
                </div>

                <div className="p-4 overflow-y-auto custom-scrollbar flex-1">
                    {loading ? (
                        <div className="py-10 text-center">
                            <div className="inline-block w-8 h-8 border-4 border-tudu-accent/30 border-t-tudu-accent rounded-full animate-spin"></div>
                            <p className="mt-2 text-sm text-gray-500">Cargando solicitudes...</p>
                        </div>
                    ) : requests.length === 0 ? (
                        <div className="py-10 text-center">
                            <p className="text-gray-500 italic">No hay solicitudes pendientes.</p>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {requests.map((req) => (
                                <div key={req.id} className="bg-gray-50 dark:bg-tudu-bg-dark border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm">
                                    <div className="flex justify-between items-start mb-3">
                                        <div>
                                            <h3 className="font-bold text-gray-800 dark:text-white text-sm">{req.titulo}</h3>
                                            <p className="text-xs text-tudu-accent font-medium">
                                                {req.es_receptor ? `De: ${req.solicitante_nombre}` : `Para: ${req.receptor_nombre}`}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-xs font-bold text-gray-700 dark:text-gray-300">
                                                {new Date(req.fecha_propuesta).toLocaleDateString()}
                                            </p>
                                            <p className="text-[10px] text-gray-500">
                                                {new Date(req.fecha_propuesta).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} ({req.duracion_minutos} min)
                                            </p>
                                        </div>
                                    </div>

                                    {req.mensaje && (
                                        <div className="mb-3 p-2 bg-white dark:bg-tudu-content-dark rounded border border-gray-100 dark:border-gray-800 flex gap-2">
                                            <MessageSquare size={14} className="text-gray-400 mt-0.5 flex-shrink-0" />
                                            <p className="text-xs text-gray-600 dark:text-gray-400 italic">"{req.mensaje}"</p>
                                        </div>
                                    )}

                                    <div className="flex gap-2">
                                        {req.link_maps && (
                                            <a
                                                href={req.link_maps}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="flex items-center gap-1 text-xs text-blue-500 hover:text-blue-600 mr-auto"
                                            >
                                                <MapPin size={12} /> Ver Ubicación
                                            </a>
                                        )}

                                        {req.es_receptor ? (
                                            <>
                                                <button
                                                    disabled={processingId !== null}
                                                    onClick={() => handleRespond(req.id, 'rechazada')}
                                                    className="flex-1 sm:flex-none flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 dark:border-red-900/30 dark:text-red-400 dark:hover:bg-red-900/20 text-xs font-medium transition-colors disabled:opacity-50"
                                                >
                                                    {processingId === req.id ? '...' : <><XIcon size={14} /> Rechazar</>}
                                                </button>
                                                <button
                                                    disabled={processingId !== null}
                                                    onClick={() => handleRespond(req.id, 'aceptada')}
                                                    className="flex-1 sm:flex-none flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg bg-green-500 text-white hover:bg-green-600 text-xs font-medium transition-colors shadow-sm disabled:opacity-50"
                                                >
                                                    {processingId === req.id ? '...' : <><Check size={14} /> Aceptar</>}
                                                </button>
                                            </>
                                        ) : (
                                            <button
                                                disabled={processingId !== null}
                                                onClick={() => handleCancel(req.id)}
                                                className="flex-1 sm:flex-none flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700 text-xs font-medium transition-colors disabled:opacity-50"
                                            >
                                                {processingId === req.id ? '...' : <><XIcon size={14} /> Cancelar Solicitud</>}
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )
                    }
                </div>
            </div>
        </div>
    );
};

export default RequestsModal;
