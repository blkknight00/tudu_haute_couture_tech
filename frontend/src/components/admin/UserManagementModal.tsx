import { useState, useEffect } from 'react';
import api, { BASE_URL } from '../../api/axios';
import { X, Send, Copy, Mail, Phone, Users, UserPlus, Clock, Check, XCircle, CheckCircle, Trash2, Shield, ShieldCheck, ChevronDown, Link2, PartyPopper } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';

interface TeamMember {
    id: number;
    username: string;
    nombre: string;
    email: string;
    telefono: string;
    rol: string;
    activo: number;
    foto_perfil: string | null;
    organizacion_id: number | null;
    organizacion_nombre: string | null;
}

interface Invitation {
    id: number;
    email: string | null;
    telefono: string | null;
    token: string;
    estado: 'pendiente' | 'aceptada' | 'expirada';
    fecha_creacion: string;
    fecha_expiracion: string;
    invitado_por_nombre: string;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
}

const UserManagementModal = ({ isOpen, onClose }: Props) => {
    const { user: currentUser } = useAuth();
    const [tab, setTab] = useState<'invite' | 'team'>('invite');
    const [contact, setContact] = useState('');
    const [generatedLink, setGeneratedLink] = useState('');
    const [invitations, setInvitations] = useState<Invitation[]>([]);
    const [members, setMembers] = useState<TeamMember[]>([]);
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [copied, setCopied] = useState(false);
    const [roleMenuId, setRoleMenuId] = useState<number | null>(null);

    useEffect(() => {
        if (isOpen) {
            setTab('invite');
            setGeneratedLink('');
            setContact('');
            setMessage(null);
            setCopied(false);
            fetchInvitations();
            fetchMembers();
        }
    }, [isOpen]);

    const fetchInvitations = async () => {
        try {
            const res = await api.get('/invitations.php?action=list');
            if (res.data.status === 'success') {
                setInvitations(res.data.data);
            }
        } catch (e) {
            console.error('Error fetching invitations:', e);
        }
    };

    const fetchMembers = async () => {
        try {
            const res = await api.get('/users.php?action=list');
            if (res.data.status === 'success') {
                setMembers(res.data.data.filter((u: TeamMember) => u.activo));
            }
        } catch (e) {
            console.error('Error fetching members:', e);
        }
    };

    const isEmail = (val: string) => /\S+@\S+\.\S+/.test(val);

    const handleCreateInvite = async () => {
        if (!contact.trim()) {
            setMessage({ type: 'error', text: 'Ingresa un email o teléfono' });
            return;
        }

        setLoading(true);
        setMessage(null);
        try {
            const payload = isEmail(contact.trim())
                ? { email: contact.trim() }
                : { telefono: contact.trim() };

            const res = await api.post('/invitations.php?action=create', payload);
            if (res.data.status === 'success') {
                const token = res.data.data.token;
                const link = `${window.location.origin}${window.location.pathname}#/register/${token}`;
                setGeneratedLink(link);
                setMessage({ type: 'success', text: '¡Invitación creada! Comparte el link.' });
                fetchInvitations();
            } else {
                setMessage({ type: 'error', text: res.data.message || 'Error al crear invitación' });
            }
        } catch (e: any) {
            setMessage({ type: 'error', text: e.response?.data?.message || 'Error al crear invitación' });
        } finally {
            setLoading(false);
        }
    };

    const handleCopyLink = async () => {
        try {
            await navigator.clipboard.writeText(generatedLink);
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        } catch {
            // Fallback
            const ta = document.createElement('textarea');
            ta.value = generatedLink;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        }
    };

    const handleShareWhatsApp = () => {
        const orgName = currentUser?.organizacion_nombre || 'nuestro equipo';
        const msg = `¡Hola! 👋 Te invito a unirte a ${orgName} en TuDu.\n\nRegístrate aquí: ${generatedLink}\n\nEs rápido y fácil. ¡Te esperamos! ✨`;
        window.open(`https://wa.me/?text=${encodeURIComponent(msg)}`, '_blank');
    };

    const handleShareEmail = () => {
        const orgName = currentUser?.organizacion_nombre || 'nuestro equipo';
        const subject = `Invitación a ${orgName} en TuDu`;
        const body = `¡Hola!\n\nTe invito a unirte a ${orgName} en TuDu.\n\nPuedes registrarte aquí:\n${generatedLink}\n\n¡Te esperamos!`;
        window.open(`mailto:${contact}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`, '_blank');
    };

    const handleRevokeInvite = async (id: number) => {
        try {
            await api.post('/invitations.php?action=revoke', { id });
            fetchInvitations();
        } catch (e) {
            console.error('Error revoking invitation:', e);
        }
    };

    const handleRoleChange = async (userId: number, newRol: string) => {
        try {
            const member = members.find(m => m.id === userId);
            if (!member) return;
            const data = new FormData();
            data.append('id', userId.toString());
            data.append('nombre', member.nombre);
            data.append('username', member.username);
            data.append('email', member.email || '');
            data.append('telefono', member.telefono || '');
            data.append('rol', newRol);
            data.append('activo', '1');
            await api.post('/users.php?action=update', data);
            setRoleMenuId(null);
            fetchMembers();
            setMessage({ type: 'success', text: 'Rol actualizado' });
        } catch (e: any) {
            setMessage({ type: 'error', text: 'Error al cambiar rol' });
        }
    };

    const handleDeactivate = async (userId: number) => {
        if (!confirm('¿Desactivar este usuario?')) return;
        try {
            const data = new FormData();
            data.append('id', userId.toString());
            await api.post('/users.php?action=delete', data);
            fetchMembers();
            setMessage({ type: 'success', text: 'Usuario desactivado' });
        } catch {
            setMessage({ type: 'error', text: 'Error al desactivar' });
        }
    };

    const pendingInvitations = invitations.filter(i => i.estado === 'pendiente');
    const recentInvitations = invitations.filter(i => i.estado !== 'pendiente').slice(0, 5);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fade-in" onClick={onClose}>
            <div
                className="bg-white dark:bg-tudu-content-dark w-full max-w-2xl max-h-[85vh] rounded-2xl shadow-2xl overflow-hidden flex flex-col"
                onClick={e => e.stopPropagation()}
            >
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 className="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <Users className="text-tudu-accent" size={22} />
                        Mi Equipo
                    </h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-white transition-colors">
                        <X size={24} />
                    </button>
                </div>

                {/* Tabs */}
                <div className="flex border-b border-gray-100 dark:border-gray-700">
                    <button
                        onClick={() => setTab('invite')}
                        className={`flex-1 py-3 px-4 text-sm font-semibold flex items-center justify-center gap-2 transition-colors border-b-2 ${
                            tab === 'invite'
                                ? 'border-tudu-accent text-tudu-accent'
                                : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
                        }`}
                    >
                        <UserPlus size={16} /> Invitar
                    </button>
                    <button
                        onClick={() => setTab('team')}
                        className={`flex-1 py-3 px-4 text-sm font-semibold flex items-center justify-center gap-2 transition-colors border-b-2 ${
                            tab === 'team'
                                ? 'border-tudu-accent text-tudu-accent'
                                : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
                        }`}
                    >
                        <Users size={16} /> Miembros ({members.length})
                    </button>
                </div>

                {/* Messages */}
                {message && (
                    <div className={`mx-6 mt-4 p-3 rounded-lg flex items-center gap-2 text-sm ${
                        message.type === 'success' ? 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300'
                    }`}>
                        {message.type === 'success' ? <CheckCircle size={16} /> : <XCircle size={16} />}
                        {message.text}
                    </div>
                )}

                {/* Content */}
                <div className="flex-1 overflow-y-auto p-6 custom-scrollbar">

                    {/* ═══ TAB: INVITE ═══ */}
                    {tab === 'invite' && (
                        <div className="space-y-6">
                            {/* Invite Input */}
                            <div className="bg-gradient-to-br from-tudu-accent/5 to-purple-500/5 dark:from-tudu-accent/10 dark:to-purple-500/10 rounded-xl p-6 border border-tudu-accent/20">
                                <h3 className="text-sm font-bold text-gray-800 dark:text-white mb-1">Invita a alguien</h3>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mb-4">
                                    Solo necesitas su email o teléfono. Ellos completan su información al registrarse.
                                </p>

                                <div className="flex gap-2">
                                    <div className="relative flex-1">
                                        <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                            {isEmail(contact) ? <Mail size={16} /> : <Phone size={16} />}
                                        </div>
                                        <input
                                            type="text"
                                            value={contact}
                                            onChange={e => { setContact(e.target.value); setGeneratedLink(''); setCopied(false); }}
                                            onKeyDown={e => e.key === 'Enter' && handleCreateInvite()}
                                            placeholder="correo@ejemplo.com o +52 555 123 4567"
                                            className="w-full pl-10 pr-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl text-sm focus:ring-2 focus:ring-tudu-accent outline-none transition-shadow"
                                        />
                                    </div>
                                    <button
                                        onClick={handleCreateInvite}
                                        disabled={loading || !contact.trim()}
                                        className="px-5 py-3 bg-tudu-accent hover:bg-tudu-accent-hover text-white rounded-xl font-semibold text-sm transition-all disabled:opacity-50 flex items-center gap-2 whitespace-nowrap shadow-lg shadow-tudu-accent/20 active:scale-95"
                                    >
                                        <Link2 size={16} />
                                        {loading ? 'Creando...' : 'Generar Link'}
                                    </button>
                                </div>
                            </div>

                            {/* Generated Link Share Zone */}
                            {generatedLink && (
                                <div className="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-600 space-y-4 animate-fade-in">
                                    <div className="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-white">
                                        <PartyPopper size={18} className="text-yellow-500" />
                                        ¡Link listo! Compártelo:
                                    </div>

                                    {/* Link preview */}
                                    <div className="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 text-xs text-gray-600 dark:text-gray-400 font-mono break-all select-all border border-gray-200 dark:border-gray-700">
                                        {generatedLink}
                                    </div>

                                    {/* Share buttons */}
                                    <div className="grid grid-cols-3 gap-3">
                                        <button
                                            onClick={handleShareWhatsApp}
                                            className="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 transition-colors active:scale-95"
                                        >
                                            <Send size={20} />
                                            <span className="text-xs font-semibold">WhatsApp</span>
                                        </button>

                                        <button
                                            onClick={handleCopyLink}
                                            className={`flex flex-col items-center gap-1.5 p-3 rounded-xl transition-all active:scale-95 ${
                                                copied
                                                    ? 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300'
                                                    : 'bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50 text-blue-700 dark:text-blue-300'
                                            }`}
                                        >
                                            {copied ? <Check size={20} /> : <Copy size={20} />}
                                            <span className="text-xs font-semibold">{copied ? '¡Copiado!' : 'Copiar'}</span>
                                        </button>

                                        <button
                                            onClick={handleShareEmail}
                                            className="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-violet-50 dark:bg-violet-900/30 hover:bg-violet-100 dark:hover:bg-violet-900/50 text-violet-700 dark:text-violet-300 transition-colors active:scale-95"
                                        >
                                            <Mail size={20} />
                                            <span className="text-xs font-semibold">Email</span>
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* Pending Invitations */}
                            {pendingInvitations.length > 0 && (
                                <div>
                                    <h4 className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <Clock size={14} /> Invitaciones Pendientes ({pendingInvitations.length})
                                    </h4>
                                    <div className="space-y-2">
                                        {pendingInvitations.map(inv => (
                                            <div key={inv.id} className="flex items-center justify-between p-3 bg-yellow-50/50 dark:bg-yellow-900/10 rounded-lg border border-yellow-200/50 dark:border-yellow-800/30">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-8 h-8 rounded-full bg-yellow-100 dark:bg-yellow-800/40 flex items-center justify-center">
                                                        {inv.email ? <Mail size={14} className="text-yellow-600" /> : <Phone size={14} className="text-yellow-600" />}
                                                    </div>
                                                    <div>
                                                        <div className="text-sm font-medium text-gray-800 dark:text-gray-200">{inv.email || inv.telefono}</div>
                                                        <div className="text-[10px] text-gray-400">
                                                            Expira: {new Date(inv.fecha_expiracion).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' })}
                                                        </div>
                                                    </div>
                                                </div>
                                                <button
                                                    onClick={() => handleRevokeInvite(inv.id)}
                                                    className="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors"
                                                    title="Revocar invitación"
                                                >
                                                    <Trash2 size={14} />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Recent Activity */}
                            {recentInvitations.length > 0 && (
                                <div>
                                    <h4 className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                                        Historial Reciente
                                    </h4>
                                    <div className="space-y-1">
                                        {recentInvitations.map(inv => (
                                            <div key={inv.id} className="flex items-center gap-3 p-2 rounded-lg text-xs text-gray-500 dark:text-gray-400">
                                                {inv.estado === 'aceptada'
                                                    ? <CheckCircle size={14} className="text-green-500 flex-shrink-0" />
                                                    : <XCircle size={14} className="text-gray-400 flex-shrink-0" />
                                                }
                                                <span className="truncate">{inv.email || inv.telefono}</span>
                                                <span className={`ml-auto flex-shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                                    inv.estado === 'aceptada' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                                                }`}>
                                                    {inv.estado === 'aceptada' ? 'Aceptada' : 'Expirada'}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* ═══ TAB: TEAM ═══ */}
                    {tab === 'team' && (
                        <div className="space-y-3">
                            {members.length === 0 ? (
                                <div className="text-center py-10 text-gray-400">
                                    <Users size={40} className="mx-auto mb-3 opacity-30" />
                                    <p className="text-sm">Aún no hay miembros</p>
                                    <p className="text-xs mt-1">¡Invita a tu primer compañero!</p>
                                </div>
                            ) : (
                                members.map(m => (
                                    <div key={m.id} className="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors group">
                                        {/* Avatar */}
                                        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-tudu-accent/30 to-purple-400/30 flex-shrink-0 overflow-hidden flex items-center justify-center">
                                            {m.foto_perfil ? (
                                                <img
                                                    src={m.foto_perfil.startsWith('http') ? m.foto_perfil : `${BASE_URL}/${m.foto_perfil}`}
                                                    alt={m.nombre}
                                                    className="w-full h-full object-cover"
                                                />
                                            ) : (
                                                <span className="text-sm font-bold text-tudu-accent">{m.nombre.charAt(0).toUpperCase()}</span>
                                            )}
                                        </div>

                                        {/* Info */}
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm font-semibold text-gray-800 dark:text-white truncate">{m.nombre}</span>
                                                {m.id === currentUser?.id && (
                                                    <span className="text-[10px] px-1.5 py-0.5 bg-tudu-accent/10 text-tudu-accent rounded-full font-bold">Tú</span>
                                                )}
                                            </div>
                                            <div className="text-xs text-gray-500 dark:text-gray-400 truncate">@{m.username}</div>
                                        </div>

                                        {/* Role Badge */}
                                        <div className="relative">
                                            <button
                                                onClick={() => m.id !== currentUser?.id && setRoleMenuId(roleMenuId === m.id ? null : m.id)}
                                                disabled={m.id === currentUser?.id}
                                                className={`flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold transition-colors ${
                                                    m.rol === 'super_admin' || m.rol === 'admin'
                                                        ? 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300'
                                                        : m.rol === 'administrador'
                                                            ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300'
                                                            : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'
                                                } ${m.id !== currentUser?.id ? 'cursor-pointer hover:ring-2 hover:ring-tudu-accent/30' : 'cursor-default'}`}
                                            >
                                                {m.rol === 'super_admin' || m.rol === 'admin' ? <ShieldCheck size={12} /> : m.rol === 'administrador' ? <Shield size={12} /> : null}
                                                {m.rol === 'super_admin' ? 'Super Admin' : m.rol === 'admin' ? 'Admin' : m.rol === 'administrador' ? 'Admin' : 'Miembro'}
                                                {m.id !== currentUser?.id && <ChevronDown size={10} />}
                                            </button>

                                            {/* Role dropdown */}
                                            {roleMenuId === m.id && (
                                                <div className="absolute right-0 top-full mt-1 w-36 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 py-1 z-20">
                                                    <button onClick={() => handleRoleChange(m.id, 'administrador')} className="w-full px-3 py-2 text-xs text-left hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2">
                                                        <Shield size={12} className="text-blue-500" /> Admin
                                                    </button>
                                                    <button onClick={() => handleRoleChange(m.id, 'usuario')} className="w-full px-3 py-2 text-xs text-left hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2">
                                                        <Users size={12} className="text-gray-500" /> Miembro
                                                    </button>
                                                    <hr className="my-1 border-gray-100 dark:border-gray-700" />
                                                    <button onClick={() => handleDeactivate(m.id)} className="w-full px-3 py-2 text-xs text-left text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 flex items-center gap-2">
                                                        <Trash2 size={12} /> Desactivar
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default UserManagementModal;
