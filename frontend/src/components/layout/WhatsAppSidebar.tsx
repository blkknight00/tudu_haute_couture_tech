import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { useProjectFilter } from '../../contexts/ProjectFilterContext';
import { useTaskModal } from '../../contexts/TaskModalContext';
import { useProjectModal } from '../../contexts/ProjectModalContext';
import api, { BASE_URL } from '../../api/axios';
import { 
    Search, Plus, MoreVertical, MessageSquare, 
    Hash, User as UserIcon, LogOut, Settings, 
    Bell, Lock, Globe 
} from 'lucide-react';

export default function WhatsAppSidebar() {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const { setProjectType, selectedProjectId, setSelectedProjectId } = useProjectFilter();
    const { openNewTaskModal } = useTaskModal();
    const { openNewProjectModal } = useProjectModal();

    const [projects, setProjects] = useState<any[]>([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [menuOpen, setMenuOpen] = useState(false);

    useEffect(() => {
        fetchProjects();
        const handleProjectSaved = () => fetchProjects();
        window.addEventListener('project-saved', handleProjectSaved);
        return () => window.removeEventListener('project-saved', handleProjectSaved);
    }, []);

    const fetchProjects = async () => {
        try {
            const res = await api.get('/get_options.php');
            if (res.data.status === 'success') {
                setProjects(res.data.projects);
            }
        } catch (error) {
            console.error('Error fetching projects:', error);
        }
    };

    const handleLogout = async () => {
        await api.get('/auth.php?action=logout');
        logout();
    };

    // Filter by search query
    const filteredProjects = projects.filter(p => 
        p.nombre.toLowerCase().includes(searchQuery.toLowerCase())
    );

    // Grouping
    const publicProjects = filteredProjects.filter(p => p.user_id === 0);
    const privateProjects = filteredProjects.filter(p => p.user_id !== 0);

    return (
        <aside className="w-[380px] h-full flex flex-col bg-zinc-950/80 backdrop-blur-2xl border-r border-white/5 flex-shrink-0 z-40">
            
            {/* ── HEADER (Tu Perfil) ── */}
            <div className="h-16 px-4 bg-zinc-900/50 border-b border-white/5 flex items-center justify-between shrink-0">
                <div className="flex items-center gap-3 cursor-pointer" onClick={() => navigate('/')}>
                    {user?.foto ? (
                        <img 
                            src={`${BASE_URL}/uploads/profiles/${user.foto}`} 
                            alt="Perfil" 
                            className="w-10 h-10 rounded-full object-cover border border-purple-500/30"
                        />
                    ) : (
                        <div className="w-10 h-10 rounded-full bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center text-white font-bold shadow-md shadow-purple-500/20">
                            {user?.nombre?.charAt(0).toUpperCase()}
                        </div>
                    )}
                    <div className="hidden sm:block">
                        <p className="text-sm font-semibold text-white leading-tight">{user?.nombre}</p>
                        <p className="text-xs text-zinc-500 leading-tight">Activo ahora</p>
                    </div>
                </div>

                <div className="flex items-center gap-3 text-zinc-400">
                    <button 
                        onClick={() => openNewProjectModal()} 
                        title="Nuevo Grupo/Proyecto"
                        className="p-2 rounded-full hover:bg-white/10 hover:text-white transition-colors"
                    >
                        <Plus size={20} />
                    </button>
                    <div className="relative">
                        <button 
                            onClick={() => setMenuOpen(!menuOpen)}
                            className="p-2 rounded-full hover:bg-white/10 hover:text-white transition-colors"
                        >
                            <MoreVertical size={20} />
                        </button>
                        {menuOpen && (
                            <div className="absolute top-12 right-0 w-48 bg-zinc-800 border border-zinc-700 rounded-xl shadow-2xl py-2 z-50">
                                <button onClick={() => navigate('/admin')} className="w-full text-left px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-700 hover:text-white flex items-center gap-2">
                                    <Settings size={16}/> Configuración
                                </button>
                                <button onClick={handleLogout} className="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-zinc-700 hover:text-red-300 flex items-center gap-2">
                                    <LogOut size={16}/> Cerrar Sesión
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* ── SEARCH BAR ── */}
            <div className="p-3 bg-zinc-950 border-b border-white/5 shrink-0">
                <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Search size={16} className="text-zinc-500" />
                    </div>
                    <input 
                        type="text" 
                        placeholder="Buscar un chat o proyecto..." 
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="w-full pl-10 pr-4 py-2 bg-zinc-900 border border-zinc-800 rounded-xl text-sm focus:outline-none focus:ring-1 focus:ring-purple-500 focus:border-purple-500 transition-all text-zinc-200 placeholder-zinc-500"
                    />
                </div>
            </div>

            {/* ── CHAT LIST (PROYECTOS) ── */}
            <div className="flex-1 overflow-y-auto custom-scrollbar">
                
                {/* General Hub */}
                <button 
                    onClick={() => { setSelectedProjectId(''); navigate('/kanban'); }}
                    className={`w-full p-3 flex items-start gap-4 transition-colors border-b border-white/5 ${
                        selectedProjectId === '' ? 'bg-purple-600/10' : 'hover:bg-zinc-900/50'
                    }`}
                >
                    <div className="w-12 h-12 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center shrink-0">
                        <MessageSquare size={24} className="text-white" />
                    </div>
                    <div className="flex-1 min-w-0 text-left py-1">
                        <div className="flex justify-between items-baseline mb-0.5">
                            <h3 className="font-semibold text-white truncate">Centro de Tareas</h3>
                            <span className="text-xs text-purple-400">Hoy</span>
                        </div>
                        <p className="text-sm text-zinc-400 truncate">Ver todas tus tareas combinadas...</p>
                    </div>
                </button>

                <div className="px-4 pt-4 pb-2 text-xs font-semibold text-zinc-500 uppercase tracking-wider">
                    Equipos & Grupos
                </div>

                {publicProjects.map(proj => (
                    <button 
                        key={proj.id}
                        onClick={() => { 
                            setProjectType('public'); 
                            setSelectedProjectId(proj.id.toString()); 
                            navigate('/kanban');
                        }}
                        className={`w-full p-3 flex items-start gap-3 transition-colors ${
                            selectedProjectId === proj.id.toString() ? 'bg-purple-600/10' : 'hover:bg-zinc-900/50'
                        }`}
                    >
                        <div className="w-12 h-12 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center shrink-0 text-zinc-400">
                            <Hash size={20} />
                        </div>
                        <div className="flex-1 min-w-0 text-left py-1">
                            <div className="flex justify-between items-baseline mb-0.5">
                                <h3 className="font-semibold text-white truncate pr-2">{proj.nombre}</h3>
                            </div>
                            <p className="text-sm text-zinc-500 truncate">Tablero colaborativo de equipo</p>
                        </div>
                    </button>
                ))}

                <div className="px-4 pt-6 pb-2 text-xs font-semibold text-zinc-500 uppercase tracking-wider flex items-center gap-1">
                    <Lock size={12} /> Personales
                </div>

                {privateProjects.map(proj => (
                    <button 
                        key={proj.id}
                        onClick={() => { 
                            setProjectType('private'); 
                            setSelectedProjectId(proj.id.toString()); 
                            navigate('/kanban');
                        }}
                        className={`w-full p-3 flex items-start gap-3 transition-colors ${
                            selectedProjectId === proj.id.toString() ? 'bg-orange-600/10' : 'hover:bg-zinc-900/50'
                        }`}
                    >
                        <div className="w-12 h-12 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center shrink-0 text-orange-400">
                            <Lock size={18} />
                        </div>
                        <div className="flex-1 min-w-0 text-left py-1">
                            <div className="flex justify-between items-baseline mb-0.5">
                                <h3 className="font-semibold text-white truncate pr-2">{proj.nombre}</h3>
                            </div>
                            <p className="text-sm text-zinc-500 truncate">Espacio de trabajo privado</p>
                        </div>
                    </button>
                ))}

            </div>

        </aside>
    );
}
