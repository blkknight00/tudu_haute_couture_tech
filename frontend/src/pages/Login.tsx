import React, { useState, useEffect } from 'react';
import { useAuth } from '../contexts/AuthContext';
import api, { BASE_URL } from '../api/axios';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import LanguageSwitcher from '../components/LanguageSwitcher';
import { Eye, EyeOff } from 'lucide-react';

// ── WebAuthn helpers ──────────────────────────────────────────────────────────

function b64uToBuffer(b64u: string): ArrayBuffer {
    const pad = '='.repeat((4 - b64u.length % 4) % 4);
    const b64 = b64u.replace(/-/g, '+').replace(/_/g, '/') + pad;
    const bin = atob(b64);
    const buf = new ArrayBuffer(bin.length);
    const u8 = new Uint8Array(buf);
    for (let i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
    return buf;
}

function bufferToB64u(buf: ArrayBuffer): string {
    const u8 = new Uint8Array(buf);
    let str = '';
    u8.forEach(b => str += String.fromCharCode(b));
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

const BIO_STORAGE_KEY = 'tudu_bio_user';

// ── Component ─────────────────────────────────────────────────────────────────

const Login = () => {
    const { t } = useTranslation();
    const { login } = useAuth();
    const navigate = useNavigate();
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [bioLoading, setBioLoading] = useState(false);
    const [webAuthnOK, setWebAuthnOK] = useState(false);
    const [bioMode, setBioMode] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [savedBioUser, setSavedBioUser] = useState<string | null>(null); // remembered user
    const usernameRef = React.useRef<HTMLInputElement>(null);

    // On mount: detect WebAuthn support + check if user is remembered
    useEffect(() => {
        document.documentElement.classList.add('dark');
        
        const ok = typeof window !== 'undefined' &&
            !!window.PublicKeyCredential &&
            location.protocol === 'https:';
        setWebAuthnOK(ok);

        if (ok) {
            const stored = localStorage.getItem(BIO_STORAGE_KEY);
            if (stored) {
                setSavedBioUser(stored);
                setUsername(stored);
                // Auto-trigger biometric after a short visual delay
                setTimeout(() => triggerBiometric(stored), 800);
            }
        }
    }, []);

    // ── Normal login ──────────────────────────────────────────────────────────
    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (bioMode) { triggerBiometric(username); return; }
        setError('');
        setLoading(true);
        try {
            const res = await api.post('/auth.php?action=login', { username, password });
            if (res.data.status === 'success') {
                login(res.data.user, res.data.edition, res.data.token);
                navigate('/');
            } else {
                setError(res.data.message || 'Error al iniciar sesión');
            }
        } catch {
            setError('Error de conexión. Inténtalo de nuevo.');
        } finally {
            setLoading(false);
        }
    };

    // ── Biometric login ───────────────────────────────────────────────────────
    const triggerBiometric = async (user: string) => {
        if (!user.trim()) {
            setError('Escribe tu usuario y presiona Enter o el botón 🔑');
            setBioMode(true);
            setTimeout(() => usernameRef.current?.focus(), 50);
            return;
        }
        setBioMode(false);
        setError('');
        setBioLoading(true);
        try {
            const challengeRes = await api.post('/webauthn.php', {
                action: 'auth_challenge',
                username: user.trim(),
            });
            if (challengeRes.data.status !== 'success') {
                // No credential stored for this user — fall back to password
                localStorage.removeItem(BIO_STORAGE_KEY);
                setSavedBioUser(null);
                setError(challengeRes.data.message || 'No hay huella registrada. Usa tu contraseña.');
                return;
            }

            const opts = challengeRes.data.options;

            const publicKey: PublicKeyCredentialRequestOptions = {
                challenge: b64uToBuffer(opts.challenge),
                rpId: opts.rpId,
                timeout: opts.timeout,
                userVerification: 'required',
                allowCredentials: opts.allowCredentials.map((c: any) => ({
                    type: 'public-key' as const,
                    id: b64uToBuffer(c.id),
                })),
            };

            const credential = await navigator.credentials.get({ publicKey }) as PublicKeyCredential | null;
            if (!credential) throw new Error('No se pudo obtener la credencial');

            const response = credential.response as AuthenticatorAssertionResponse;

            const verifyRes = await api.post('/webauthn.php', {
                action: 'auth_verify',
                clientDataJSON: bufferToB64u(response.clientDataJSON),
                authenticatorData: bufferToB64u(response.authenticatorData),
                signature: bufferToB64u(response.signature),
            });

            if (verifyRes.data.status === 'success') {
                // Remember this user for next time
                localStorage.setItem(BIO_STORAGE_KEY, user.trim());
                login(verifyRes.data.user, verifyRes.data.edition, verifyRes.data.token);
                navigate('/');
            } else {
                setError(verifyRes.data.message || 'Verificación biométrica fallida.');
            }
        } catch (err: any) {
            if (err?.name === 'NotAllowedError') {
                setError('Huella cancelada. Usa tu contraseña o intenta de nuevo.');
            } else {
                setError(err?.message || 'Error en autenticación biométrica.');
            }
        } finally {
            setBioLoading(false);
        }
    };

    const handleBiometric = () => triggerBiometric(username);

    const forgetBioUser = () => {
        localStorage.removeItem(BIO_STORAGE_KEY);
        setSavedBioUser(null);
        setUsername('');
        setError('');
        setBioMode(false);
    };

    // ── Remembered-user biometric screen (banking app style) ──────────────────
    if (savedBioUser && webAuthnOK) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-transparent transition-colors duration-500 relative">
                {/* Background is handled globally by GradientBg */}
                <div className="bg-white/80 dark:bg-tudu-content-dark/80 backdrop-blur-lg p-8 rounded-2xl shadow-2xl w-full max-w-sm border border-white/20 dark:border-gray-700 text-center">
                    {/* Logo */}
                    <div className="w-20 h-20 bg-white/50 dark:bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4 shadow-xl border border-white/20">
                        <img src={`${BASE_URL}/tudu-logo-transparent.png`} alt="TuDu Logo" className="w-14 h-14 object-contain" />
                    </div>
                    <h2 className="text-2xl font-bold text-tudu-text-light dark:text-tudu-text-dark mb-1">Bienvenido</h2>
                    <p className="text-tudu-text-muted mb-8 text-sm">{savedBioUser}</p>

                    {error && (
                        <div className="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 mb-6 rounded-r text-sm text-left" role="alert">
                            {error}
                        </div>
                    )}

                    {/* Big fingerprint button */}
                    <button
                        onClick={() => triggerBiometric(savedBioUser)}
                        disabled={bioLoading}
                        className="w-full flex flex-col items-center justify-center gap-3 py-8 px-4 rounded-2xl bg-tudu-accent hover:bg-tudu-accent-hover text-white transition-all transform hover:scale-[1.02] disabled:opacity-70 shadow-xl mb-4 group"
                    >
                        {bioLoading ? (
                            <span className="w-12 h-12 border-4 border-white/30 border-t-white rounded-full animate-spin" />
                        ) : (
                            <span className="text-6xl group-hover:scale-110 transition-transform duration-300">🔑</span>
                        )}
                        <span className="text-base font-semibold">
                            {bioLoading ? 'Verificando...' : 'Toca para entrar con huella'}
                        </span>
                    </button>

                    {/* Secondary: password login */}
                    <button
                        type="button"
                        onClick={forgetBioUser}
                        className="w-full text-sm text-gray-500 hover:text-tudu-accent dark:text-gray-400 dark:hover:text-tudu-accent py-2 transition-colors"
                    >
                        Usar contraseña / Cambiar usuario
                    </button>
                </div>
            </div >
        );
    }

    return (
        <div className="min-h-screen flex items-center justify-center bg-transparent transition-colors duration-500 relative">
            <div className="bg-white/80 dark:bg-tudu-content-dark/80 backdrop-blur-lg p-8 rounded-2xl shadow-2xl w-full max-w-md border border-white/20 dark:border-gray-700 relative">
                <div className="absolute top-4 right-4">
                    <LanguageSwitcher />
                </div>
                <div className="text-center mb-8">
                    <div className="w-20 h-20 bg-white/50 dark:bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4 shadow-xl border border-white/20">
                        <img src={`${BASE_URL}/tudu-logo-transparent.png`} alt="TuDu Logo" className="w-14 h-14 object-contain" />
                    </div>
                    <h2 className="text-3xl font-bold text-tudu-text-light dark:text-tudu-text-dark">Bienvenido</h2>
                    <p className="text-tudu-text-muted mt-2">{t('auth.login', 'Inicia sesión en TuDu')}</p>
                </div>

                {error && (
                    <div className="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r" role="alert">
                        <p>{error}</p>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div>
                        <label className="block text-sm font-medium text-tudu-text-light dark:text-tudu-text-dark mb-1">{t('auth.email', 'Usuario o Correo')}</label>
                        <input
                            ref={usernameRef}
                            type="text"
                            value={username}
                            onChange={(e) => setUsername(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && bioMode) {
                                    e.preventDefault();
                                    handleBiometric();
                                }
                            }}
                            className={`w-full px-4 py-3 rounded-lg bg-gray-50 dark:bg-gray-800 border focus:ring-2 focus:ring-tudu-accent focus:border-transparent outline-none transition-all dark:text-white ${bioMode ? 'border-tudu-accent ring-2 ring-tudu-accent/30' : 'border-gray-300 dark:border-gray-600'}`}
                            placeholder="Tu usuario"
                            required
                            autoComplete="username"
                        />
                        {bioMode && (
                            <p className="mt-2 text-xs text-tudu-accent font-medium flex items-center gap-1">
                                <span>🔑</span> Escribe tu usuario y presiona <strong>Continuar con huella</strong>
                            </p>
                        )}
                    </div>

                    {/* Password section — hidden in bioMode */}
                    {!bioMode && (
                        <>
                            <div>
                                <label className="block text-sm font-medium text-tudu-text-light dark:text-tudu-text-dark mb-1">{t('auth.password', 'Contraseña')}</label>
                                <div className="relative">
                                    <input
                                        type={showPassword ? "text" : "password"}
                                        value={password}
                                        onChange={(e) => setPassword(e.target.value)}
                                        className="w-full px-4 py-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-tudu-accent focus:border-transparent outline-none transition-all dark:text-white pr-10"
                                        placeholder="••••••••"
                                        autoComplete="current-password"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                                    >
                                        {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
                                    </button>
                                </div>
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="flex items-center">
                                    <input id="remember-me" type="checkbox" className="h-4 w-4 text-tudu-accent focus:ring-tudu-accent border-gray-300 rounded" />
                                    <label htmlFor="remember-me" className="ml-2 block text-sm text-tudu-text-muted">Recuérdame</label>
                                </div>
                                <a href="#" className="text-sm font-medium text-tudu-accent hover:text-tudu-accent-hover">¿Olvidaste tu contraseña?</a>
                            </div>

                            <button
                                type="submit"
                                disabled={loading || bioLoading}
                                className="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-tudu-accent hover:bg-tudu-accent-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-tudu-accent transition-all transform hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {loading ? 'Iniciando sesión...' : 'Ingresar'}
                            </button>
                        </>
                    )}

                    {/* Biometric mode: big button + cancel */}
                    {bioMode && (
                        <div className="space-y-3">
                            <button
                                type="submit"
                                disabled={bioLoading}
                                className="w-full flex items-center justify-center gap-3 py-4 px-4 border-2 border-tudu-accent rounded-lg text-sm font-semibold text-white bg-tudu-accent hover:bg-tudu-accent-hover transition-all transform hover:scale-[1.01] disabled:opacity-60 shadow-lg"
                            >
                                {bioLoading ? (
                                    <span className="w-5 h-5 border-2 border-white/40 border-t-white rounded-full animate-spin" />
                                ) : (
                                    <span className="text-2xl">🔑</span>
                                )}
                                <span>{bioLoading ? 'Verificando con huella...' : 'Continuar con huella digital'}</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => { setBioMode(false); setError(''); }}
                                className="w-full text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 py-1 transition-colors"
                            >
                                ← Volver a contraseña
                            </button>
                        </div>
                    )}

                    {/* Biometric option — only in normal mode, HTTPS, WebAuthn supported */}
                    {webAuthnOK && !bioMode && (
                        <>
                            <div className="relative">
                                <div className="absolute inset-0 flex items-center">
                                    <div className="w-full border-t border-gray-300 dark:border-gray-600" />
                                </div>
                                <div className="relative flex justify-center text-sm">
                                    <span className="px-2 bg-white/80 dark:bg-tudu-content-dark/80 text-gray-500">o</span>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={handleBiometric}
                                disabled={bioLoading || loading}
                                className="w-full flex items-center justify-center gap-3 py-3 px-4 border-2 border-tudu-accent/40 hover:border-tudu-accent rounded-lg text-sm font-medium text-tudu-text-light dark:text-tudu-text-dark hover:bg-tudu-accent/5 transition-all disabled:opacity-50 disabled:cursor-not-allowed group"
                            >
                                <span className="text-2xl group-hover:scale-110 transition-transform">🔑</span>
                                <span>Ingresar con huella digital</span>
                            </button>
                        </>
                    )}
                </form>
            </div>
        </div>
    );
};

export default Login;
