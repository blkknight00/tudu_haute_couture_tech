import React, { useState, useEffect } from 'react';
import api from '../../api/axios';
import { X, Copy, Check, MessageCircle, Send } from 'lucide-react';

interface ShareModalProps {
    isOpen: boolean;
    onClose: () => void;
    taskId: number | null;
    taskTitle: string;
}

const ShareModal: React.FC<ShareModalProps> = ({ isOpen, onClose, taskId, taskTitle }) => {
    const [loading, setLoading] = useState(false);
    const [publicLink, setPublicLink] = useState('');
    const [copied, setCopied] = useState(false);
    const [whatsappPhone, setWhatsappPhone] = useState('');
    const [users, setUsers] = useState<any[]>([]);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (isOpen && taskId) {
            fetchShareLink(taskId);
            fetchUsers();
        } else {
            // Reset state on close
            setPublicLink('');
            setCopied(false);
            setWhatsappPhone('');
            setError(null);
        }
    }, [isOpen, taskId]);

    const fetchUsers = async () => {
        try {
            const res = await api.get('/get_options.php');
            if (res.data && res.data.success) {
                setUsers(res.data.users || []);
            }
        } catch (err) {
            console.error("Error fetching users for share", err);
        }
    };

    const fetchShareLink = async (id: number) => {
        setLoading(true);
        setError(null);
        try {
            const res = await api.get(`/generar_share_link.php?id=${id}`);
            if (res.data && res.data.success) {
                setPublicLink(res.data.url);
            } else {
                setError(res.data.error || "Error al generar enlace");
            }
        } catch (err) {
            setError("Error de conexión");
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const handleCopy = () => {
        if (publicLink) {
            navigator.clipboard.writeText(publicLink);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    const handleSendWhatsApp = () => {
        if (!whatsappPhone) {
            alert("Ingresa un número de teléfono");
            return;
        }
        if (!publicLink) return;

        const cleanPhone = whatsappPhone.replace(/[^0-9]/g, '');
        // Ensure link has protocol
        let finalLink = publicLink;
        if (!finalLink.startsWith('http')) {
            finalLink = 'https://' + finalLink; // or http depending on prod
        }

        const message = `Hola, te comparto la tarea: '${taskTitle}'.\n\nVer Tarea: ${finalLink}`;
        const url = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`;

        window.open(url, '_blank');
        // onClose(); // Optional: keep open or close
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm animate-fade-in p-4">
            <div className="bg-white dark:bg-tudu-column-dark w-full max-w-md rounded-xl shadow-2xl overflow-hidden border border-gray-100 dark:border-gray-700">

                {/* Header */}
                <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-800">
                    <h3 className="font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                        <Share2Icon /> Compartir Tarea
                    </h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                        <X size={20} />
                    </button>
                </div>

                {/* Body */}
                <div className="p-6 space-y-6">
                    {loading ? (
                        <div className="text-center py-8">
                            <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-tudu-accent"></div>
                            <p className="mt-2 text-sm text-gray-500">Generando enlace público...</p>
                        </div>
                    ) : error ? (
                        <div className="bg-red-50 text-red-600 p-4 rounded-lg text-sm text-center">
                            {error}
                            <button onClick={() => taskId && fetchShareLink(taskId)} className="block mx-auto mt-2 text-xs font-bold underline">Reintentar</button>
                        </div>
                    ) : (
                        <>
                            {/* Section: Copy Link */}
                            <div className="space-y-2">
                                <label className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Enlace Público</label>
                                <div className="flex gap-2">
                                    <input
                                        type="text"
                                        readOnly
                                        value={publicLink}
                                        className="flex-1 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-600 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-tudu-accent/50"
                                    />
                                    <button
                                        onClick={handleCopy}
                                        className={`px-3 py-2 rounded-lg border transition-colors flex items-center justify-center min-w-[44px] ${copied ? 'bg-green-50 border-green-200 text-green-600' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'}`}
                                        title="Copiar"
                                    >
                                        {copied ? <Check size={18} /> : <Copy size={18} />}
                                    </button>
                                </div>
                                <p className="text-xs text-gray-400">Cualquiera con este enlace podrá ver la tarea.</p>
                            </div>

                            <hr className="border-gray-100 dark:border-gray-700" />

                            {/* Section: WhatsApp */}
                            <div className="space-y-4">
                                <label className="text-xs font-semibold text-green-600 uppercase tracking-wide flex items-center gap-1">
                                    <MessageCircle size={14} /> Enviar por WhatsApp
                                </label>

                                <div className="space-y-3">
                                    <div className="flex gap-2">
                                        <div className="flex-1">
                                            <select
                                                className="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500/50 dark:text-gray-300"
                                                onChange={(e) => {
                                                    const user = users.find(u => u.id === parseInt(e.target.value));
                                                    if (user && user.telefono) {
                                                        setWhatsappPhone(user.telefono);
                                                    }
                                                }}
                                                defaultValue=""
                                            >
                                                <option value="" disabled>Seleccionar contacto...</option>
                                                {users.filter(u => u.telefono).map(u => (
                                                    <option key={u.id} value={u.id}>{u.nombre}</option>
                                                ))}
                                            </select>
                                        </div>
                                    </div>
                                    <div className="flex gap-2">
                                        <div className="relative flex-1">
                                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                                <MessageCircle size={16} />
                                            </span>
                                            <input
                                                type="tel"
                                                placeholder="Número (con código país)"
                                                className="w-full pl-10 pr-3 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500/50 dark:text-white"
                                                value={whatsappPhone}
                                                onChange={(e) => setWhatsappPhone(e.target.value)}
                                            />
                                        </div>
                                        <button
                                            onClick={handleSendWhatsApp}
                                            className="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
                                        >
                                            Enviar <Send size={14} />
                                        </button>
                                    </div>
                                </div>
                                <p className="text-xs text-gray-400">Ingresa el número completo (ej. 5215512345678) sin símbolos.</p>
                            </div>
                        </>
                    )}
                </div>

            </div>
        </div>
    );
};

// Internal icon component to avoid huge import lists just for one icon if needed, 
// strictly using lucide-react as imported.
const Share2Icon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" /><line x1="8.59" y1="13.51" x2="15.42" y2="17.49" /><line x1="15.41" y1="6.51" x2="8.59" y2="10.49" /></svg>
);

export default ShareModal;
