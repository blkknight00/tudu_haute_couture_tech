import { useState, useEffect, useRef } from 'react';
import api, { BASE_URL } from '../../api/axios';
import { User, Camera, Save, Lock, Phone, UserCircle, CheckCircle, AlertTriangle, X, Fingerprint } from 'lucide-react';

interface ProfileModalProps {
    isOpen: boolean;
    onClose: () => void;
}

const ProfileModal = ({ isOpen, onClose }: ProfileModalProps) => {
    // const { user } = useAuth(); // Not strictly needed if we fetch fresh data, but good for context

    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);
    const [formData, setFormData] = useState({
        nombre: '',
        username: '',
        telefono: '',
        current_password: '',
        new_password: '',
        confirm_password: ''
    });
    const [previewImage, setPreviewImage] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Biometric state
    const [bioRegistered, setBioRegistered] = useState(false);
    const [bioLoading, setBioLoading] = useState(false);
    const [bioMessage, setBioMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);
    const [webAuthnOK, setWebAuthnOK] = useState(false);

    useEffect(() => {
        setWebAuthnOK(
            typeof window !== 'undefined' &&
            !!window.PublicKeyCredential &&
            location.protocol === 'https:'
        );
    }, []);

    // Reset loop
    useEffect(() => {
        if (isOpen) {
            fetchProfile();
            setMessage(null);
            fetchBioStatus();
            setFormData(prev => ({
                ...prev,
                current_password: '',
                new_password: '',
                confirm_password: ''
            }));
        }
    }, [isOpen]);

    const fetchProfile = async () => {
        try {
            const res = await api.get('/profile.php?action=get_profile');
            if (res.data.status === 'success') {
                const data = res.data.data;
                setFormData(prev => ({
                    ...prev,
                    nombre: data.nombre,
                    username: data.username,
                    telefono: data.telefono || ''
                }));
                if (data.foto_perfil) setPreviewImage(data.foto_perfil);
            }
        } catch (error) {
            console.error(error);
        }
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            const file = e.target.files[0];
            setPreviewImage(URL.createObjectURL(file));
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setMessage(null);
        setLoading(true);

        if (formData.new_password && formData.new_password !== formData.confirm_password) {
            setMessage({ type: 'error', text: 'Las nuevas contraseñas no coinciden.' });
            setLoading(false);
            return;
        }

        try {
            const data = new FormData();
            data.append('nombre', formData.nombre);
            data.append('username', formData.username);
            data.append('telefono', formData.telefono);

            if (formData.new_password) {
                data.append('current_password', formData.current_password);
                data.append('new_password', formData.new_password);
            }

            if (fileInputRef.current?.files?.[0]) {
                data.append('foto_perfil', fileInputRef.current.files[0]);
            }

            const res = await api.post('/profile.php?action=update_profile', data, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            if (res.data.status === 'success') {
                setMessage({ type: 'success', text: 'Perfil actualizado correctamente.' });
                // Reset password
                setFormData(prev => ({ ...prev, current_password: '', new_password: '', confirm_password: '' }));
                // Re-fetch profile to get the updated photo path from the server
                await fetchProfile();
            } else {
                setMessage({ type: 'error', text: res.data.message || 'Error al actualizar.' });
            }

        } catch (error: any) {
            setMessage({
                type: 'error',
                text: error.response?.data?.message || 'Error de conexión.'
            });
        } finally {
            setLoading(false);
        }
    };

    // ── WebAuthn helpers (inline) ─────────────────────────────────────────────
    function b64uToBuffer(b64u: string): ArrayBuffer {
        const pad = '='.repeat((4 - b64u.length % 4) % 4);
        const b64 = b64u.replace(/-/g, '+').replace(/_/g, '/') + pad;
        const bin = atob(b64); const buf = new ArrayBuffer(bin.length);
        const u8 = new Uint8Array(buf);
        for (let i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
        return buf;
    }
    function bufferToB64u(buf: ArrayBuffer): string {
        const u8 = new Uint8Array(buf); let str = '';
        u8.forEach(b => str += String.fromCharCode(b));
        return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    }

    const fetchBioStatus = async () => {
        try {
            const res = await api.post('/webauthn.php', { action: 'check' });
            if (res.data.status === 'success') setBioRegistered(res.data.registered);
        } catch {/* silent */ }
    };

    const registerBiometric = async () => {
        setBioLoading(true); setBioMessage(null);
        try {
            const res = await api.post('/webauthn.php', { action: 'register_challenge' });
            if (res.data.status !== 'success') throw new Error(res.data.message);
            const opts = res.data.options;

            const publicKey: PublicKeyCredentialCreationOptions = {
                challenge: b64uToBuffer(opts.challenge),
                rp: opts.rp,
                user: {
                    id: b64uToBuffer(opts.user.id),
                    name: opts.user.name,
                    displayName: opts.user.displayName,
                },
                pubKeyCredParams: opts.pubKeyCredParams,
                authenticatorSelection: opts.authenticatorSelection,
                timeout: opts.timeout,
                attestation: 'none',
            };

            const credential = await navigator.credentials.create({ publicKey }) as PublicKeyCredential | null;
            if (!credential) throw new Error('No se pudo crear la credencial');

            const resp = credential.response as AuthenticatorAttestationResponse;
            const verifyRes = await api.post('/webauthn.php', {
                action: 'register_verify',
                credentialId: bufferToB64u(credential.rawId),
                clientDataJSON: bufferToB64u(resp.clientDataJSON),
                attestationObject: bufferToB64u(resp.attestationObject),
            });

            if (verifyRes.data.status === 'success') {
                setBioRegistered(true);
                setBioMessage({ type: 'success', text: '✅ Huella registrada correctamente' });
                // Remember this user so the app auto-prompts on next open
                if (formData.username) {
                    localStorage.setItem('tudu_bio_user', formData.username);
                }
            } else {
                throw new Error(verifyRes.data.message);
            }
        } catch (err: any) {
            if (err?.name === 'NotAllowedError') {
                setBioMessage({ type: 'error', text: 'Registro cancelado.' });
            } else {
                setBioMessage({ type: 'error', text: err?.message || 'Error al registrar huella.' });
            }
        } finally {
            setBioLoading(false);
        }
    };

    const deleteBiometric = async () => {
        if (!confirm('¿Eliminar la huella digital registrada?')) return;
        try {
            await api.post('/webauthn.php', { action: 'delete' });
            setBioRegistered(false);
            setBioMessage({ type: 'success', text: 'Huella eliminada.' });
            localStorage.removeItem('tudu_bio_user');
        } catch {
            setBioMessage({ type: 'error', text: 'Error al eliminar.' });
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fade-in" onClick={onClose}>
            <div
                className="bg-white dark:bg-tudu-content-dark w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
                onClick={e => e.stopPropagation()}
            >
                {/* Close Button */}
                <button
                    onClick={onClose}
                    className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 transition-colors z-10"
                >
                    <X size={24} />
                </button>

                <div className="flex-1 overflow-y-auto custom-scrollbar p-8">
                    {/* Avatar */}
                    <div className="relative mb-6 flex justify-center sm:justify-start">
                        <div className="relative group">
                            <div className="w-24 h-24 rounded-full border-4 border-white dark:border-tudu-content-dark bg-gray-200 overflow-hidden shadow-md">
                                {previewImage ? (
                                    <img
                                        src={previewImage.startsWith('blob:') || previewImage.startsWith('http') ? previewImage : `${BASE_URL}/${previewImage}`}
                                        alt="Profile"
                                        className="w-full h-full object-cover"
                                    />
                                ) : (
                                    <div className="w-full h-full flex items-center justify-center text-gray-400">
                                        <User size={48} />
                                    </div>
                                )}
                            </div>
                            <button
                                type="button"
                                onClick={() => fileInputRef.current?.click()}
                                className="absolute bottom-0 right-0 bg-white dark:bg-gray-800 p-1.5 rounded-full shadow-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 text-gray-600 dark:text-gray-300 transition-transform transform active:scale-95"
                            >
                                <Camera size={14} />
                            </button>
                            <input
                                type="file"
                                ref={fileInputRef}
                                onChange={handleFileChange}
                                className="hidden"
                                accept="image/*"
                            />
                        </div>
                        <div className="ml-4 mt-14 sm:mt-14">
                            <h2 className="text-xl font-bold text-gray-800 dark:text-white">Editar Perfil</h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400">Actualiza tu información personal</p>
                        </div>
                    </div>

                    {message && (
                        <div className={`mb-6 p-3 rounded-lg flex items-center gap-3 text-sm ${message.type === 'success' ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-red-50 text-red-700 border border-red-100'}`}>
                            {message.type === 'success' ? <CheckCircle size={16} /> : <AlertTriangle size={16} />}
                            <p>{message.text}</p>
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Fields */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-1">
                                <label className="text-xs font-medium text-gray-700 dark:text-gray-300">Nombre</label>
                                <div className="relative">
                                    <UserCircle className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={16} />
                                    <input
                                        type="text"
                                        name="nombre"
                                        value={formData.nombre}
                                        onChange={handleChange}
                                        className="w-full pl-9 pr-3 py-2 bg-gray-50 dark:bg-tudu-bg-dark border border-gray-200 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-tudu-accent outline-none"
                                        required
                                    />
                                </div>
                            </div>
                            <div className="space-y-1">
                                <label className="text-xs font-medium text-gray-700 dark:text-gray-300">Usuario</label>
                                <div className="relative">
                                    <div className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 font-bold text-xs">@</div>
                                    <input
                                        type="text"
                                        name="username"
                                        value={formData.username}
                                        onChange={handleChange}
                                        className="w-full pl-8 pr-3 py-2 bg-gray-50 dark:bg-tudu-bg-dark border border-gray-200 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-tudu-accent outline-none"
                                        required
                                    />
                                </div>
                            </div>
                            <div className="space-y-1 md:col-span-2">
                                <label className="text-xs font-medium text-gray-700 dark:text-gray-300">Teléfono</label>
                                <div className="relative">
                                    <Phone className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={16} />
                                    <input
                                        type="tel"
                                        name="telefono"
                                        value={formData.telefono}
                                        onChange={handleChange}
                                        className="w-full pl-9 pr-3 py-2 bg-gray-50 dark:bg-tudu-bg-dark border border-gray-200 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-tudu-accent outline-none"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="bg-gray-50 dark:bg-gray-800/50 p-4 rounded-xl border border-gray-100 dark:border-gray-700">
                            <h3 className="text-sm font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                                <Lock size={16} className="text-tudu-accent" />
                                Seguridad
                            </h3>
                            <div className="grid grid-cols-1 gap-3">
                                <input
                                    type="password"
                                    name="current_password"
                                    value={formData.current_password}
                                    onChange={handleChange}
                                    className="w-full px-3 py-2 bg-white dark:bg-tudu-bg-dark border border-gray-200 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-tudu-accent outline-none"
                                    placeholder="Contraseña Actual (si vas a cambiarla)"
                                />
                                <div className="grid grid-cols-2 gap-3">
                                    <input
                                        type="password"
                                        name="new_password"
                                        value={formData.new_password}
                                        onChange={handleChange}
                                        className="w-full px-3 py-2 bg-white dark:bg-tudu-bg-dark border border-gray-200 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-tudu-accent outline-none"
                                        placeholder="Nueva Contraseña"
                                    />
                                    <input
                                        type="password"
                                        name="confirm_password"
                                        value={formData.confirm_password}
                                        onChange={handleChange}
                                        className="w-full px-3 py-2 bg-white dark:bg-tudu-bg-dark border border-gray-200 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-tudu-accent outline-none"
                                        placeholder="Confirmar"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={loading}
                                className="flex items-center gap-2 bg-tudu-accent hover:bg-tudu-accent-hover text-white px-6 py-2 rounded-lg text-sm font-medium shadow-md transition-all disabled:opacity-70"
                            >
                                {loading ? (
                                    <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                ) : (
                                    <Save size={16} />
                                )}
                                Guardar
                            </button>
                        </div>
                    </form>

                    {/* Biometric Registration Section */}
                    {webAuthnOK && (
                        <div className="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 className="text-sm font-semibold text-tudu-text-muted mb-4 uppercase tracking-wider flex items-center gap-2">
                                <Fingerprint size={16} /> Acceso Biométrico
                            </h3>

                            {bioMessage && (
                                <div className={`mb-3 p-3 rounded-lg text-sm ${bioMessage.type === 'success' ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-300' : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300'}`}>
                                    {bioMessage.text}
                                </div>
                            )}

                            <div className="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-xl">
                                <div className="flex items-center gap-3">
                                    <div className={`p-2 rounded-full ${bioRegistered ? 'bg-green-100 dark:bg-green-900/30' : 'bg-gray-200 dark:bg-gray-700'}`}>
                                        <Fingerprint size={22} className={bioRegistered ? 'text-green-600' : 'text-gray-400'} />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                            {bioRegistered ? 'Huella registrada' : 'Sin huella registrada'}
                                        </p>
                                        <p className="text-xs text-gray-400">
                                            {bioRegistered ? 'Puedes ingresar sin contraseña' : 'Regístrala para ingresar rápido'}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex gap-2">
                                    {bioRegistered ? (
                                        <button
                                            type="button"
                                            onClick={deleteBiometric}
                                            className="text-xs px-3 py-1.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20 transition-colors"
                                        >
                                            Eliminar
                                        </button>
                                    ) : null}
                                    <button
                                        type="button"
                                        onClick={registerBiometric}
                                        disabled={bioLoading}
                                        className="flex items-center gap-1.5 text-xs px-4 py-1.5 rounded-lg bg-tudu-accent hover:bg-tudu-accent-hover text-white transition-colors disabled:opacity-60"
                                    >
                                        {bioLoading ? (
                                            <span className="w-3 h-3 border border-white/30 border-t-white rounded-full animate-spin" />
                                        ) : (
                                            <Fingerprint size={13} />
                                        )}
                                        {bioRegistered ? 'Actualizar' : 'Registrar'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default ProfileModal;
