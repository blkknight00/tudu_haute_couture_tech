import { useState } from 'react';
import {
    LayoutDashboard,
    FolderPlus,
    CheckSquare,
    Calendar as CalendarIcon,
    Archive,
    Zap,
    Menu,
    ChevronRight,
    X,
    BookOpen,
    Users,
    Shield
} from 'lucide-react';

interface UserGuideModalProps {
    isOpen: boolean;
    onClose: () => void;
}

const UserGuideModal = ({ isOpen, onClose }: UserGuideModalProps) => {
    const [activeSection, setActiveSection] = useState('intro');
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);

    const sections = [
        { id: 'intro', title: 'Inicio', icon: <Zap size={18} /> },
        { id: 'dashboard', title: 'Dashboard', icon: <LayoutDashboard size={18} /> },
        { id: 'projects', title: 'Proyectos', icon: <FolderPlus size={18} /> },
        { id: 'tasks', title: 'Tareas y Kanban', icon: <CheckSquare size={18} /> },
        { id: 'calendar', title: 'Calendario', icon: <CalendarIcon size={18} /> },
        { id: 'resources', title: 'Recursos', icon: <Archive size={18} /> },
        { id: 'team', title: 'Equipo', icon: <Users size={18} /> },
        { id: 'security', title: 'Seguridad', icon: <Shield size={18} /> },
        { id: 'ai', title: 'Asistente IA', icon: <Zap size={18} className="text-purple-500" /> },
    ];

    const scrollToSection = (id: string) => {
        setActiveSection(id);
        const element = document.getElementById(`guide-${id}`);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth' });
        }
        setIsSidebarOpen(false);
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fade-in" onClick={onClose}>
            <div
                className="bg-white dark:bg-tudu-content-dark w-full max-w-5xl h-[85vh] rounded-2xl shadow-2xl overflow-hidden flex flex-col md:flex-row relative"
                onClick={e => e.stopPropagation()}
            >
                {/* Close Button (Mobile Absolute / Desktop Absolute) */}
                <button
                    onClick={onClose}
                    className="absolute top-4 right-4 z-[100] bg-white/80 dark:bg-black/20 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white p-2 rounded-full transition-colors"
                >
                    <X size={24} />
                </button>

                {/* Sidebar Navigation */}
                <aside className={`
                    absolute inset-y-0 left-0 z-40 w-64 bg-gray-50 dark:bg-gray-800/50 border-r border-gray-200 dark:border-gray-700 transform transition-transform duration-300 ease-in-out
                    ${isSidebarOpen ? 'translate-x-0' : '-translate-x-full'}
                    md:relative md:translate-x-0
                `}>
                    <div className="h-full flex flex-col">
                        <div className="p-6 border-b border-gray-100 dark:border-gray-700">
                            <h1 className="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                                <BookOpen size={24} className="text-tudu-accent" />
                                Guía de Uso
                            </h1>
                            <p className="text-xs text-gray-500 mt-2">Documentación oficial</p>
                        </div>

                        <nav className="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-1">
                            {sections.map(section => (
                                <button
                                    key={section.id}
                                    onClick={() => scrollToSection(section.id)}
                                    className={`w-full flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors text-left
                                        ${activeSection === section.id
                                            ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400'
                                            : 'text-gray-600 dark:text-gray-400 hover:bg-white dark:hover:bg-gray-700'
                                        }`}
                                >
                                    {section.icon}
                                    {section.title}
                                    {activeSection === section.id && <ChevronRight size={14} className="ml-auto" />}
                                </button>
                            ))}
                        </nav>
                    </div>
                </aside>

                {/* Mobile Toggle */}
                <div className="md:hidden absolute bottom-6 left-6 z-[100]">
                    <button
                        onClick={() => setIsSidebarOpen(!isSidebarOpen)}
                        className="p-3 bg-tudu-accent text-white rounded-full shadow-lg hover:bg-tudu-accent-hover transition-colors"
                    >
                        <Menu size={24} />
                    </button>
                </div>

                {/* Main Content */}
                <main className="flex-1 overflow-y-auto custom-scrollbar p-6 md:p-10 scroll-smooth pb-20 bg-white dark:bg-tudu-content-dark">
                    <div className="max-w-3xl mx-auto space-y-12">

                        {/* Intro */}
                        <section id="guide-intro" className="space-y-4 pt-2">
                            <div className="bg-gradient-to-r from-blue-600 to-tudu-accent rounded-2xl p-8 text-white shadow-lg">
                                <h2 className="text-3xl font-bold mb-4">Bienvenido a TuDu</h2>
                                <p className="text-blue-100 text-lg">
                                    Tu plataforma integral para la gestión de proyectos con un diseño <em>Haute Couture Cristal</em>, ultrarrápida y enfocada en colaboración.
                                    Esta guía te ayudará a sacar el máximo provecho de todas las funcionalidades.
                                </p>
                            </div>
                        </section>

                        {/* Dashboard */}
                        <section id="guide-dashboard" className="scroll-mt-8 space-y-4">
                            <div className="flex items-center gap-3 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                                <div className="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-tudu-accent">
                                    <LayoutDashboard size={24} />
                                </div>
                                <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Dashboard</h2>
                            </div>
                            <div className="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                                <p>
                                    El Dashboard es tu centro de mando. Aquí encontrarás un resumen visual del estado de tus proyectos.
                                </p>
                                <ul className="list-disc pl-5 space-y-2 mt-4">
                                    <li><strong>Resumen de Tareas:</strong> Contadores de tareas pendientes, en progreso y completadas.</li>
                                    <li><strong>Tareas Urgentes:</strong> Una lista prioritaria de tareas que requieren tu atención inmediata.</li>
                                    <li><strong>Actividad Reciente:</strong> Un historial de las últimas acciones realizadas por tu equipo.</li>
                                    <li><strong>Gráficos:</strong> Visualizaciones para entender el rendimiento y la carga de trabajo.</li>
                                </ul>
                            </div>
                        </section>

                        {/* Projects */}
                        <section id="guide-projects" className="scroll-mt-8 space-y-4">
                            <div className="flex items-center gap-3 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                                <div className="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-tudu-accent">
                                    <FolderPlus size={24} />
                                </div>
                                <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Proyectos</h2>
                            </div>
                            <div className="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                                <p>
                                    Organiza tu trabajo en Proyectos. Cada proyecto puede contener múltiples tareas y recursos.
                                </p>
                                <div className="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 mt-4">
                                    <h3 className="text-lg font-semibold mb-2">Funcionalidades Clave:</h3>
                                    <ul className="list-disc pl-5 space-y-2">
                                        <li><strong>Crear Proyecto:</strong> Define un nombre, descripción y fecha límite.</li>
                                        <li><strong>Privacidad:</strong> Los proyectos pueden ser públicos (visibles para todo el equipo) o privados.</li>
                                        <li><strong>Archivar:</strong> Si un proyecto termina, puedes archivarlo para limpiar tu vista sin perder los datos.</li>
                                    </ul>
                                </div>
                            </div>
                        </section>

                        {/* Tasks */}
                        <section id="guide-tasks" className="scroll-mt-8 space-y-4">
                            <div className="flex items-center gap-3 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                                <div className="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-tudu-accent">
                                    <CheckSquare size={24} />
                                </div>
                                <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Tareas y Kanban</h2>
                            </div>
                            <div className="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                                <p>
                                    Gestiona el flujo de trabajo utilizando el tablero Kanban interactivo.
                                </p>
                                <div className="grid md:grid-cols-2 gap-6 mt-4">
                                    <div className="bg-purple-50 dark:bg-purple-900/10 p-5 rounded-lg border border-purple-100 dark:border-purple-800/30">
                                        <h4 className="font-bold text-purple-700 dark:text-purple-400 mb-2">Kanban Drag & Drop</h4>
                                        <p className="text-sm">Arrastra las tarjetas entre columnas (Pendiente, En Progreso, Completado) para actualizar su estado al instante.</p>
                                    </div>
                                    <div className="bg-blue-50 dark:bg-blue-900/10 p-5 rounded-lg border border-blue-100 dark:border-blue-800/30">
                                        <h4 className="font-bold text-blue-700 dark:text-blue-400 mb-2">Detalles de Tarea</h4>
                                        <p className="text-sm">Haz clic en una tarjeta para ver detalles, subtareas, comentarios, añadir etiquetas o subir archivos adjuntos.</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {/* Calendar */}
                        <section id="guide-calendar" className="scroll-mt-8 space-y-4">
                            <div className="flex items-center gap-3 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                                <div className="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-tudu-accent">
                                    <CalendarIcon size={24} />
                                </div>
                                <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Calendario</h2>
                            </div>
                            <div className="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                                <p>
                                    Visualiza tus plazos y eventos importantes en una vista mensual.
                                </p>
                                <ul className="list-disc pl-5 space-y-2 mt-4">
                                    <li><strong>Eventos:</strong> Crea reuniones, entregas o eventos personales.</li>
                                    <li><strong>Sincronización:</strong> Las tareas con fecha de vencimiento aparecen automáticamente en el calendario.</li>
                                    <li><strong>Solicitudes:</strong> Envía solicitudes de cita a otros usuarios y gestiona las respuestas.</li>
                                </ul>
                            </div>
                        </section>

                        {/* Resources */}
                        <section id="guide-resources" className="scroll-mt-8 space-y-4">
                            <div className="flex items-center gap-3 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                                <div className="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-tudu-accent">
                                    <Archive size={24} />
                                </div>
                                <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Recursos y Archivos</h2>
                            </div>
                            <div className="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                                <p>
                                    Un repositorio centralizado para todos los archivos de tu organización.
                                </p>
                                <p className="mt-2 text-sm text-gray-500">
                                    Sube documentos, imágenes y contratos. Puedes vincular estos recursos directamente a tareas específicas para mantener todo el contexto en un solo lugar.
                                </p>
                            </div>
                        </section>

                        {/* Team */}
                        <section id="guide-team" className="scroll-mt-8 space-y-4">
                            <div className="flex items-center gap-3 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                                <div className="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-tudu-accent">
                                    <Users size={24} />
                                </div>
                                <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Equipo e Invitaciones</h2>
                            </div>
                            <div className="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                                <p>
                                    Invita a colaboradores rápida y fácilmente.
                                </p>
                                <ul className="list-disc pl-5 space-y-2 mt-4">
                                    <li><strong>Invitaciones con 1 Clic:</strong> Desde el menú de tu perfil (esquina superior derecha), abre "Invitar Usuarios". Escribe el correo o teléfono y obtendrás al instante un enlace listo para compartir por WhatsApp o Email sin complejas plantillas.</li>
                                    <li><strong>Administración de Miembros:</strong> En la misma pestaña podrás gestionar quiénes tienen rol de "Administrador" o revocar el acceso a ex-integrantes con facilidad.</li>
                                </ul>
                            </div>
                        </section>

                        {/* Security */}
                        <section id="guide-security" className="scroll-mt-8 space-y-4">
                            <div className="flex items-center gap-3 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                                <div className="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-tudu-accent">
                                    <Shield size={24} />
                                </div>
                                <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Seguridad y Acceso</h2>
                            </div>
                            <div className="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                                <p>
                                    TuDu incorpora los últimos protocolos para tu seguridad con el mínimo rozamiento (WebAuthn).
                                </p>
                                <ul className="list-disc pl-5 space-y-2 mt-4">
                                    <li><strong>Inicio de Sesión Biométrico:</strong> ¡Olvídate de las contraseñas! Entra usando tu huella, Face ID o Windows Hello. Lo puedes configurar directamente al registrarte o desde tu perfil.</li>
                                    <li><strong>Registro Rápido:</strong> Ahora basta con confirmación y listo, todo es más directo y tu conexión permanece fluida.</li>
                                </ul>
                            </div>
                        </section>

                        {/* AI */}
                        <section id="guide-ai" className="scroll-mt-8 space-y-4 mb-20">
                            <div className="flex items-center gap-3 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                                <div className="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-purple-600">
                                    <Zap size={24} />
                                </div>
                                <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Asistente IA</h2>
                            </div>
                            <div className="bg-gradient-to-br from-purple-50 to-white dark:from-gray-800 dark:to-gray-900 p-6 rounded-xl border border-purple-100 dark:border-gray-700 shadow-sm">
                                <p className="text-gray-700 dark:text-gray-300 mb-4">
                                    TuDu integra inteligencia artificial avanzada para potenciar tu productividad.
                                </p>
                                <div className="grid gap-4">
                                    <div className="flex items-start gap-3">
                                        <span className="bg-purple-100 text-purple-600 p-1 rounded">✨</span>
                                        <div>
                                            <strong className="text-gray-900 dark:text-white">Desglose de Tareas:</strong>
                                            <p className="text-sm text-gray-600 dark:text-gray-400">Pide a la IA que sugiera subtareas para cualquier trabajo complejo.</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <span className="bg-purple-100 text-purple-600 p-1 rounded">⏱️</span>
                                        <div>
                                            <strong className="text-gray-900 dark:text-white">Estimación de Tiempo:</strong>
                                            <p className="text-sm text-gray-600 dark:text-gray-400">Obtén predicciones sobre cuánto tiempo tomará una tarea basándose en su descripción.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                    </div>
                </main>
            </div>
        </div>
    );
};

export default UserGuideModal;
