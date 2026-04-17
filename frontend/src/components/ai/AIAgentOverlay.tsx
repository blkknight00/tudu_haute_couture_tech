import { useState, useEffect, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Bot, Mic, Send, X, Loader2, Sparkles } from 'lucide-react';
import api from '../../api/axios';
import { useNavigate } from 'react-router-dom';
import { executeAgentAction } from '../../services/agentTools';
import { useProjectFilter } from '../../contexts/ProjectFilterContext';

interface Message {
    role: 'user' | 'assistant';
    content: string;
}

const AIAgentOverlay = () => {
    const [isOpen, setIsOpen] = useState(false);
    const [isListening, setIsListening] = useState(false);
    const [isThinking, setIsThinking] = useState(false);
    const [input, setInput] = useState('');
    const [messages, setMessages] = useState<Message[]>([]);
    const [transcript, setTranscript] = useState('');
    const chatEndRef = useRef<HTMLDivElement>(null);
    const recognitionRef = useRef<any>(null);
    const audioRef = useRef<HTMLAudioElement | null>(null);
    const navigate = useNavigate();
    const { setSelectedProjectId } = useProjectFilter();

    // Listen for the 'open-ai-chat' event dispatched by BottomNavigation on mobile
    useEffect(() => {
        const handleOpenChat = () => setIsOpen(true);
        window.addEventListener('open-ai-chat', handleOpenChat);
        return () => window.removeEventListener('open-ai-chat', handleOpenChat);
    }, []);

    // Initialize Web Speech API
    useEffect(() => {
        if (recognitionRef.current) return;

        const SpeechRecognition = (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition;
        if (SpeechRecognition) {
            try {
                const recognition = new SpeechRecognition();
                recognition.continuous = true;
                recognition.interimResults = true;
                recognition.lang = 'es-ES';

                recognition.onresult = (event: any) => {
                    let current = '';
                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        current += event.results[i][0].transcript;
                    }
                    setTranscript(current);
                };

                recognition.onerror = (event: any) => {
                    if (event.error === 'no-speech') return; // Ignore no-speech

                    console.error("Speech Recognition Error:", event.error);
                    setShouldListen(false);
                    setIsListening(false);

                    if (event.error === 'not-allowed') {
                        const isIP = /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}/.test(window.location.hostname);
                        if (isIP && window.location.protocol !== 'https:') {
                            alert("Seguridad del Navegador: Para usar el micrófono en una IP de red local (192.168.x.x), debes usar HTTPS o habilitar la opción 'Insecure origins treated as secure' en chrome://flags.");
                        } else {
                            alert("Permiso denegado: El micrófono está bloqueado por el sistema, por otra aplicación o no has concedido permiso en el navegador.");
                        }
                    } else if (event.error === 'aborted') {
                        console.warn("Recognition aborted - likely transient.");
                        // No alert for aborted as it can happen naturally on mobile transitions
                    } else if (event.error === 'network') {
                        alert("Error de red: El reconocimiento de voz requiere conexión a internet.");
                    } else {
                        alert("Error en micrófono: " + event.error);
                    }
                };

                recognitionRef.current = recognition;
            } catch (err) {
                console.error("Speech Recognition Init Error", err);
            }
        }
    }, []);

    // State to track if we should be listening.
    const [shouldListen, setShouldListen] = useState(false);

    // Effect to handle the actual start/stop based on shouldListen
    useEffect(() => {
        if (!recognitionRef.current) return;

        const recognition = recognitionRef.current;

        const handleEnd = () => {
            if (shouldListen) {
                try {
                    recognition.start();
                } catch (e) {
                    console.error("Failed to restart recognition", e);
                    setShouldListen(false);
                    setIsListening(false);
                }
            } else {
                setIsListening(false);
            }
        };

        recognition.onend = handleEnd;

        if (shouldListen) {
            try {
                recognition.start();
                setIsListening(true);
            } catch (e) {
                // If already started, that's fine
                setIsListening(true);
            }
        } else {
            try {
                recognition.stop();
            } catch (e) {
                // Ignore stop errors
            }
            setIsListening(false);
        }

        return () => {
            recognition.onend = null;
        };
    }, [shouldListen]);

    useEffect(() => {
        if (chatEndRef.current) {
            chatEndRef.current.scrollIntoView({ behavior: 'smooth' });
        }
    }, [messages, transcript, isThinking]);

    const handleSend = async (textOverride?: string) => {
        const text = textOverride || input;
        if (!text.trim()) return;

        const userMsg: Message = { role: 'user', content: text };
        setMessages(prev => [...prev, userMsg]);
        setInput('');
        setTranscript('');
        setIsThinking(true);

        try {
            // Fetch current context for the chat logic
            const contextRes = await api.get('/get_context.php');
            const context = contextRes.data.status === 'success' ? contextRes.data.data : null;

            const response = await api.post('/ai_assistant.php', {
                action: 'chat',
                message: text,
                history: messages,
                context: context,
                local_datetime: new Date().toLocaleString('es-MX', { timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone, hour12: false, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' }),
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            });

            // Check for backend-level errors (status: 'error' with HTTP 200)
            if (response.data.status === 'error') {
                const backendErrorMsg = response.data.message || 'Error desconocido del servidor.';
                console.error('AI backend error:', backendErrorMsg);
                const errorMsg: Message = {
                    role: 'assistant',
                    content: backendErrorMsg.includes('API Key')
                        ? '⚠️ El asistente no está configurado aún. Pide al administrador que configure la API Key de DeepSeek en Configurar AI.'
                        : `⚠️ Error del servidor: ${backendErrorMsg}`
                };
                setMessages(prev => [...prev, errorMsg]);
                return;
            }

            const content = response.data.message || 'No obtuve respuesta.';

            // Look for [[ACTION: ...]]
            const actionMatch = content.match(/\[\[ACTION: (.*?)\]\]/);
            let feedback = '';
            if (actionMatch) {
                try {
                    const action = JSON.parse(actionMatch[1]);
                    feedback = await executeAgentAction(action, navigate, setSelectedProjectId);
                } catch (e) {
                    console.error("Action parse error", e);
                }
            }

            const aiMsg: Message = { role: 'assistant', content: content.replace(/\[\[ACTION: .*?\]\]/g, '').trim() + (feedback ? `\n\n*${feedback}*` : '') };
            setMessages(prev => [...prev, aiMsg]);

            // TTS
            if (feedback || aiMsg.content) {
                // Remove Markdown symbols for cleaner speech
                const cleanContent = aiMsg.content.replace(/[*#_~`]/g, '');
                speak(cleanContent);
            }

        } catch (error: any) {
            console.error("AI Error", error);
            // Show specific HTTP error if available
            const httpStatus = error?.response?.status;
            const backendMessage = error?.response?.data?.message;

            let errorContent = backendMessage || 'Lo siento, hubo un error al procesar tu solicitud. Por favor, verifica tu conexión o inténtalo más tarde.';

            if (httpStatus === 401) {
                errorContent = '⚠️ Sesión expirada. Por favor recarga la página e inicia sesión de nuevo.';
            } else if (httpStatus === 500 && !backendMessage) {
                errorContent = '⚠️ Error 500 del servidor. Revisa api/api_trace.log o los logs del hosting.';
            } else if (!navigator.onLine) {
                errorContent = '⚠️ Sin conexión a internet. Verifica tu red.';
            }

            const errorMsg: Message = {
                role: 'assistant',
                content: errorContent
            };
            setMessages(prev => [...prev, errorMsg]);
        } finally {
            setIsThinking(false);
        }
    };

    const toggleListening = () => {
        if (!recognitionRef.current) {
            const isiOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
            if (isiOS) {
                alert("En iOS, el reconocimiento de voz solo funciona en Safari.");
            } else {
                alert("Tu navegador no soporta el reconocimiento de voz o está deshabilitado.");
            }
            return;
        }

        if (shouldListen) {
            setShouldListen(false);
            // Only send if we have actual transcript content
            if (transcript && transcript.trim()) {
                handleSend(transcript.trim());
            }
        } else {
            setTranscript('');
            setShouldListen(true);
        }
    };

    // Proactive Briefing logic
    const fetchBriefing = async () => {
        try {
            // 1. Get Context
            const contextRes = await api.get('/get_context.php');
            if (contextRes.data.status === 'success') {
                const context = contextRes.data.data;

                setIsThinking(true);
                // 2. Get AI Briefing
                const aiRes = await api.post('/ai_assistant.php', {
                    action: 'briefing',
                    context: context
                });

                if (aiRes.data.status === 'success' && aiRes.data.message) {
                    const briefMsg: Message = {
                        role: 'assistant',
                        content: aiRes.data.message
                    };
                    setMessages([briefMsg]);
                    speak(briefMsg.content.replace(/[*#_~`]/g, ''));
                } else {
                    // Fallback to initial welcome if briefing is empty or failed
                    const welcomeMsg: Message = {
                        role: 'assistant',
                        content: '¡Hola! Soy tu asistente. ¿En qué puedo ayudarte hoy?'
                    };
                    setMessages([welcomeMsg]);
                }
            }
        } catch (err: any) {
            console.error("Briefing error", err);
            const backendMessage = err?.response?.data?.message;
            // Default greeting if briefing fails
            const welcomeMsg: Message = {
                role: 'assistant',
                content: backendMessage ? `⚠️ Error de Briefing: ${backendMessage}` : '¡Hola! No pude generar el resumen del día, pero estoy listo para ayudarte con tus tareas.'
            };
            setMessages([welcomeMsg]);
        } finally {
            setIsThinking(false);
        }
    };

    // Trigger briefing on first open - Limit to once per day
    const [hasBriefed, setHasBriefed] = useState(false);
    useEffect(() => {
        if (isOpen && !hasBriefed) {
            const today = new Date().toISOString().split('T')[0];
            const lastBriefDate = localStorage.getItem('lastAiBriefingDate');

            if (lastBriefDate === today) {
                // Already briefed today, show standard welcome
                const welcomeMsg: Message = {
                    role: 'assistant',
                    content: '¡Hola de nuevo! ¿En qué puedo ayudarte con tus tareas ahora?'
                };
                setMessages([welcomeMsg]);
            } else {
                fetchBriefing().then(() => {
                    localStorage.setItem('lastAiBriefingDate', today);
                });
            }
            setHasBriefed(true);
        }
    }, [isOpen, hasBriefed]);

    const isOpenRef = useRef(isOpen);
    useEffect(() => {
        isOpenRef.current = isOpen;
    }, [isOpen]);

    // Handle open from bottom nav
    useEffect(() => {
        const handleOpen = () => setIsOpen(true);
        window.addEventListener('open-ai-chat', handleOpen);
        return () => window.removeEventListener('open-ai-chat', handleOpen);
    }, []);

    // Silenciar cuando se cierra
    useEffect(() => {
        if (!isOpen) {
            // Stop any playing ElevenLabs audio
            if (audioRef.current) {
                audioRef.current.pause();
                audioRef.current = null;
            }
            // Also stop browser TTS fallback
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
            setShouldListen(false);
        }
    }, [isOpen]);

    const speak = async (text: string) => {
        if (!isOpenRef.current) return;

        // Stop any currently playing audio
        if (audioRef.current) {
            audioRef.current.pause();
            audioRef.current = null;
        }

        try {
            const response = await api.post('/tts.php', { text }, { responseType: 'blob' });

            // Check if we got audio back (not a JSON error)
            if (response.data.type === 'application/json') {
                throw new Error('ElevenLabs not configured');
            }

            const audioBlob = new Blob([response.data], { type: 'audio/mpeg' });
            const audioUrl = URL.createObjectURL(audioBlob);
            const audio = new Audio(audioUrl);
            audioRef.current = audio;
            audio.onended = () => {
                URL.revokeObjectURL(audioUrl);
                audioRef.current = null;
            };
            await audio.play();
        } catch {
            // Fallback to browser TTS if ElevenLabs not configured or error
            if ('speechSynthesis' in window && isOpenRef.current) {
                window.speechSynthesis.cancel();
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = 'es-MX';
                utterance.rate = 1;
                window.speechSynthesis.speak(utterance);
            }
        }
    };

    return (
        <div className="fixed bottom-6 right-6 z-[9999]">
            <AnimatePresence>
                {isOpen && (
                    <motion.div
                        initial={{ opacity: 0, scale: 0.8, y: 20 }}
                        animate={{ opacity: 1, scale: 1, y: 0 }}
                        exit={{ opacity: 0, scale: 0.8, y: 20 }}
                        className="bg-white dark:bg-tudu-bg-dark rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-800 w-[350px] sm:w-[400px] mb-4 overflow-hidden flex flex-col"
                        style={{ maxHeight: '500px' }}
                    >
                        {/* Header */}
                        <div className="bg-tudu-accent p-4 flex items-center justify-between text-white">
                            <div className="flex items-center gap-2">
                                <Sparkles size={20} />
                                <span className="font-bold">TuDu Agent AI</span>
                            </div>
                            <button onClick={() => setIsOpen(false)} className="hover:bg-white/20 p-1 rounded">
                                <X size={20} />
                            </button>
                        </div>

                        {/* Messages Area */}
                        <div className="flex-1 overflow-y-auto p-4 space-y-4 min-h-[300px] custom-scrollbar bg-gray-50/50 dark:bg-tudu-bg-dark/50">
                            {messages.length === 0 && (
                                <div className="text-center text-tudu-text-muted py-8 px-4">
                                    <Bot size={48} className="mx-auto mb-4 opacity-20" />
                                    <p>Hola! Soy tu asistente. Puedo crear tareas, filtrar proyectos o ayudarte a organizar tu día.</p>
                                    <p className="text-xs mt-2 italic">Prueba diciendo: "Crea una tarea para comprar café mañana"</p>
                                </div>
                            )}

                            {messages.map((msg, idx) => (
                                <div key={idx} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                                    <div className={`max-w-[85%] p-3 rounded-2xl text-sm ${msg.role === 'user'
                                        ? 'bg-tudu-accent text-white rounded-tr-none'
                                        : 'bg-white/85 dark:bg-tudu-column-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10 dark:text-gray-200 shadow-sm rounded-tl-none border border-gray-100 dark:border-gray-700'
                                        }`}>
                                        {msg.content}
                                    </div>
                                </div>
                            ))}

                            {isThinking && (
                                <div className="flex justify-start">
                                    <div className="bg-gray-100 dark:bg-tudu-column-dark p-3 rounded-2xl rounded-tl-none animate-pulse flex items-center gap-2 text-tudu-text-muted">
                                        <Loader2 className="animate-spin" size={16} /> Pensando...
                                    </div>
                                </div>
                            )}

                            {transcript && (
                                <div className="flex justify-end">
                                    <div className="bg-tudu-accent/50 text-white p-3 rounded-2xl rounded-tr-none text-sm italic">
                                        {transcript}...
                                    </div>
                                </div>
                            )}
                            <div ref={chatEndRef} />
                        </div>

                        {/* Input Area */}
                        <div className="p-4 bg-white dark:bg-tudu-bg-dark border-t border-gray-100 dark:border-gray-800">
                            <div className="flex items-center gap-2">
                                <button
                                    onClick={toggleListening}
                                    className={`p-2 rounded-full transition-all ${isListening ? 'bg-red-500 text-white animate-pulse' : 'bg-gray-100 dark:bg-tudu-column-dark text-gray-500 hover:text-tudu-accent'}`}
                                >
                                    <Mic size={20} />
                                </button>
                                <input
                                    type="text"
                                    value={input}
                                    onChange={(e) => setInput(e.target.value)}
                                    onKeyPress={(e) => e.key === 'Enter' && handleSend()}
                                    placeholder="Habla o escribe aquí..."
                                    className="flex-1 bg-gray-100 dark:bg-tudu-column-dark rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-tudu-accent/50 dark:text-white"
                                />
                                <button
                                    onClick={() => handleSend()}
                                    disabled={!input.trim() || isThinking}
                                    className="p-2 bg-tudu-accent text-white rounded-full hover:bg-tudu-accent-hover transition-colors disabled:opacity-50"
                                >
                                    <Send size={18} />
                                </button>
                            </div>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>

            <motion.button
                whileHover={{ scale: 1.1 }}
                whileTap={{ scale: 0.9 }}
                onClick={() => setIsOpen(!isOpen)}
                className="hidden sm:flex bg-tudu-accent w-14 h-14 rounded-full items-center justify-center text-white shadow-xl hover:shadow-tudu-accent/30 transition-all border-2 border-white dark:border-gray-800"
            >
                {isOpen ? <X size={28} /> : <Bot size={28} />}
            </motion.button>
        </div>
    );
};

export default AIAgentOverlay;
