import { useState, useEffect, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { useNavigate } from 'react-router-dom';
import {
    ChevronRight, ChevronLeft, X, Star, LayoutDashboard, Filter, Plus,
    Lock, Folder, Sparkles, Share2, MessageCircle,
    Users, Calendar, Kanban, Paperclip, Tag, Bell
} from 'lucide-react';
import api from '../../api/axios';
import { useTaskModal } from '../../contexts/TaskModalContext';

// ─── Step Definition ───────────────────────────────────────────────────────
type StepAction =
    | { type: 'open-modal' }
    | { type: 'close-modal' }
    | { type: 'navigate'; path: string };

interface Step {
    title: string;
    description: string;
    target?: string;      // CSS selector to highlight
    icon: React.ReactNode;
    badge: string;
    action?: StepAction;  // fires BEFORE showing this step
    targetDelay?: number; // ms to wait before querying target (for modal open)
}

const steps: Step[] = [
    {
        title: "¡Bienvenido a TuDu!",
        description: "Tu centro de comando para proyectos, tareas y colaboración. Permítenos guiarte por todas las funciones.",
        icon: <Star className="text-yellow-400" size={26} />, badge: "Inicio"
    },
    {
        title: "Tu Dashboard",
        description: "Resumen en tiempo real de KPIs: tareas totales, pendientes, en progreso y completadas. También gráficas y tareas que vencen esta semana.",
        target: "#tour-dashboard-title",
        icon: <LayoutDashboard className="text-blue-500" size={26} />, badge: "Dashboard"
    },
    {
        title: "Proyectos Públicos y Privados",
        description: "🌐 = proyectos de todo el equipo. 🔒 = proyectos solo tuyos. Perfectamente separados.",
        target: "#tour-visibility-toggle",
        icon: <Lock className="text-purple-500" size={26} />, badge: "Privacidad"
    },
    {
        title: "Selector de Proyecto",
        description: "Filtra todo por proyecto. El botón 'Nuevo Proyecto' crea uno en segundos y aparece aquí automáticamente.",
        target: "#tour-view-switcher",
        icon: <Folder className="text-amber-500" size={26} />, badge: "Proyectos"
    },
    {
        title: "Crea tu Primera Tarea",
        description: "Haz clic en '+ Nueva Tarea'. Se abrirá el formulario — abre el modal para ver en detalle cada sección.",
        target: "#tour-new-task",
        icon: <Plus className="text-green-500" size={26} />, badge: "Tareas"
    },
    // ── MODAL STEPS (modal abre al entrar aquí) ──────────────────────────
    {
        title: "El Formulario de Tarea",
        description: "Aquí escribes el título y descripción. El formulario tiene todo lo que necesitas para definir la tarea perfectamente.",
        target: "#tour-task-title",
        icon: <Plus className="text-green-500" size={26} />, badge: "Nueva Tarea",
        action: { type: 'open-modal' }, targetDelay: 500
    },
    {
        title: "Asistente de Inteligencia Artificial ✨",
        description: "'Sugerir Contenido' enriquece la descripción automáticamente. 'Estimar Esfuerzo' sugiere prioridad y fecha de entrega según el contexto.",
        target: "#tour-task-ai",
        icon: <Sparkles className="text-violet-500" size={26} />, badge: "IA", targetDelay: 100
    },
    {
        title: "Etiquetas y Archivos Adjuntos",
        description: "Clasifica con etiquetas de colores por departamento o tema. Adjunta PDFs, imágenes y documentos directamente a la tarea.",
        target: "#tour-task-tags",
        icon: <Tag className="text-pink-500" size={26} />, badge: "Organización", targetDelay: 100
    },
    {
        title: "Asignar a tu Equipo",
        description: "Selecciona uno o varios miembros del equipo. La tarea aparecerá en su dashboard con todos los detalles.",
        target: "#tour-task-assignees",
        icon: <Users className="text-cyan-500" size={26} />, badge: "Colaboración", targetDelay: 100
    },
    {
        title: "Comentarios con @menciones",
        description: "Usa @nombre para notificar a un compañero directamente. También puedes adjuntar archivos a cada comentario.",
        target: "#tour-task-comments",
        icon: <MessageCircle className="text-green-500" size={26} />, badge: "Comentarios", targetDelay: 100
    },
    // ── POST-MODAL STEPS ────────────────────────────────────────────────
    {
        title: "Compartir por WhatsApp 💬",
        description: "En la lista de tareas, cada tarjeta tiene un botón de WhatsApp. Selecciona un contacto y enviará el enlace de la tarea con un clic.",
        icon: <MessageCircle className="text-green-400" size={26} />, badge: "WhatsApp",
        action: { type: 'close-modal' }
    },
    {
        title: "Compartir Enlace Público 🔗",
        description: "El ícono de compartir genera un enlace único. Cualquier persona puede ver la tarea sin iniciar sesión — ideal para clientes.",
        icon: <Share2 className="text-blue-400" size={26} />, badge: "Compartir"
    },
    // ── KANBAN ──────────────────────────────────────────────────────────
    {
        title: "Vista Kanban",
        description: "Columnas visuales: Pendiente → En Progreso → Completado. Arrastra y suelta tarjetas para mover tareas entre estados.",
        target: "#tour-view-switcher",
        icon: <Kanban className="text-indigo-500" size={26} />, badge: "Kanban",
        action: { type: 'navigate', path: '/kanban' }, targetDelay: 600
    },
    // ── CALENDAR ────────────────────────────────────────────────────────
    {
        title: "Calendario y Citas 📅",
        description: "Todas las tareas con fecha de vencimiento aparecen aquí. Haz clic en cualquier día para crear una nueva tarea programada para esa fecha.",
        target: "#tour-view-switcher",
        icon: <Calendar className="text-red-500" size={26} />, badge: "Calendario",
        action: { type: 'navigate', path: '/calendar' }, targetDelay: 600
    },
    // ── FINAL ────────────────────────────────────────────────────────────
    {
        title: "Notificaciones 🔔",
        description: "La campana muestra alertas de tareas próximas a vencer, menciones en comentarios y actividad de tu equipo en tiempo real.",
        target: "#tour-notifications",
        icon: <Bell className="text-amber-500" size={26} />, badge: "Alertas",
        action: { type: 'navigate', path: '/' }, targetDelay: 600
    },
    {
        title: "¡Estás listo para conquistar el día! 🎉",
        description: "Ya conoces todo TuDu. Puedes reiniciar este recorrido desde el menú de tu perfil cuando quieras repasar.",
        icon: <Star className="text-yellow-400" size={26} fill="currentColor" />, badge: "¡Completado!"
    }
];

// ─── Component ─────────────────────────────────────────────────────────────
const OnboardingTour = () => {
    const [currentStep, setCurrentStep] = useState(-1);
    const [isVisible, setIsVisible] = useState(false);
    const [highlightStyle, setHighlightStyle] = useState<React.CSSProperties>({});
    const navigate = useNavigate();
    const { openNewTaskModal, closeModal } = useTaskModal();
    const actionFiredRef = useRef<number>(-2); // tracks which step's action has fired

    useEffect(() => { checkStatus(); }, []);

    const checkStatus = async () => {
        try {
            const res = await api.get('/onboarding.php?action=status');
            if (res.data.status === 'success' && res.data.tour_visto === 0)
                setTimeout(() => setIsVisible(true), 1500);
        } catch (e) { console.error('Tour status error:', e); }
    };

    // ── Fire step action then position highlight ──────────────────────────
    useEffect(() => {
        if (!isVisible || currentStep < 0) return;
        const step = steps[currentStep];

        // Fire action only once per step
        if (actionFiredRef.current !== currentStep && step.action) {
            actionFiredRef.current = currentStep;
            if (step.action.type === 'open-modal') openNewTaskModal({});
            else if (step.action.type === 'close-modal') closeModal();
            else if (step.action.type === 'navigate') navigate(step.action.path);
        }

        // Position highlight after DOM settles
        const delay = step.targetDelay ?? 100;
        const timer = setTimeout(() => positionHighlight(step), delay);
        return () => clearTimeout(timer);
    }, [currentStep, isVisible]);

    const positionHighlight = (step: Step) => {
        if (step.target) {
            const el = document.querySelector(step.target);
            if (el) {
                const r = el.getBoundingClientRect();
                setHighlightStyle({
                    position: 'fixed',
                    top: r.top - 10,
                    left: r.left - 10,
                    width: r.width + 20,
                    height: r.height + 20,
                    zIndex: 100,
                    pointerEvents: 'none',
                    boxShadow: '0 0 0 9999px rgba(0,0,0,0.65)',
                    borderRadius: '14px',
                    border: '2px solid rgba(99,102,241,0.9)',
                    transition: 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)'
                });
                el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                return;
            }
        }
        // No target → full dim overlay
        setHighlightStyle({
            position: 'fixed', inset: 0,
            background: 'rgba(0,0,0,0.65)', zIndex: 100
        });
    };

    const handleNext = () => {
        if (currentStep < steps.length - 1) setCurrentStep(s => s + 1);
        else completeTour();
    };

    const handlePrev = () => {
        if (currentStep > 0) setCurrentStep(s => s - 1);
    };

    const completeTour = async () => {
        closeModal();
        setIsVisible(false);
        try { await api.post('/onboarding.php?action=complete'); }
        catch (e) { console.error(e); }
    };

    const handleClose = () => { closeModal(); completeTour(); };

    if (!isVisible) return null;

    const step = currentStep >= 0 ? steps[currentStep] : null;
    const progress = currentStep >= 0 ? ((currentStep + 1) / steps.length) * 100 : 0;

    return (
        <div className="fixed inset-0 z-[200] pointer-events-none">
            {/* Overlay / cut-out */}
            <motion.div
                initial={{ opacity: 0 }} animate={{ opacity: 1 }}
                style={highlightStyle}
            />

            {/* Card container */}
            <div className="fixed inset-0 flex items-end sm:items-center justify-center p-4 sm:p-6 pointer-events-none" style={{ zIndex: 201 }}>
                <AnimatePresence mode="wait">

                    {/* ── Welcome screen ── */}
                    {currentStep === -1 && (
                        <motion.div
                            key="start"
                            initial={{ scale: 0.9, opacity: 0, y: 20 }}
                            animate={{ scale: 1, opacity: 1, y: 0 }}
                            exit={{ scale: 0.9, opacity: 0 }}
                            className="pointer-events-auto w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden bg-white/85 dark:bg-tudu-content-dark/85 backdrop-blur-xl border border-white/20 dark:border-white/10"
                        >
                            <div className="bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 p-8 text-center relative overflow-hidden">
                                <div className="absolute inset-0 opacity-20" style={{
                                    backgroundImage: 'radial-gradient(circle at 20% 80%, white, transparent 50%), radial-gradient(circle at 80% 20%, white, transparent 50%)'
                                }} />
                                <div className="relative">
                                    <div className="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4 animate-bounce">
                                        <Star size={40} className="text-yellow-300" fill="currentColor" />
                                    </div>
                                    <h3 className="text-2xl font-black text-white">¡Hola! 👋</h3>
                                    <p className="text-white/80 text-sm mt-1">Es un gusto verte en TuDu</p>
                                </div>
                            </div>
                            <div className="p-6 space-y-4">
                                <p className="text-gray-600 dark:text-gray-300 text-sm text-center leading-relaxed">
                                    Recorrido interactivo de <strong className="text-gray-800 dark:text-white">{steps.length} pasos</strong>: te abriremos el modal, el Kanban, el Calendario y más.
                                </p>
                                <div className="flex flex-col gap-3">
                                    <button
                                        onClick={() => setCurrentStep(0)}
                                        className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white py-3 rounded-2xl font-bold transition-all shadow-lg shadow-indigo-500/30 active:scale-95 flex items-center justify-center gap-2"
                                    >
                                        ¡Comencemos! <ChevronRight size={18} />
                                    </button>
                                    <button onClick={handleClose} className="text-gray-400 hover:text-gray-600 text-xs font-medium py-1 transition-colors">
                                        Ya conozco TuDu, saltar tour
                                    </button>
                                </div>
                            </div>
                        </motion.div>
                    )}

                    {/* ── Step card ── */}
                    {currentStep >= 0 && step && (
                        <motion.div
                            key={currentStep}
                            initial={{ y: 14, opacity: 0 }}
                            animate={{ y: 0, opacity: 1 }}
                            exit={{ y: -14, opacity: 0 }}
                            transition={{ duration: 0.22 }}
                            className="pointer-events-auto w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden bg-white/85 dark:bg-tudu-content-dark/85 backdrop-blur-xl border border-white/20 dark:border-white/10"
                        >
                            <div className="h-1 bg-gray-100 dark:bg-gray-800">
                                <motion.div
                                    className="h-full bg-gradient-to-r from-indigo-500 to-purple-500"
                                    animate={{ width: `${progress}%` }}
                                    transition={{ duration: 0.35 }}
                                />
                            </div>

                            {/* Top bar */}
                            <div className="flex items-center justify-between px-5 pt-4 pb-2">
                                <span className="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full">
                                    {step.badge}
                                </span>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-gray-400">{currentStep + 1} / {steps.length}</span>
                                    <button onClick={handleClose}
                                        className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                        <X size={17} />
                                    </button>
                                </div>
                            </div>

                            {/* Content */}
                            <div className="px-5 pb-2 flex gap-3">
                                <div className="w-11 h-11 rounded-2xl bg-white/50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 flex-shrink-0 flex items-center justify-center shadow-inner">
                                    {step.icon}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <h4 className="text-base font-bold text-gray-900 dark:text-white leading-tight mb-1">{step.title}</h4>
                                    <p className="text-gray-600 dark:text-gray-300 text-sm leading-relaxed">{step.description}</p>
                                </div>
                            </div>

                            {/* Dots */}
                            <div className="flex justify-center gap-1 py-3">
                                {steps.map((_, i) => (
                                    <button key={i} onClick={() => setCurrentStep(i)}
                                        className={`rounded-full transition-all ${i === currentStep
                                            ? 'w-4 h-1.5 bg-indigo-500'
                                            : 'w-1.5 h-1.5 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400'}`}
                                    />
                                ))}
                            </div>

                            {/* Navigation */}
                            <div className="flex gap-2 px-5 pb-5">
                                <button onClick={handlePrev} disabled={currentStep === 0}
                                    className="flex items-center gap-1 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100/50 dark:hover:bg-gray-800/50 transition-all disabled:opacity-30 disabled:cursor-not-allowed">
                                    <ChevronLeft size={15} /> Anterior
                                </button>
                                <button onClick={handleNext}
                                    className="flex-1 flex items-center justify-center gap-1.5 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white py-2.5 rounded-xl font-bold text-sm transition-all shadow-md shadow-indigo-500/20 active:scale-95">
                                    {currentStep === steps.length - 1 ? '🎉 ¡Completar!' : 'Siguiente'}
                                    {currentStep < steps.length - 1 && <ChevronRight size={15} />}
                                </button>
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>
            </div>
        </div>
    );
};

export default OnboardingTour;
