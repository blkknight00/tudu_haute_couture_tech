import { useState, useRef, useEffect } from 'react';
import { useAuth } from '../../contexts/AuthContext';
import { Moon, Sun, LogOut, User as UserIcon, Settings, FolderPlus, Tag, Users, UserPlus, Shield, RotateCcw, CreditCard, Archive, BookOpen, ChevronDown, Building2, Bell, BellOff } from 'lucide-react';
import { usePushNotifications } from '../../hooks/usePushNotifications';
import AISettingsModal from '../settings/AISettingsModal';
import TagsModal from '../settings/TagsModal';
import ProfileModal from '../profile/ProfileModal';
import UserGuideModal from '../guide/UserGuideModal';
import UserManagementModal from '../admin/UserManagementModal';
import ProjectManagementModal from '../admin/ProjectManagementModal';
import OrganizationManagementModal from '../admin/OrganizationManagementModal';
import LicensePanelModal from '../admin/LicensePanelModal';
import AuditLogModal from '../admin/AuditLogModal';
import SettingsModal from '../admin/SettingsModal';
import api from '../../api/axios';
import { BASE_URL } from '../../api/axios';

import { useNavigate } from 'react-router-dom';
import LanguageSwitcher from '../LanguageSwitcher';

/** Dropdown to show notifications and push subscribe option */
const NotificationDropdown = () => {
    const [isOpen, setIsOpen] = useState(false);
    const [notifications, setNotifications] = useState<any[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const dropdownRef = useRef<HTMLDivElement>(null);
    const navigate = useNavigate();

    // Push notifications integration
    const { isSupported, isSubscribed, isLoading, permission, subscribe, unsubscribe } = usePushNotifications();

    useEffect(() => {
        const fetchNotifications = async () => {
            try {
                const res = await api.get('/get_pending_notifications.php');
                if (res.data.status === 'success') {
                    setNotifications(res.data.notifications || []);
                    setUnreadCount(res.data.notifications?.length || 0);
                }
            } catch (err) {
                console.error("Error fetching notifications", err);
            }
        };
        fetchNotifications();
        // optionally poll every minute
        const interval = setInterval(fetchNotifications, 60000);
        return () => clearInterval(interval);
    }, []);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handlePushToggle = () => {
        if (isSubscribed) unsubscribe();
        else subscribe();
    };

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                id="tour-notifications"
                onClick={() => setIsOpen(!isOpen)}
                className="relative p-2 rounded-full transition-colors text-gray-500 hover:bg-gray-200 dark:text-tudu-text-muted-dark dark:hover:bg-gray-700"
            >
                {isSubscribed ? <Bell size={20} className="text-green-500" /> : <Bell size={20} />}
                {unreadCount > 0 && (
                    <span className="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold flex items-center justify-center rounded-full border border-white dark:border-gray-800">
                        {unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <div className="absolute right-0 mt-2 w-80 bg-white/85 dark:bg-tudu-content-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 py-2 z-[70] animate-fade-in-down origin-top-right">
                    <div className="px-4 py-2 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                        <span className="font-bold text-gray-800 dark:text-white">Notificaciones</span>
                        {unreadCount > 0 && (
                            <span className="text-[10px] font-bold bg-tudu-accent text-white px-2 py-0.5 rounded-full">{unreadCount} nuevas</span>
                        )}
                    </div>

                    <div className="max-h-[60vh] overflow-y-auto custom-scrollbar">
                        {notifications.length === 0 ? (
                            <div className="px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                                <Bell className="mx-auto mb-2 opacity-30" size={32} />
                                <p className="text-sm">No tienes alertas pendientes.</p>
                            </div>
                        ) : (
                            <div className="divide-y divide-gray-100 dark:divide-gray-800">
                                {notifications.map((notif, idx) => (
                                    <button
                                        key={idx}
                                        onClick={() => {
                                            setIsOpen(false);
                                            if (notif.url) navigate(notif.url);
                                        }}
                                        className="w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors flex items-start gap-3"
                                    >
                                        <div className="mt-1 bg-tudu-accent/10 text-tudu-accent p-1.5 rounded-full shrink-0">
                                            <Bell size={14} />
                                        </div>
                                        <div>
                                            <p className="text-sm font-semibold text-gray-800 dark:text-white">{notif.title}</p>
                                            <p className="text-xs text-tudu-text-muted mt-0.5 line-clamp-2">{notif.body}</p>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    {isSupported && (
                        <div className="px-4 py-3 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/20 rounded-b-xl">
                            <button
                                onClick={handlePushToggle}
                                disabled={isLoading || permission === 'denied'}
                                className={`w-full flex items-center justify-center gap-2 text-xs font-medium py-1.5 px-3 rounded-lg transition-colors border ${isSubscribed
                                    ? 'border-green-200 text-green-700 bg-green-50 hover:bg-green-100 dark:border-green-900/50 dark:text-green-400 dark:bg-green-900/20 dark:hover:bg-green-900/40'
                                    : 'border-gray-200 text-gray-600 bg-white hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600'
                                    } disabled:opacity-50`}
                            >
                                {isSubscribed ? <BellOff size={14} /> : <Bell size={14} />}
                                {isSubscribed ? 'Desactivar notificaciones push' : 'Activar notificaciones de escritorio'}
                            </button>
                            {permission === 'denied' && (
                                <p className="text-[10px] text-red-500 text-center mt-1">Permiso denegado en tu navegador.</p>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

const TopNavigation = () => {
    const { user, edition, logout, switchOrganization } = useAuth();
    const navigate = useNavigate();
    const [isDark, setIsDark] = useState(() => {
        const saved = localStorage.getItem('tudu-theme');
        if (saved) return saved === 'dark';
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    });

    // Menu States
    const [showUserMenu, setShowUserMenu] = useState(false);
    const [showAiSettings, setShowAiSettings] = useState(false);
    const [showTagsModal, setShowTagsModal] = useState(false);
    const [showProfileModal, setShowProfileModal] = useState(false);
    const [showGuideModal, setShowGuideModal] = useState(false);
    const [showUserModal, setShowUserModal] = useState(false);
    const [showProjectModal, setShowProjectModal] = useState(false);
    const [showOrgModal, setShowOrgModal] = useState(false);
    const [showLicenseModal, setShowLicenseModal] = useState(false);
    const [showAuditModal, setShowAuditModal] = useState(false);
    const [showSettingsModal, setShowSettingsModal] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    const openModalFromSettings = (modalName: string) => {
        setShowSettingsModal(false);
        switch(modalName) {
            case 'guide': setShowGuideModal(true); break;
            case 'projects': setShowProjectModal(true); break;
            case 'tags': setShowTagsModal(true); break;
            case 'users': setShowUserModal(true); break;
            case 'ai': setShowAiSettings(true); break;
            case 'audit': setShowAuditModal(true); break;
            case 'orgs': setShowOrgModal(true); break;
            case 'license': setShowLicenseModal(true); break;
        }
    };

    const isAdmin = user?.rol === 'super_admin' || user?.rol === 'administrador';

    // Close menu when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
                setShowUserMenu(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleResetTour = async () => {
        setShowUserMenu(false);
        try {
            await api.post('/onboarding.php?action=reset');
            window.location.reload(); // Reload to trigger tour
        } catch (error) {
            console.error('Error resetting tour:', error);
        }
    };

    // Update theme class and localStorage
    useEffect(() => {
        if (isDark) {
            document.documentElement.classList.add('dark');
            localStorage.setItem('tudu-theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('tudu-theme', 'light');
        }
    }, [isDark]);

    const toggleTheme = () => {
        setIsDark(!isDark);
    };

    return (
        <>
        <header className="relative haute-glass shadow-sm z-[70]">
            <div className="container mx-auto px-4 h-16 flex items-center justify-between">
                {/* Logo Section - Top Centered */}
                <div className="absolute left-1/2 top-1 transform -translate-x-1/2 flex items-center justify-center pointer-events-none">
                    <img
                        src={`${BASE_URL || ''}/tudu-logo-transparent.png`}
                        alt="TuDu Logo"
                        className="h-10 w-auto object-contain drop-shadow-sm"
                    />
                </div>

                {/* Left Section (Empty space or room for future items) */}
                <div className="flex items-center gap-3">
                    {/* Placeholder for left-aligned items if needed, keeps the flex layout stable */}
                </div>

                {/* Right Actions */}
                <div className="flex items-center gap-3">
                    {/* Language Switcher */}
                    <div className="hidden sm:block mr-2">
                        <LanguageSwitcher />
                    </div>

                    {/* Theme Toggle */}
                    <button
                        onClick={toggleTheme}
                        className="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 text-tudu-text-muted transition-colors"
                    >
                        {isDark ? <Sun size={20} /> : <Moon size={20} />}
                    </button>

                    {/* Notifications Dropdown */}
                    <NotificationDropdown />

                    {/* User Profile Menu */}
                    <div className="flex items-center gap-3 pl-4 border-l border-gray-300 dark:border-gray-600 relative" ref={menuRef}>
                        <div className="text-right hidden sm:block">
                            <p className="text-sm font-medium text-tudu-text-light dark:text-white leading-none">{user?.nombre || 'Usuario'}</p>
                            <p className="text-[10px] text-tudu-text-muted mt-1 uppercase font-bold tracking-tight">
                                {edition === 'corporate' && user?.organizacion_nombre ? (
                                    <span className="flex items-center justify-end gap-1">
                                        <span className="text-tudu-accent max-w-[120px] truncate" title={user.organizacion_nombre}>
                                            {user.organizacion_nombre}
                                        </span>
                                        {user?.rol_organizacion === 'admin' || user?.rol === 'super_admin' ? (
                                            <span className="px-1.5 py-0.5 rounded-md bg-tudu-accent/10 border border-tudu-accent/20 text-[9px] text-tudu-accent">ADMIN</span>
                                        ) : null}
                                    </span>
                                ) : (
                                    user?.rol?.replace('_', ' ') || 'Miembro'
                                )}
                            </p>
                        </div>
                        <button
                            onClick={() => setShowUserMenu(!showUserMenu)}
                            className="group flex items-center gap-1 focus:outline-none"
                        >
                            <div className="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center overflow-hidden border-2 border-white dark:border-gray-600 shadow-sm transition-transform transform group-hover:scale-105">
                                {user?.foto ? (
                                    <img
                                        src={user.foto.startsWith('http') ? user.foto : `${BASE_URL || ''}/${user.foto}`}
                                        alt="User"
                                        className="w-full h-full object-cover"
                                    />
                                ) : (
                                    <UserIcon size={20} className="text-gray-400" />
                                )}
                            </div>
                            <ChevronDown size={14} className="text-gray-400 group-hover:text-tudu-accent transition-colors" />
                        </button>

                        {/* Dropdown Menu */}
                        {showUserMenu && (
                            <div className="absolute right-0 top-14 w-64 bg-white/85 dark:bg-tudu-content-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 py-2 z-[70] transform transition-all animate-fade-in-down origin-top-right">

                                {/* Info (Mobile Only) */}
                                <div className="sm:hidden px-4 py-3 border-b border-gray-100 dark:border-gray-700 mb-1">
                                    <p className="font-bold text-gray-800 dark:text-white truncate">{user?.nombre}</p>
                                    <p className="text-xs text-tudu-text-muted capitalize mb-3">{user?.rol?.replace('_', ' ')}</p>
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-bold text-gray-500">Idioma</span>
                                        <LanguageSwitcher />
                                    </div>
                                </div>

                                <div className="max-h-[70vh] overflow-y-auto custom-scrollbar">
                                    {/* Comunes */}
                                    <button onClick={() => { setShowUserMenu(false); setShowProfileModal(true); }} className="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-2">
                                        <UserIcon size={16} /> Perfil de Usuario
                                    </button>
                                    <button onClick={() => { setShowUserMenu(false); setShowGuideModal(true); }} className="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-2">
                                        <BookOpen size={16} /> Guía de Uso
                                    </button>
                                    <button onClick={() => { setShowUserMenu(false); setShowUserModal(true); }} className="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-2">
                                        <UserPlus size={16} /> Invitar Usuarios
                                    </button>

                                    {edition === 'corporate' && (
                                        <div className="px-4 py-2 bg-blue-50/50 dark:bg-blue-900/10 border-y border-blue-100 dark:border-blue-900/30 my-1">
                                            <p className="text-[10px] text-blue-600 dark:text-blue-400 font-bold uppercase transition-all flex justify-between items-center">
                                                <span>Empresa</span>
                                                {user?.rol === 'admin_global' && <span className="bg-purple-100 text-purple-700 px-1 rounded">Global</span>}
                                            </p>
                                            <p className="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{user?.organizacion_nombre}</p>

                                            {/* Org Switcher if multiple */}
                                            {user?.organizations && user.organizations.length > 1 && (
                                                <div className="mt-2 space-y-1">
                                                    <p className="text-[9px] text-gray-400 uppercase font-semibold">Cambiar a:</p>
                                                    {user.organizations.map(org => (
                                                        org.id !== user.organizacion_id && (
                                                            <button
                                                                key={org.id}
                                                                onClick={async () => {
                                                                    const success = await switchOrganization(org.id);
                                                                    if (success) {
                                                                        setShowUserMenu(false);
                                                                        window.location.reload(); // Hard refresh to clear all contexts
                                                                    }
                                                                }}
                                                                className="w-full text-left px-2 py-1 text-xs text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded transition-colors truncate"
                                                            >
                                                                {org.nombre}
                                                            </button>
                                                        )
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {/* Settings / Configuración Central */}
                                    <div className="h-px bg-gray-100 dark:bg-gray-700 my-1 mx-2"></div>
                                    <button onClick={() => { setShowUserMenu(false); setShowSettingsModal(true); }} className="w-full text-left px-4 py-2 text-sm text-gray-800 dark:text-gray-200 font-bold hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-2">
                                        <Settings size={16} /> Configuración General
                                    </button>

                                    <div className="h-px bg-gray-100 dark:bg-gray-700 my-1 mx-2"></div>

                                    {/* Archivo (Recursos) */}
                                    <button onClick={() => { setShowUserMenu(false); navigate('/resources'); }} className="w-full text-left px-4 py-2 text-sm text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 flex items-center gap-2 font-medium">
                                        <Archive size={16} /> Archivo (Todos)
                                    </button>

                                    <div className="h-px bg-gray-100 dark:bg-gray-700 my-1 mx-2"></div>

                                    {/* Logout */}
                                    <button onClick={logout} className="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2 rounded-b-xl">
                                        <LogOut size={16} /> Cerrar Sesión
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </header>

        {/* Portals / Modals - Rendered outside of <header> to avoid CSS filter trapping (backdrop-blur) */}
        
            <SettingsModal isOpen={showSettingsModal} onClose={() => setShowSettingsModal(false)} openModal={openModalFromSettings} />
            <AISettingsModal isOpen={showAiSettings} onClose={() => setShowAiSettings(false)} />
            <TagsModal isOpen={showTagsModal} onClose={() => setShowTagsModal(false)} />
            <ProfileModal isOpen={showProfileModal} onClose={() => setShowProfileModal(false)} />
            <UserGuideModal isOpen={showGuideModal} onClose={() => setShowGuideModal(false)} />
            <UserManagementModal isOpen={showUserModal} onClose={() => setShowUserModal(false)} />
            <ProjectManagementModal isOpen={showProjectModal} onClose={() => setShowProjectModal(false)} />
            <OrganizationManagementModal isOpen={showOrgModal} onClose={() => setShowOrgModal(false)} />
            <LicensePanelModal isOpen={showLicenseModal} onClose={() => setShowLicenseModal(false)} />
            <AuditLogModal isOpen={showAuditModal} onClose={() => setShowAuditModal(false)} />
        </>
    );
};

export default TopNavigation;
