import { createContext, useContext, useState, type ReactNode } from 'react';

interface ProjectModalContextType {
    isProjectModalOpen: boolean;
    editingProject: any | null;
    openNewProjectModal: () => void;
    openEditProjectModal: (project: any) => void;
    closeProjectModal: () => void;
    refreshProjects: () => void;
    setRefreshProjects: (fn: () => void) => void;
}

const ProjectModalContext = createContext<ProjectModalContextType | undefined>(undefined);

export const ProjectModalProvider = ({ children }: { children: ReactNode }) => {
    const [isProjectModalOpen, setIsProjectModalOpen] = useState(false);
    const [editingProject, setEditingProject] = useState<any | null>(null);
    const [refreshTrigger, setRefreshTrigger] = useState<() => void>(() => () => { });

    const openNewProjectModal = () => {
        setEditingProject(null);
        setIsProjectModalOpen(true);
    };

    const openEditProjectModal = (project: any) => {
        setEditingProject(project);
        setIsProjectModalOpen(true);
    };

    const closeProjectModal = () => {
        setIsProjectModalOpen(false);
        setEditingProject(null);
    };

    const refreshProjects = () => {
        refreshTrigger();
    };

    const setRefreshProjects = (fn: () => void) => {
        setRefreshTrigger(() => fn);
    };

    return (
        <ProjectModalContext.Provider value={{
            isProjectModalOpen,
            editingProject,
            openNewProjectModal,
            openEditProjectModal,
            closeProjectModal,
            refreshProjects,
            setRefreshProjects
        }}>
            {children}
        </ProjectModalContext.Provider>
    );
};

export const useProjectModal = () => {
    const context = useContext(ProjectModalContext);
    if (!context) {
        throw new Error('useProjectModal must be used within a ProjectModalProvider');
    }
    return context;
};
