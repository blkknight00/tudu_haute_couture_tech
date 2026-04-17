import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../api/axios';
import { useAuth } from '../contexts/AuthContext';
import { User, Lock, AtSign, ArrowRight, CheckCircle, XCircle, Loader2, PartyPopper } from 'lucide-react';

const RegisterPage = () => {
    const { token } = useParams<{ token: string }>();
    const navigate = useNavigate();
    const { login } = useAuth();

    const [status, setStatus] = useState<'loading' | 'valid' | 'invalid' | 'success' | 'public_register'>('loading');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [orgName, setOrgName] = useState('');
    const [inviteEmail, setInviteEmail] = useState('');
    const [invitePhone, setInvitePhone] = useState('');
    const [errorMsg, setErrorMsg] = useState('');

    const [nombre, setNombre] = useState('');
    const [email, setEmail] = useState('');
    const [empresa, setEmpresa] = useState('');
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [formError, setFormError] = useState('');

    useEffect(() => {
        if (token) {
            validateToken();
        } else {
            setStatus('public_register');
        }
    }, [token]);

    const validateToken = async () => {
        try {
            const res = await api.get(`/invitations.php?action=validate&token=${token}`);
            if (res.data.status === 'success') {
                setOrgName(res.data.data.organizacion_nombre);
                setInviteEmail(res.data.data.email || '');
                setInvitePhone(res.data.data.telefono || '');
                setStatus('valid');
            } else {
                setErrorMsg(res.data.message || 'Invitación no válida');
                setStatus('invalid');
            }
        } catch (e: any) {
            setErrorMsg(e.response?.data?.message || 'Error al validar invitación');
            setStatus('invalid');
        }
    };

    const handleRegister = async (e: React.FormEvent) => {
        e.preventDefault();
        setFormError('');

        if (status === 'public_register' && (!nombre.trim() || !email.trim() || !password)) {
            setFormError('Nombre, Correo y Contraseña son obligatorios');
            return;
        }

        if (status === 'valid' && (!nombre.trim() || !username.trim() || !password)) {
            setFormError('Todos los campos son obligatorios');
            return;
        }

        if (password.length < 4) {
            setFormError('La contraseña debe tener mínimo 4 caracteres');
            return;
        }
        if (password !== confirmPassword) {
            setFormError('Las contraseñas no coinciden');
            return;
        }

        const previousStatus = status;
        setIsSubmitting(true);
        
        try {
            let res;
            if (previousStatus === 'public_register') {
                // Registro publico (Crear Tenant)
                res = await api.post('/register.php', {
                    nombre: nombre.trim(),
                    email: email.trim(),
                    empresa: empresa.trim(),
                    username: username.trim() || undefined,
                    password,
                    plan: 'starter'
                });
            } else {
                // Registro por invitación
                res = await api.post('/auth.php?action=register', {
                    nombre: nombre.trim(),
                    username: username.trim().toLowerCase().replace(/\s/g, ''),
                    password,
                    invite_token: token
                });
            }

            if (res.data.status === 'success') {
                setStatus('success');
                // Si es public_register, podemos guardar orgName para el mensaje de éxito
                if (previousStatus === 'public_register') {
                    setOrgName(empresa);
                }
                setTimeout(() => {
                    login(res.data.user, undefined, res.data.token);
                    navigate('/');
                }, 1500);
            } else {
                setFormError(res.data.message || 'Error al registrarse');
                // No revertimos el status ya que se mantiene igual, solo apagamos el submitting
            }
        } catch (e: any) {
            setFormError(e.response?.data?.message || 'Error al registrarse');
        } finally {
            setIsSubmitting(false);
        }
    };

    // Auto-generate username from name
    const handleNameChange = (val: string) => {
        setNombre(val);
        if (!username || username === nombre.trim().toLowerCase().replace(/\s+/g, '.')) {
            setUsername(val.trim().toLowerCase().replace(/\s+/g, '.'));
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 p-4">
            <div className="w-full max-w-md">

                {/* Logo */}
                <div className="text-center mb-8">
                    <h1 className="text-3xl font-black text-white tracking-tight">
                        Tu<span className="text-tudu-accent">Du</span>
                    </h1>
                </div>

                {/* Card */}
                <div className="bg-white/10 backdrop-blur-xl rounded-2xl border border-white/10 shadow-2xl overflow-hidden">

                    {/* Loading */}
                    {status === 'loading' && (
                        <div className="p-12 flex flex-col items-center gap-4 text-white/70">
                            <Loader2 size={32} className="animate-spin text-tudu-accent" />
                            <p className="text-sm">Validando invitación...</p>
                        </div>
                    )}

                    {/* Invalid */}
                    {status === 'invalid' && (
                        <div className="p-12 flex flex-col items-center gap-4 text-center">
                            <div className="w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center">
                                <XCircle size={32} className="text-red-400" />
                            </div>
                            <h2 className="text-lg font-bold text-white">Invitación No Válida</h2>
                            <p className="text-sm text-white/60">{errorMsg}</p>
                            <button
                                onClick={() => navigate('/')}
                                className="mt-4 px-6 py-2 bg-tudu-accent hover:bg-tudu-accent-hover text-white rounded-xl text-sm font-semibold transition-colors"
                            >
                                Ir al inicio
                            </button>
                        </div>
                    )}

                    {/* Success */}
                    {status === 'success' && (
                        <div className="p-12 flex flex-col items-center gap-4 text-center">
                            <div className="w-16 h-16 rounded-full bg-green-500/20 flex items-center justify-center animate-bounce">
                                <PartyPopper size={32} className="text-green-400" />
                            </div>
                            <h2 className="text-lg font-bold text-white">¡Bienvenido a {orgName}!</h2>
                            <p className="text-sm text-white/60">Tu cuenta ha sido creada. Redirigiendo...</p>
                        </div>
                    )}

                    {/* Registration Form */}
                    {(status === 'valid' || status === 'public_register') && (
                        <form onSubmit={handleRegister} className="p-6 space-y-5">
                            {/* Header */}
                            <div className="text-center pb-2">
                                {status === 'public_register' ? (
                                    <>
                                        <h2 className="text-xl font-bold text-white">Empieza tu prueba gratis</h2>
                                        <p className="text-xs text-white/50 mt-1">14 días de prueba. Sin tarjeta de crédito inicial.</p>
                                    </>
                                ) : (
                                    <>
                                        <h2 className="text-lg font-bold text-white">Únete a <span className="text-tudu-accent">{orgName}</span></h2>
                                        <p className="text-xs text-white/50 mt-1">
                                            {inviteEmail ? `Invitación para ${inviteEmail}` : invitePhone ? `Invitación para ${invitePhone}` : 'Crea tu cuenta'}
                                        </p>
                                    </>
                                )}
                            </div>

                            {formError && (
                                <div className="p-3 bg-red-500/20 border border-red-500/30 rounded-lg text-sm text-red-300 flex items-center gap-2">
                                    <XCircle size={14} /> {formError}
                                </div>
                            )}

                            {/* Name */}
                            <div className="space-y-1.5">
                                <label className="text-xs font-medium text-white/70">Tu nombre completo</label>
                                <div className="relative">
                                    <User size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-white/30" />
                                    <input
                                        type="text"
                                        value={nombre}
                                        onChange={e => handleNameChange(e.target.value)}
                                        placeholder="Juan Pérez"
                                        className="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/10 rounded-xl text-white placeholder-white/30 text-sm focus:ring-2 focus:ring-tudu-accent focus:border-transparent outline-none"
                                        disabled={isSubmitting}
                                    />
                                </div>
                            </div>

                            {status === 'public_register' ? (
                                <>
                                    {/* Email */}
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-medium text-white/70">Correo electrónico</label>
                                        <div className="relative">
                                            <AtSign size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-white/30" />
                                            <input
                                                type="email"
                                                value={email}
                                                onChange={e => setEmail(e.target.value)}
                                                placeholder="juan@empresa.com"
                                                className="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/10 rounded-xl text-white placeholder-white/30 text-sm focus:ring-2 focus:ring-tudu-accent focus:border-transparent outline-none"
                                                disabled={isSubmitting}
                                            />
                                        </div>
                                    </div>
                                    {/* Empresa */}
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-medium text-white/70">Nombre de tu Empresa / Workspace <span className="text-white/40 italic">(Opcional)</span></label>
                                        <div className="relative">
                                            <User size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-white/30" />
                                            <input
                                                type="text"
                                                value={empresa}
                                                onChange={e => setEmpresa(e.target.value)}
                                                placeholder="Mi Empresa S.A de C.V"
                                                className="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/10 rounded-xl text-white placeholder-white/30 text-sm focus:ring-2 focus:ring-tudu-accent focus:border-transparent outline-none"
                                                disabled={isSubmitting}
                                            />
                                        </div>
                                    </div>
                                </>
                            ) : (
                                <div className="space-y-1.5">
                                    {/* Username */}
                                    <label className="text-xs font-medium text-white/70">Usuario</label>
                                    <div className="relative">
                                        <AtSign size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-white/30" />
                                        <input
                                            type="text"
                                            value={username}
                                            onChange={e => setUsername(e.target.value.toLowerCase().replace(/\s/g, ''))}
                                            placeholder="tu.usuario"
                                            className="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/10 rounded-xl text-white placeholder-white/30 text-sm focus:ring-2 focus:ring-tudu-accent focus:border-transparent outline-none"
                                            disabled={isSubmitting}
                                        />
                                    </div>
                                </div>
                            )}

                            {/* Password */}
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <label className="text-xs font-medium text-white/70">Contraseña</label>
                                    <div className="relative">
                                        <Lock size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-white/30" />
                                        <input
                                            type="password"
                                            value={password}
                                            onChange={e => setPassword(e.target.value)}
                                            placeholder="••••••"
                                            className="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/10 rounded-xl text-white placeholder-white/30 text-sm focus:ring-2 focus:ring-tudu-accent focus:border-transparent outline-none"
                                            disabled={isSubmitting}
                                        />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-xs font-medium text-white/70">Confirmar</label>
                                    <div className="relative">
                                        <CheckCircle size={16} className={`absolute left-3 top-1/2 -translate-y-1/2 ${password && confirmPassword && password === confirmPassword ? 'text-green-400' : 'text-white/30'}`} />
                                        <input
                                            type="password"
                                            value={confirmPassword}
                                            onChange={e => setConfirmPassword(e.target.value)}
                                            placeholder="••••••"
                                            className="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/10 rounded-xl text-white placeholder-white/30 text-sm focus:ring-2 focus:ring-tudu-accent focus:border-transparent outline-none"
                                            disabled={isSubmitting}
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Submit */}
                            <button
                                type="submit"
                                disabled={isSubmitting}
                                className="w-full py-3.5 bg-tudu-accent hover:bg-tudu-accent-hover text-white rounded-xl font-bold text-sm transition-all flex items-center justify-center gap-2 shadow-lg shadow-tudu-accent/30 disabled:opacity-60 active:scale-[0.98]"
                            >
                                {isSubmitting ? (
                                    <><Loader2 size={16} className="animate-spin" /> {status === 'public_register' ? 'Creando cuenta...' : 'Uniéndose...'}</>
                                ) : (
                                    <><ArrowRight size={16} /> {status === 'public_register' ? 'Registrarse Gratis' : 'Crear mi cuenta'}</>
                                )}
                            </button>

                            <p className="text-center text-[10px] text-white/30">
                                Al registrarte aceptas los términos de uso de TuDu
                            </p>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
};

export default RegisterPage;
