import { useState, useEffect } from 'react';
import { X, Save, Key, AppWindow, MessageSquare, Eye, EyeOff, Volume2, Mic, CheckCircle, XCircle, Loader } from 'lucide-react';
import api from '../../api/axios';

interface AISettingsModalProps {
    isOpen: boolean;
    onClose: () => void;
}

const ELEVENLABS_VOICES = [
    { id: 'cgSgspJ2msm6clMCkdW9', label: 'Jessica — Joven, cálida (recomendada para español)' },
    { id: 'EXAVITQu4vr4xnSDxMaL', label: 'Bella — Expresiva, femenina' },
    { id: 'FGY2WhTYpPnrIDTdsKH5', label: 'Laura — Natural, clara' },
    { id: 'pFZP5JQG7iQjIQuC4Bku', label: 'Lily — Suave, juvenil' },
    { id: 'XrExE9yKIg1WjnnlVkGX', label: 'Lily (alt) — Joven, multilingüe' },
];

const AISettingsModal = ({ isOpen, onClose }: AISettingsModalProps) => {
    const [apiKey, setApiKey] = useState('');
    const [elevenLabsKey, setElevenLabsKey] = useState('');
    const [elevenLabsVoiceId, setElevenLabsVoiceId] = useState('cgSgspJ2msm6clMCkdW9');
    const [promptSuggest, setPromptSuggest] = useState('');
    const [promptEstimate, setPromptEstimate] = useState('');
    const [showKey, setShowKey] = useState(false);
    const [showElKey, setShowElKey] = useState(false);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [testingVoice, setTestingVoice] = useState(false);
    const [testingKey, setTestingKey] = useState(false);
    const [keyTestResult, setKeyTestResult] = useState<{ ok: boolean; msg: string } | null>(null);

    useEffect(() => {
        if (isOpen) {
            fetchSettings();
        }
    }, [isOpen]);

    const fetchSettings = async () => {
        setLoading(true);
        setKeyTestResult(null);
        try {
            const res = await api.get('/settings.php');
            if (res.data.status === 'success') {
                const data = res.data.data || {};
                setApiKey(data.deepseek_api_key || '');
                setElevenLabsKey(data.elevenlabs_api_key || '');
                setElevenLabsVoiceId(data.elevenlabs_voice_id || 'cgSgspJ2msm6clMCkdW9');
                setPromptSuggest(data.prompt_suggest || '');
                setPromptEstimate(data.prompt_estimate || '');
            }
        } catch (error) {
            console.error('Error fetching settings:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleSave = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = {
                deepseek_api_key: apiKey.trim(),
                elevenlabs_api_key: elevenLabsKey.trim(),
                elevenlabs_voice_id: elevenLabsVoiceId,
                prompt_suggest: promptSuggest,
                prompt_estimate: promptEstimate
            };

            const res = await api.post('/settings.php', payload);
            if (res.data.status === 'success') {
                onClose();
            } else {
                alert('Error al guardar configuración');
            }
        } catch (error) {
            console.error(error);
            alert('Error al guardar configuración');
        } finally {
            setSaving(false);
        }
    };

    const handleTestDeepSeek = async () => {
        const keyToTest = apiKey.trim();
        if (!keyToTest || keyToTest === '***configured***') {
            setKeyTestResult({ ok: false, msg: 'Pega tu API Key antes de probarla.' });
            return;
        }
        setTestingKey(true);
        setKeyTestResult(null);
        try {
            const res = await api.post('/ai_assistant.php', {
                action: 'test_key',
                api_key: keyToTest,
            });
            const ok = res.data.status === 'success';
            setKeyTestResult({ ok, msg: res.data.message });
        } catch {
            setKeyTestResult({ ok: false, msg: '❌ Error de conexión al probar la key.' });
        } finally {
            setTestingKey(false);
        }
    };

    const handleTestVoice = async () => {
        if (!elevenLabsKey) {
            alert('Primero guarda la API Key de ElevenLabs.');
            return;
        }
        setTestingVoice(true);
        try {
            // Save first so the backend has the key
            await api.post('/settings.php', {
                elevenlabs_api_key: elevenLabsKey.trim(),
                elevenlabs_voice_id: elevenLabsVoiceId,
            });
            const res = await api.post('/tts.php', {
                text: '¡Hola! Soy tu asistente de voz. Estoy lista para ayudarte con tus tareas.'
            }, { responseType: 'blob' });

            // If we got back JSON instead of audio, it's an error
            if (res.data.type === 'application/json') {
                const text = await res.data.text();
                const json = JSON.parse(text);
                alert('❌ Error de ElevenLabs: ' + (json.message || text));
                return;
            }

            const audioBlob = new Blob([res.data], { type: 'audio/mpeg' });
            const audioUrl = URL.createObjectURL(audioBlob);
            const audio = new Audio(audioUrl);
            audio.onended = () => URL.revokeObjectURL(audioUrl);
            try {
                await audio.play();
            } catch (playErr: any) {
                alert('❌ El navegador bloqueó el audio. Intenta hacer clic en la página primero.\n' + playErr.message);
            }
        } catch (err: any) {
            // Extract the real error from the blob response
            let errorMsg = err.message || 'Error desconocido';
            if (err.response?.data instanceof Blob) {
                try {
                    const text = await err.response.data.text();
                    const json = JSON.parse(text);
                    errorMsg = json.message || text;
                } catch {
                    errorMsg = 'No se pudo leer la respuesta del servidor';
                }
            } else if (err.response?.data?.message) {
                errorMsg = err.response.data.message;
            }
            alert('❌ Error al probar la voz:\n' + errorMsg);
            console.error('ElevenLabs error:', err);
        } finally {
            setTestingVoice(false);
        }
    };

    if (!isOpen) return null;

    return (
        <div
            className="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            onClick={onClose}
        >
            <div
                className="bg-white dark:bg-tudu-content-dark w-full max-w-lg rounded-xl shadow-2xl flex flex-col max-h-[90vh]"
                onClick={(e) => e.stopPropagation()}
            >

                {/* Header */}
                <div className="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 className="text-xl font-bold text-tudu-text-light dark:text-tudu-text-dark flex items-center gap-2">
                        <AppWindow size={24} className="text-purple-600" />
                        Configuración IA
                    </h2>
                    <button onClick={onClose} className="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <X size={24} />
                    </button>
                </div>

                {/* Body */}
                <form onSubmit={handleSave} className="p-6 overflow-y-auto flex-1 custom-scrollbar space-y-6">

                    {/* DeepSeek API Key */}
                    <div className="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg border border-purple-100 dark:border-purple-800">
                        <label className="block text-sm font-bold text-purple-900 dark:text-purple-300 mb-2 flex items-center gap-2">
                            <Key size={16} /> DeepSeek API Key
                        </label>
                        <div className="relative">
                            <input
                                type="text"
                                value={apiKey}
                                onChange={e => { setApiKey(e.target.value); setKeyTestResult(null); }}
                                className="w-full p-2 pr-10 rounded-lg border border-purple-200 dark:border-purple-700 bg-white dark:bg-tudu-bg-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 outline-none text-sm font-mono"
                                placeholder="sk-..."
                            />
                            <button
                                type="button"
                                onClick={() => setShowKey(!showKey)}
                                className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-purple-600"
                            >
                                {showKey ? <EyeOff size={18} /> : <Eye size={18} />}
                            </button>
                        </div>
                        <div className="flex items-center gap-3 mt-2">
                            <button
                                type="button"
                                onClick={handleTestDeepSeek}
                                disabled={testingKey || !apiKey}
                                className="flex items-center gap-1.5 text-xs bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white px-3 py-1.5 rounded-lg font-medium transition-all active:scale-95"
                            >
                                {testingKey ? <Loader size={13} className="animate-spin" /> : <Key size={13} />}
                                {testingKey ? 'Probando...' : 'Probar Key'}
                            </button>
                            {keyTestResult && (
                                <span className={`flex items-center gap-1 text-xs font-medium ${keyTestResult.ok ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                                    }`}>
                                    {keyTestResult.ok
                                        ? <CheckCircle size={13} />
                                        : <XCircle size={13} />}
                                    {keyTestResult.msg}
                                </span>
                            )}
                        </div>
                    </div>

                    {/* ElevenLabs Section */}
                    <div className="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg border border-orange-100 dark:border-orange-800 space-y-4">
                        <div>
                            <label className="block text-sm font-bold text-orange-900 dark:text-orange-300 mb-1 flex items-center gap-2">
                                <Volume2 size={16} /> ElevenLabs API Key <span className="text-[10px] bg-orange-200 dark:bg-orange-700 text-orange-800 dark:text-orange-200 px-1.5 py-0.5 rounded-full font-semibold ml-1">VOZ IA</span>
                            </label>
                            <div className="relative">
                                <input
                                    type="text"
                                    value={elevenLabsKey}
                                    onChange={e => setElevenLabsKey(e.target.value)}
                                    className="w-full p-2 pr-10 rounded-lg border border-orange-200 dark:border-orange-700 bg-white dark:bg-tudu-bg-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-orange-400 outline-none text-sm font-mono"
                                    placeholder="Pega aquí tu API Key de ElevenLabs..."
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowElKey(!showElKey)}
                                    className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-orange-600"
                                >
                                    {showElKey ? <EyeOff size={18} /> : <Eye size={18} />}
                                </button>
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-bold text-orange-900 dark:text-orange-300 mb-1 flex items-center gap-2">
                                <Mic size={14} /> Voz de la IA
                            </label>
                            <select
                                value={elevenLabsVoiceId}
                                onChange={e => setElevenLabsVoiceId(e.target.value)}
                                className="w-full p-2 rounded-lg border border-orange-200 dark:border-orange-700 bg-white dark:bg-tudu-bg-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-orange-400 outline-none text-sm"
                            >
                                {ELEVENLABS_VOICES.map(v => (
                                    <option key={v.id} value={v.id}>{v.label}</option>
                                ))}
                            </select>
                        </div>

                        <button
                            type="button"
                            onClick={handleTestVoice}
                            disabled={testingVoice || !elevenLabsKey}
                            className="flex items-center gap-2 text-sm bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white px-4 py-2 rounded-lg font-medium transition-all active:scale-95"
                        >
                            <Volume2 size={16} />
                            {testingVoice ? 'Cargando audio...' : 'Probar voz'}
                        </button>

                        <p className="text-xs text-orange-700 dark:text-orange-400">
                            Usa el modelo <strong>eleven_multilingual_v2</strong> — óptimo para español mexicano. Obtén tu key en{' '}
                            <a href="https://elevenlabs.io" target="_blank" rel="noreferrer" className="underline">elevenlabs.io</a>
                        </p>
                    </div>

                    {/* Prompts Section */}
                    <div className="space-y-4">
                        <h3 className="font-bold text-gray-800 dark:text-white flex items-center gap-2 border-b pb-2 dark:border-gray-700">
                            <MessageSquare size={18} /> Personalizar Prompts
                        </h3>

                        <div>
                            <label className="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Prompt para "Sugerir"</label>
                            <textarea
                                value={promptSuggest}
                                onChange={e => setPromptSuggest(e.target.value)}
                                className="w-full p-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-tudu-bg-dark text-sm dark:text-white h-24 focus:ring-2 focus:ring-tudu-accent outline-none"
                                placeholder="Instrucciones para la IA al sugerir contenido..."
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Prompt para "Estimar"</label>
                            <textarea
                                value={promptEstimate}
                                onChange={e => setPromptEstimate(e.target.value)}
                                className="w-full p-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-tudu-bg-dark text-sm dark:text-white h-24 focus:ring-2 focus:ring-tudu-accent outline-none"
                                placeholder="Instrucciones para la IA al estimar esfuerzo..."
                            />
                        </div>
                    </div>

                </form>

                {/* Footer */}
                <div className="flex justify-end gap-3 p-4 border-t border-gray-200 dark:border-gray-700">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        onClick={handleSave}
                        disabled={saving || loading}
                        className="flex items-center gap-2 bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg font-medium shadow-md transition-all transform active:scale-95 disabled:opacity-50"
                    >
                        {saving ? 'Guardando...' : <><Save size={18} /> Guardar Configuración</>}
                    </button>
                </div>
            </div>
        </div>
    );
};

export default AISettingsModal;
