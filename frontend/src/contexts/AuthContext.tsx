import { createContext, useContext, useState, useEffect, type ReactNode } from 'react';
import api from '../api/axios';

interface User {
    id: number;
    nombre: string;
    username: string;
    rol: string;
    rol_organizacion?: string;
    foto: string | null;
    organizacion_id: number | null;
    organizacion_nombre: string | null;
    organizations?: { id: number; nombre: string }[];
}

interface AuthContextType {
    user: User | null;
    edition: 'standard' | 'corporate' | null;
    isLoading: boolean;
    login: (user: User, edition?: string, token?: string) => void;
    logout: () => void;
    checkSession: () => Promise<void>;
    switchOrganization: (orgId: number) => Promise<boolean>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider = ({ children }: { children: ReactNode }) => {
    const [user, setUser] = useState<User | null>(null);
    const [edition, setEdition] = useState<'standard' | 'corporate' | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const checkSession = async () => {
        try {
            const { data } = await api.get('/auth.php?action=check_session');
            if (data.status === 'authenticated') {
                setUser(data.user);
                setEdition(data.edition || 'standard');
            } else {
                localStorage.removeItem('tudu_jwt_token');
                setUser(null);
            }
        } catch (error: any) {
            // 401 = no active session, this is normal on first load
            if (error?.response?.status === 401) {
                localStorage.removeItem('tudu_jwt_token');
                setUser(null);
            } else {
                console.error('Session check failed', error);
                localStorage.removeItem('tudu_jwt_token');
                setUser(null);
            }
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        checkSession();
    }, []);

    const login = (userData: User, userEdition?: string, token?: string) => {
        if (token) localStorage.setItem('tudu_jwt_token', token);
        setUser(userData);
        if (userEdition) setEdition(userEdition as any);
    };

    const logout = async () => {
        try {
            await api.get('/auth.php?action=logout');
        } catch (error) {
            console.error('Logout API failed', error);
        } finally {
            localStorage.removeItem('tudu_jwt_token');
            setUser(null);
            setEdition(null);
            window.location.hash = '#/login';
        }
    };

    const switchOrganization = async (orgId: number) => {
        try {
            const { data } = await api.post('/auth.php?action=switch_org', { organizacion_id: orgId });
            if (data.status === 'success' && data.token) {
                localStorage.setItem('tudu_jwt_token', data.token);
                await checkSession();
                return true;
            }
            return false;
        } catch (error) {
            console.error('Switching organization failed', error);
            return false;
        }
    };

    return (
        <AuthContext.Provider value={{ user, edition, isLoading, login, logout, checkSession, switchOrganization }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
};
