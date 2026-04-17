import { useEffect } from 'react';
import { Outlet, Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import TopNavigation from '../components/layout/TopNavigation';
import ActionBar from '../components/layout/ActionBar';
import OnboardingTour from '../components/onboarding/OnboardingTour';
import AIAgentOverlay from '../components/ai/AIAgentOverlay';
import TaskModal from '../components/tasks/TaskModal';
import ProjectModal from '../components/projects/ProjectModal';
import { useTaskModal } from '../contexts/TaskModalContext';
import { useProjectModal } from '../contexts/ProjectModalContext';
import BottomNavigation from '../components/layout/BottomNavigation';

const MainLayout = () => {
    const { user, isLoading } = useAuth();
    const location = useLocation();
    const { isModalOpen, closeModal, editingTask, refreshBoard } = useTaskModal();
    const { isProjectModalOpen, closeProjectModal, openNewProjectModal, editingProject: editingProjectProject, refreshProjects } = useProjectModal();

    useEffect(() => {
        const handleOpenProject = () => openNewProjectModal();
        window.addEventListener('open-project-modal', handleOpenProject);
        return () => window.removeEventListener('open-project-modal', handleOpenProject);
    }, [openNewProjectModal]);

    if (isLoading) {
        return <div className="min-h-screen flex items-center justify-center dark:bg-tudu-bg-dark">Cargando...</div>;
    }

    if (!user) {
        return <Navigate to="/login" replace />;
    }

    const showActionBar = location.pathname === '/' || location.pathname === '/kanban' || location.pathname === '/calendar';

    return (
        <div className="h-screen flex flex-col bg-transparent text-tudu-text-light dark:text-tudu-text-dark transition-colors duration-300 overflow-hidden">
            {/* Header Global */}
            <TopNavigation />

            {/* Sub-header Contextual */}
            {showActionBar && <ActionBar />}

            {/* Feature Tour (Onboarding) */}
            <OnboardingTour />

            {/* Contenido Principal */}
            <main className="flex-1 w-full overflow-hidden relative flex flex-col">
                <div className="flex-1 w-full overflow-y-auto custom-scrollbar flex flex-col pb-20 sm:pb-0">
                    <div className="container mx-auto px-4 py-6 flex-1 flex flex-col">
                        <Outlet />
                    </div>
                </div>
            </main>

            {/* Global Modal */}
            <TaskModal
                isOpen={isModalOpen}
                onClose={closeModal}
                task={editingTask}
                onSave={refreshBoard}
            />
            <ProjectModal
                isOpen={isProjectModalOpen}
                onClose={closeProjectModal}
                project={editingProjectProject} // Rename to avoid conflict? No, destructured differently.
                onSave={refreshProjects}
            />
            <AIAgentOverlay />
            <BottomNavigation />
        </div>
    );
};

export default MainLayout;
