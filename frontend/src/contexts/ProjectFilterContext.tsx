import { createContext, useContext, useState, type ReactNode } from 'react';

interface ProjectFilterContextType {
    projectType: 'public' | 'private';
    setProjectType: (type: 'public' | 'private') => void;
    selectedProjectId: string;
    setSelectedProjectId: (id: string) => void;
}

const ProjectFilterContext = createContext<ProjectFilterContextType | undefined>(undefined);

export const ProjectFilterProvider = ({ children }: { children: ReactNode }) => {
    const [projectType, setProjectType] = useState<'public' | 'private'>('public');
    const [selectedProjectId, setSelectedProjectId] = useState<string>('');

    return (
        <ProjectFilterContext.Provider value={{
            projectType,
            setProjectType,
            selectedProjectId,
            setSelectedProjectId
        }}>
            {children}
        </ProjectFilterContext.Provider>
    );
};

export const useProjectFilter = () => {
    const context = useContext(ProjectFilterContext);
    if (!context) {
        throw new Error('useProjectFilter must be used within a ProjectFilterProvider');
    }
    return context;
};
