import { useNavigate, useLocation } from 'react-router-dom';
import { LayoutDashboard, Columns, Calendar, MessageSquare } from 'lucide-react';

const BottomNavigation = () => {
    const navigate = useNavigate();
    const location = useLocation();

    const navItems = [
        { icon: LayoutDashboard, label: 'Resumen', path: '/' },
        { icon: Columns, label: 'Kanban', path: '/kanban' },
        { icon: MessageSquare, label: 'AI Chat', path: 'ai', isAi: true },
        { icon: Calendar, label: 'Calendario', path: '/calendar' },
    ];

    return (
        <nav className="sm:hidden fixed bottom-0 left-0 right-0 bg-white/85 dark:bg-tudu-content-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10 border-t border-gray-200 dark:border-gray-800 px-2 py-2 z-[60] flex justify-around items-center shadow-[0_-4px_10px_rgba(0,0,0,0.05)]">
            {navItems.map((item) => (
                <button
                    key={item.path}
                    onClick={() => {
                        if (item.isAi) {
                            const event = new CustomEvent('open-ai-chat');
                            window.dispatchEvent(event);
                        } else {
                            navigate(item.path);
                        }
                    }}
                    className={`flex flex-col items-center gap-0.5 transition-colors px-2 py-1 rounded-xl ${item.isAi
                        ? 'relative -top-5'
                        : location.pathname === item.path
                            ? 'text-tudu-accent font-bold'
                            : 'text-gray-500 dark:text-gray-400'
                        }`}
                >
                    {item.isAi ? (
                        <div className="bg-tudu-accent p-3.5 rounded-full shadow-lg border-4 border-white dark:border-tudu-bg-dark text-white transform active:scale-95 transition-transform">
                            <item.icon size={24} />
                        </div>
                    ) : (
                        <>
                            <item.icon size={20} />
                            <span className="text-[9px] font-medium">{item.label}</span>
                        </>
                    )}
                </button>
            ))}
        </nav>
    );
};

export default BottomNavigation;
