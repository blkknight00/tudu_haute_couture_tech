import { useState, useEffect } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { LayoutDashboard, Kanban, Calendar as CalendarIcon, Plus, ChevronDown, Lock, Globe } from 'lucide-react';
import { useTaskModal } from '../../contexts/TaskModalContext';
import { useProjectFilter } from '../../contexts/ProjectFilterContext';
import api from '../../api/axios';

const ActionBar = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const { openNewTaskModal } = useTaskModal();
    const { projectType, setProjectType, selectedProjectId, setSelectedProjectId } = useProjectFilter();

    const [projects, setProjects] = useState<any[]>([]);
    const [showProjectDropdown, setShowProjectDropdown] = useState(false);

    useEffect(() => {
        fetchProjects();

        // Listen for global project-saved event to refresh dropdown
        const handleProjectSaved = () => fetchProjects();
        window.addEventListener('project-saved', handleProjectSaved);

        return () => window.removeEventListener('project-saved', handleProjectSaved);
    }, []);

    const fetchProjects = async () => {
        try {
            const res = await api.get('/get_options.php');
            if (res.data.status === 'success') {
                // Deduplicate by id — backend DISTINCT should handle this, but we add a safety net
                const seen = new Set<number>();
                const uniqueProjects = (res.data.projects as any[]).filter(p => {
                    if (seen.has(p.id)) return false;
                    seen.add(p.id);
                    return true;
                });
                setProjects(uniqueProjects);
            }
        } catch (error) {
            console.error('Error fetching projects:', error);
        }
    };

    // Filter projects based on type
    const filteredProjects = projects.filter(p => {
        if (projectType === 'public') return p.user_id === 0;
        return p.user_id !== 0;
    });

    // Reset selection if project no longer fits type
    useEffect(() => {
        if (selectedProjectId) {
            const currentProject = projects.find(p => p.id.toString() === selectedProjectId);
            if (currentProject) {
                const belongsToCurrentType = projectType === 'public' ? currentProject.user_id === 0 : currentProject.user_id !== 0;
                if (!belongsToCurrentType) {
                    setSelectedProjectId('');
                }
            }
        }
    }, [projectType, projects, selectedProjectId, setSelectedProjectId]);

    const currentProjectName = projects.find(p => p.id.toString() === selectedProjectId)?.nombre || 'Todos los Proyectos';

    return (
        <div className="haute-glass border-b border-gray-200 dark:border-gray-700 shadow-xs px-3 py-2 relative z-[60]">
            <div className="container mx-auto flex items-center justify-between gap-2 flex-wrap">

                {/* Left: View Switcher */}
                <div id="tour-view-switcher" className="hidden sm:flex bg-gray-100 dark:bg-tudu-column-dark p-1 rounded-lg">
                    <button
                        onClick={() => navigate('/')}
                        className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-all ${location.pathname === '/' ? 'bg-white dark:bg-tudu-bg-dark text-tudu-accent shadow-sm' : 'text-tudu-text-muted hover:text-tudu-text-light dark:hover:text-white'}`}
                    >
                        <LayoutDashboard size={16} />
                        <span>Dashboard</span>
                    </button>
                    <button
                        onClick={() => navigate('/kanban')}
                        className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-all ${location.pathname === '/kanban' ? 'bg-white dark:bg-tudu-bg-dark text-tudu-accent shadow-sm' : 'text-tudu-text-muted hover:text-tudu-text-light dark:hover:text-white'}`}
                    >
                        <Kanban size={16} />
                        <span>Tablero</span>
                    </button>
                    <button
                        onClick={() => navigate('/calendar')}
                        className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-all ${location.pathname === '/calendar' ? 'bg-white dark:bg-tudu-bg-dark text-tudu-accent shadow-sm' : 'text-tudu-text-muted hover:text-tudu-text-light dark:hover:text-white'}`}
                    >
                        <CalendarIcon size={16} />
                        <span>Calendario</span>
                    </button>
                </div>

                {/* Right Area */}
                <div className="flex items-center gap-2 ml-auto">

                    {/* Public/Private Toggle — visible on md+ and also accessible on mobile */}
                    <div id="tour-visibility-toggle" className="flex bg-gray-100 dark:bg-tudu-column-dark p-1 rounded-lg">
                        <button
                            onClick={() => setProjectType('public')}
                            className={`p-1.5 rounded-md transition-all ${projectType === 'public' ? 'bg-white dark:bg-tudu-bg-dark text-blue-500 shadow-sm' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200'}`}
                            title="Proyectos Públicos"
                        >
                            <Globe size={15} />
                        </button>
                        <button
                            onClick={() => setProjectType('private')}
                            className={`p-1.5 rounded-md transition-all ${projectType === 'private' ? 'bg-white dark:bg-tudu-bg-dark text-orange-500 shadow-sm' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200'}`}
                            title="Proyectos Privados"
                        >
                            <Lock size={15} />
                        </button>
                    </div>

                    {/* Project Selector */}
                    <div className="relative">
                        <button
                            onClick={() => setShowProjectDropdown(!showProjectDropdown)}
                            className="flex items-center gap-1.5 px-2 sm:px-3 py-2 bg-gray-50 dark:bg-tudu-bg-dark border border-gray-200 dark:border-gray-600 rounded-lg text-xs sm:text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                        >
                            <span className="max-w-[80px] sm:max-w-[130px] truncate">{currentProjectName}</span>
                            <ChevronDown size={13} className={`transition-transform shrink-0 ${showProjectDropdown ? 'rotate-180' : ''}`} />
                        </button>

                        {showProjectDropdown && (
                            <div className="absolute top-11 right-0 w-64 haute-glass rounded-xl shadow-xl z-50 py-2 animate-fade-in-down">
                                <button
                                    onClick={() => { setSelectedProjectId(''); setShowProjectDropdown(false); }}
                                    className="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                >
                                    Todos los Proyectos
                                </button>
                                <div className="h-px bg-gray-100 dark:bg-gray-700 my-1 mx-2"></div>
                                <div className="max-h-64 overflow-y-auto custom-scrollbar">
                                    {filteredProjects.map((proj) => (
                                        <button
                                            key={proj.id}
                                            onClick={() => { setSelectedProjectId(proj.id.toString()); setShowProjectDropdown(false); }}
                                            className={`w-full text-left px-4 py-2 text-sm transition-colors flex items-center justify-between ${selectedProjectId === proj.id.toString() ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 font-medium' : 'hover:bg-gray-100 dark:hover:bg-gray-800'}`}
                                        >
                                            <span className="truncate">{proj.nombre}</span>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* New Project Button */}
                    <button
                        onClick={() => {
                            const event = new CustomEvent('open-project-modal');
                            window.dispatchEvent(event);
                        }}
                        className="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white px-2 sm:px-3 py-2 rounded-lg text-sm font-bold transition-all shadow-md shadow-blue-500/20 active:scale-95"
                        title="Nuevo Proyecto"
                    >
                        <Plus size={16} />
                        <span className="hidden sm:inline">Nuevo Proyecto</span>
                    </button>

                    {/* New Task Button */}
                    <button
                        id="tour-new-task"
                        onClick={() => openNewTaskModal()}
                        className="flex items-center gap-1.5 bg-tudu-accent hover:bg-tudu-accent-hover text-white px-3 py-2 rounded-lg text-sm font-bold transition-all shadow-md shadow-tudu-accent/20 active:scale-95"
                    >
                        <Plus size={16} />
                        <span className="hidden sm:inline">Nueva Tarea</span>
                        <span className="sm:hidden">Tarea</span>
                    </button>
                </div>
            </div>
        </div>
    );
};

export default ActionBar;
