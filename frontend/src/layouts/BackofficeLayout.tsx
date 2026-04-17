import type { ReactNode } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Rocket, Box, ShieldAlert, LogOut, ArrowLeft } from 'lucide-react';

interface BackofficeLayoutProps {
    children: ReactNode;
}

const BackofficeLayout = ({ children }: BackofficeLayoutProps) => {
    const { logout } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();

    const menuItems = [
        { id: '/backoffice', label: 'Dashboard General', icon: <Rocket size={20} /> },
        { id: '/backoffice/tenants', label: 'Inquilinos (Tenants)', icon: <Box size={20} /> },
        { id: '/backoffice/audit', label: 'Auditoría Global', icon: <ShieldAlert size={20} /> }
    ];

    return (
        <div className="flex h-screen bg-slate-950 text-slate-300 font-sans overflow-hidden">
            {/* Sidebar */}
            <aside className="w-64 bg-slate-900 border-r border-slate-800 flex flex-col shadow-2xl relative z-10">
                <div className="h-20 flex items-center justify-center border-b border-slate-800/50">
                    <div className="flex flex-col items-center">
                        <h1 className="text-xl font-bold bg-gradient-to-r from-amber-400 to-orange-500 bg-clip-text text-transparent uppercase tracking-widest flex items-center gap-2">
                            <Rocket size={24} className="text-amber-500" />
                            GOD MODE
                        </h1>
                        <span className="text-[10px] text-slate-500 uppercase font-mono tracking-widest mt-1">Super Admin Console</span>
                    </div>
                </div>

                <nav className="flex-1 px-4 py-8 space-y-2">
                    {menuItems.map(item => {
                        const isActive = location.pathname === item.id;
                        return (
                            <button
                                key={item.id}
                                onClick={() => navigate(item.id)}
                                className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all ${
                                    isActive
                                    ? 'bg-amber-500/10 text-amber-500 border border-amber-500/20 shadow-lg shadow-amber-500/5 font-semibold'
                                    : 'text-slate-400 hover:bg-slate-800/50 hover:text-slate-200'
                                }`}
                            >
                                {item.icon}
                                <span className="text-sm">{item.label}</span>
                            </button>
                        );
                    })}
                </nav>

                <div className="p-4 border-t border-slate-800/50 space-y-2">
                    <button
                        onClick={() => navigate('/dashboard')}
                        className="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800/50 hover:text-slate-200 transition-all text-sm"
                    >
                        <ArrowLeft size={20} /> Volver a TuDu App
                    </button>
                    <button
                        onClick={logout}
                        className="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-red-400/80 hover:bg-red-500/10 hover:text-red-400 transition-all text-sm"
                    >
                        <LogOut size={20} /> Cerrar Sesión Segura
                    </button>
                </div>
            </aside>

            {/* Main Content Area */}
            <main className="flex-1 bg-slate-950 overflow-y-auto custom-scrollbar relative">
                {/* Estilo matriz sutil de fondo */}
                <div className="absolute inset-0 bg-[linear-gradient(to_right,#80808012_1px,transparent_1px),linear-gradient(to_bottom,#80808012_1px,transparent_1px)] bg-[size:24px_24px] pointer-events-none opacity-20"></div>
                
                <div className="relative z-10 p-8 max-w-7xl mx-auto min-h-full">
                    {children}
                </div>
            </main>
        </div>
    );
};

export default BackofficeLayout;
