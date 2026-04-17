import { createContext, useContext, useState, type ReactNode } from 'react';

interface TaskModalContextType {
    isModalOpen: boolean;
    editingTask: any | null;
    openNewTaskModal: (initialData?: any) => void;
    openEditTaskModal: (task: any) => void;
    closeModal: () => void;
    refreshBoard: () => void; // Trigger to refresh data
    setRefreshBoard: (fn: () => void) => void; // Register the refresh function
}

const TaskModalContext = createContext<TaskModalContextType | undefined>(undefined);

export const TaskModalProvider = ({ children }: { children: ReactNode }) => {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingTask, setEditingTask] = useState<any | null>(null);
    const [refreshTrigger, setRefreshTrigger] = useState<() => void>(() => () => { });

    const openNewTaskModal = (initialData?: any) => {
        setEditingTask(initialData || null); // Reuse editingTask state for initial data if it's just partial
        setIsModalOpen(true);
    };

    const openEditTaskModal = (task: any) => {
        setEditingTask(task);
        setIsModalOpen(true);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        setEditingTask(null);
    };

    const refreshBoard = () => {
        refreshTrigger();
    };

    const setRefreshBoard = (fn: () => void) => {
        setRefreshTrigger(() => fn);
    };

    return (
        <TaskModalContext.Provider value={{
            isModalOpen,
            editingTask,
            openNewTaskModal,
            openEditTaskModal,
            closeModal,
            refreshBoard,
            setRefreshBoard
        }}>
            {children}
        </TaskModalContext.Provider>
    );
};

export const useTaskModal = () => {
    const context = useContext(TaskModalContext);
    if (!context) {
        throw new Error('useTaskModal must be used within a TaskModalProvider');
    }
    return context;
};
